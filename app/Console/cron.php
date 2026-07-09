<?php

declare(strict_types=1);

// Bootstraper l'application
require_once __DIR__ . '/../../bootstrap/app.php';

use App\Models\Database;
use App\Models\Finance\EtatJournalier;
use App\Repositories\Finance\FactureRepository;
use App\Repositories\Finance\PaiementRepository;
use App\Repositories\Finance\EtatJournalierRepository;
use App\Services\Shared\NotificationService;
use App\Services\Shared\AuditLogService;

echo "[LBP-CRON] Démarrage des tâches planifiées...\n";

$db = Database::getConnection();
$factureRepo = new FactureRepository($db);
$paiementRepo = new PaiementRepository($db);
$etatRepo = new EtatJournalierRepository($db);
$notifService = new NotificationService();

$dateJour = date('Y-m-d');
$now = date('Y-m-d H:i:s');

// ---------------------------------------------------------------------
// TÂCHE 1 : Verrouillage automatique des états journaliers (à 15h00)
// ---------------------------------------------------------------------
echo "[LBP-CRON] Tâche 1 : Vérification et verrouillage automatique des points de caisse (15h00)...\n";

try {
    // Récupérer toutes les agences actives
    $stmt = $db->query("SELECT id, name FROM company_sites WHERE is_active = 1");
    $agences = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($agences as $agence) {
        $agenceId = (int) $agence['id'];
        $existing = $etatRepo->findByAgenceAndDate($agenceId, $dateJour);

        if (!$existing) {
            // Aucun point n'a été créé : on calcule en direct et on soumet/verrouille automatiquement
            $live = $etatRepo->computeTotalsForDay($agenceId, $dateJour);

            // On ne crée de point auto que si l'agence a eu une activité (colis, factures ou encaissements)
            if ($live['nb_colis'] > 0 || $live['nb_factures'] > 0 || $live['total_encaisse_xof'] > 0) {
                $etat = new EtatJournalier(
                    id: null,
                    agenceId: $agenceId,
                    chefAgenceId: null, // Système / Auto-verrouillage
                    dateJour: $dateJour,
                    nbColisEnregistres: $live['nb_colis'],
                    nbFacturesEmises: $live['nb_factures'],
                    totalFactureXof: $live['total_facture_xof'],
                    totalFactureEur: $live['total_facture_eur'],
                    totalEncaisseXof: $live['total_encaisse_xof'],
                    totalEncaisseEur: $live['total_encaisse_eur'],
                    totalRestantDuXof: $live['total_restant_du_xof'],
                    totalRestantDuEur: $live['total_restant_du_eur'],
                    soldeCaisseAgenceXof: $live['solde_caisse_agence_xof'],
                    soldeCaisseAgenceEur: $live['solde_caisse_agence_eur'],
                    statut: 'soumis',
                    dateSoumission: date('Y-m-d') . ' 15:00:00'
                );

                $reportId = $etatRepo->create($etat);
                AuditLogService::log('cron_auto_submit', 'lbp_etats_journaliers', $reportId, null, $live);
                echo " -> Agence [{$agence['name']}] : Point de caisse généré et verrouillé automatiquement.\n";
            }
        } elseif ($existing->statut === 'brouillon') {
            // Si le point est en brouillon, on le passe en soumis (verrouillage)
            $live = $etatRepo->computeTotalsForDay($agenceId, $dateJour);
            
            $existing->nbColisEnregistres = $live['nb_colis'];
            $existing->nbFacturesEmises = $live['nb_factures'];
            $existing->totalFactureXof = $live['total_facture_xof'];
            $existing->totalFactureEur = $live['total_facture_eur'];
            $existing->totalEncaisseXof = $live['total_encaisse_xof'];
            $existing->totalEncaisseEur = $live['total_encaisse_eur'];
            $existing->totalRestantDuXof = $live['total_restant_du_xof'];
            $existing->totalRestantDuEur = $live['total_restant_du_eur'];
            $existing->soldeCaisseAgenceXof = $live['solde_caisse_agence_xof'];
            $existing->soldeCaisseAgenceEur = $live['solde_caisse_agence_eur'];
            $existing->statut = 'soumis';
            $existing->dateSoumission = date('Y-m-d') . ' 15:00:00';

            $etatRepo->update($existing);
            AuditLogService::log('cron_auto_lock', 'lbp_etats_journaliers', $existing->id, null, (array)$existing);
            echo " -> Agence [{$agence['name']}] : Point de caisse brouillon verrouillé automatiquement.\n";
        }
    }
} catch (\Exception $e) {
    echo " -> [ERREUR] Verrouillage des caisses : " . $e->getMessage() . "\n";
}

// ---------------------------------------------------------------------
// TÂCHE 2 : Relance automatique des factures en retard de paiement
// ---------------------------------------------------------------------
echo "[LBP-CRON] Tâche 2 : Relances automatiques de solde...\n";

try {
    // Récupérer les factures non payées dont la date d'échéance est passée
    $stmt = $db->prepare("
        SELECT f.*, cl.name as client_name, cl.phone as client_phone 
        FROM lbp_factures f
        JOIN lbp_clients cl ON f.client_id = cl.id
        WHERE f.statut IN ('emise', 'partiellement_payee') 
          AND f.date_echeance_solde < :now
    ");
    $stmt->execute(['now' => $now]);
    $facturesEnRetard = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($facturesEnRetard as $fData) {
        $factureId = (int) $fData['id'];
        
        // Eviter de relancer plus d'une fois toutes les 24h
        $stmtCheck = $db->prepare("
            SELECT COUNT(*) FROM lbp_rappel_soldes 
            WHERE facture_id = :facture_id 
              AND date_rappel > DATE_SUB(:now, INTERVAL 1 DAY)
        ");
        $stmtCheck->execute(['facture_id' => $factureId, 'now' => $now]);
        if ((int) $stmtCheck->fetchColumn() > 0) {
            continue; // Déjà relancé récemment
        }

        if (empty($fData['client_phone'])) {
            continue;
        }

        $paymentUrl = rtrim((require BASE_PATH . '/config/app.php')['url'], '/') . '/api/paiements/pay/' . $factureId;
        
        $message = sprintf(
            "Cher client %s, votre facture %s présente un retard de paiement. Solde restant : %s %s. Veuillez régulariser votre situation en payant en ligne ici : %s",
            $fData['client_name'],
            $fData['numero_facture'],
            number_format((float) $fData['montant_restant'], 0, ',', ' '),
            $fData['devise'],
            $paymentUrl
        );

        // Envoyer par WhatsApp par défaut pour les relances automatiques
        $sent = $notifService->send($fData['client_phone'], $message, 'whatsapp');

        if ($sent) {
            // Historiser le rappel
            $stmtInsert = $db->prepare("
                INSERT INTO lbp_rappel_soldes (facture_id, caissiere_id, canal, date_rappel)
                VALUES (:facture_id, NULL, 'whatsapp', NOW())
            ");
            $stmtInsert->execute(['facture_id' => $factureId]);
            echo " -> Facture [{$fData['numero_facture']}] : Relance automatique envoyée à {$fData['client_name']}.\n";
        }
    }
} catch (\Exception $e) {
    echo " -> [ERREUR] Relances automatiques : " . $e->getMessage() . "\n";
}

echo "[LBP-CRON] Fin de l'exécution.\n";
