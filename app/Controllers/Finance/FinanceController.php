<?php

declare(strict_types=1);

namespace App\Controllers\Finance;

use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Helpers\Response;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Models\Database;
use App\Models\Finance\Facture;
use App\Models\Finance\Paiement;
use App\Models\Finance\Recu;
use App\Models\Finance\PaiementCallback;
use App\Models\Finance\EtatJournalier;
use App\Models\Finance\DemandePaiement;
use App\Models\Finance\EcritureComptable;
use App\Repositories\Finance\FactureRepository;
use App\Repositories\Finance\PaiementRepository;
use App\Repositories\Finance\EtatJournalierRepository;
use App\Repositories\Finance\DemandePaiementRepository;
use App\Repositories\Finance\ComptabiliteRepository;
use App\Services\Shared\AuditLogService;
use App\Services\Shared\IntegrityRuleEngine;
use App\Services\Shared\NotificationService;
use App\Repositories\Shared\NotificationRepository;
use PDO;

final class FinanceController extends FinanceBaseController
{
    private PDO $db;
    private FactureRepository $factureRepo;
    private PaiementRepository $paiementRepo;
    private EtatJournalierRepository $etatRepo;
    private DemandePaiementRepository $demandeRepo;
    private ComptabiliteRepository $comptabiliteRepo;
    private NotificationService $notifService;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->dashboardRepo = new \App\Repositories\Finance\FinanceDashboardRepository($this->db);
        $this->factureRepo = new FactureRepository($this->db);
        $this->paiementRepo = new PaiementRepository($this->db);
        $this->etatRepo = new EtatJournalierRepository($this->db);
        $this->demandeRepo = new DemandePaiementRepository($this->db);
        $this->comptabiliteRepo = new ComptabiliteRepository($this->db);
        $this->notifService = new NotificationService(new NotificationRepository($this->db));

        // S'assurer que le plan comptable de base est seedé
        try {
            $this->comptabiliteRepo->seedDefaultPlanComptable();
        } catch (\Exception $e) {}
    }

    /**
     * Liste des factures.
     */
    public function facturesIndex(): void
    {
        RoleMiddleware::check(['caissiere', 'caissiere_principale', 'chef_agence', 'dg', 'comptable', 'superviseur_regional', 'superviseur_general']);

        $filters = [
            'q' => $_GET['q'] ?? '',
            'statut' => $_GET['statut'] ?? '',
            'agence_id' => $_GET['agence_id'] ?? '',
        ];

        // Restriction de scope : les utilisateurs locaux ne voient STRICTEMENT QUE les factures de leur agence
        $userAgId = Auth::agenceId();
        $isGlobalRole = Auth::isAdmin() || Auth::hasAnyRole(['caissiere_principale', 'superviseur_general', 'assistant_dg', 'dg', 'comptable']);

        if (!$isGlobalRole && $userAgId !== null && $userAgId > 0) {
            $filters['agence_id'] = $userAgId;
            $factures = $this->factureRepo->getFacturesByAgence((int) $userAgId, $filters);
        } else {
            if (!empty($filters['agence_id'])) {
                $factures = $this->factureRepo->getFacturesByAgence((int) $filters['agence_id'], $filters);
            } else {
                $factures = $this->factureRepo->getFacturesGlobal($filters);
            }
        }

        // Hydrater les jointures colis et clients pour l'affichage
        foreach ($factures as $f) {
            $stmt = $this->db->prepare("SELECT numero_tracking FROM lbp_colis WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $f->colisId]);
            $f->colis_tracking = $stmt->fetchColumn() ?: '';

            $stmt = $this->db->prepare("SELECT name FROM lbp_clients WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $f->clientId]);
            $f->client_name = $stmt->fetchColumn() ?: '';
        }

        $agences = $this->db->query("SELECT id, name FROM company_sites WHERE is_active = 1")->fetchAll() ?: [];

        $this->financeView('finance/factures/index', 'Gestion de la Facturation', 'factures', [
            'factures' => $factures,
            'filters' => $filters,
            'agences' => $agences,
        ]);
    }

    /**
     * Formulaire de création de facture.
     */
    public function factureCreate(): void
    {
        RoleMiddleware::check(['caissiere', 'caissiere_principale', 'chef_agence', 'dg']);

        // Récupérer les colis réceptionnés sans facture dans le scope de l'utilisateur
        $agenceId = Auth::agenceId();
        if (Auth::hasAnyRole(['caissiere_principale', 'superviseur_general', 'assistant_dg', 'dg'])) {
            $stmt = $this->db->query("
                SELECT c.*, cl.name as expediteur_name,
                       (SELECT SUM(m.poids_unitaire * m.quantite) FROM lbp_marchandises m WHERE m.colis_id = c.id) as poids_total,
                       (SELECT SUM(m.total_ligne) FROM lbp_marchandises m WHERE m.colis_id = c.id) as montant_total
                FROM lbp_colis c
                JOIN lbp_clients cl ON c.expediteur_id = cl.id
                WHERE c.id NOT IN (SELECT colis_id FROM lbp_factures)
                ORDER BY c.created_at DESC
            ");
            $colisSansFacture = $stmt->fetchAll() ?: [];
        } else {
            $stmt = $this->db->prepare("
                SELECT c.*, cl.name as expediteur_name,
                       (SELECT SUM(m.poids_unitaire * m.quantite) FROM lbp_marchandises m WHERE m.colis_id = c.id) as poids_total,
                       (SELECT SUM(m.total_ligne) FROM lbp_marchandises m WHERE m.colis_id = c.id) as montant_total
                FROM lbp_colis c
                JOIN lbp_clients cl ON c.expediteur_id = cl.id
                WHERE c.agence_depart_id = :agence_id AND c.id NOT IN (SELECT colis_id FROM lbp_factures)
                ORDER BY c.created_at DESC
            ");
            $stmt->execute(['agence_id' => $agenceId]);
            $colisSansFacture = $stmt->fetchAll() ?: [];
        }

        $this->financeView('finance/factures/create', 'Créer une Facture', 'factures', [
            'colisSansFacture' => $colisSansFacture,
        ]);
    }

    /**
     * Enregistrer une nouvelle facture.
     */
    public function factureStore(): void
    {
        RoleMiddleware::check(['caissiere', 'caissiere_principale', 'chef_agence', 'dg']);

        $colisId = (int) ($_POST['colis_id'] ?? 0);
        $devise = (string) ($_POST['devise'] ?? 'XOF');
        $tauxChange = isset($_POST['taux_change']) && $_POST['taux_change'] !== '' ? (float) $_POST['taux_change'] : null;

        if ($colisId <= 0) {
            Session::flash('error', 'Veuillez sélectionner un colis valide.');
            header('Location: ' . View::url('finance/factures/nouveau'));
            exit;
        }

        // Charger le colis
        $stmt = $this->db->prepare("SELECT * FROM lbp_colis WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $colisId]);
        $colis = $stmt->fetch();

        if (!$colis) {
            Session::flash('error', 'Colis introuvable.');
            header('Location: ' . View::url('finance/factures/nouveau'));
            exit;
        }

        // Calculer le montant total à partir des marchandises
        $stmt = $this->db->prepare("SELECT SUM(total_ligne) FROM lbp_marchandises WHERE colis_id = :colis_id");
        $stmt->execute(['colis_id' => $colisId]);
        $totalXof = (float) $stmt->fetchColumn();

        if ($totalXof <= 0) {
            Session::flash('error', 'Ce colis n\'a aucune marchandise ou son prix total est nul.');
            header('Location: ' . View::url('finance/factures/nouveau'));
            exit;
        }

        $montantTotal = $totalXof;
        if ($devise !== 'XOF') {
            if ($tauxChange === null || $tauxChange <= 0) {
                Session::flash('error', 'Le taux de change est obligatoire pour les devises étrangères.');
                header('Location: ' . View::url('finance/factures/nouveau'));
                exit;
            }
            // Exemple: Si le fret est saisi en XOF (ex: 65595.7 XOF) et qu'on facture en EUR avec un taux de 655.957 XOF/EUR,
            // alors le montant total en EUR = 65595.7 / 655.957 = 100 EUR.
            $montantTotal = $totalXof / $tauxChange;
        }

        $agenceId = (int) $colis['agence_depart_id'];
        $numeroFacture = $this->factureRepo->generateNextInvoiceNumber($agenceId);

        // Date d'échéance à J+7 par défaut
        $dateEcheanceSolde = date('Y-m-d H:i:s', strtotime('+7 days'));

        $facture = new Facture(
            id: null,
            numeroFacture: $numeroFacture,
            colisId: $colisId,
            clientId: (int) $colis['expediteur_id'],
            caissiereId: (int) Auth::id(),
            agenceId: $agenceId,
            montantTotal: $montantTotal,
            montantEncaisse: 0.0,
            montantRestant: $montantTotal,
            devise: $devise,
            tauxChange: $tauxChange,
            statut: 'emise',
            dateEcheanceSolde: $dateEcheanceSolde
        );

        $factureId = $this->factureRepo->create($facture);

        // Log d'audit
        $auditId = AuditLogService::log('create', 'lbp_factures', $factureId, null, (array) $facture);

        // Règle anti-fraude : Cumul de rôles (violation de la séparation des tâches)
        IntegrityRuleEngine::evaluateCumulRoles((int) Auth::id(), $factureId, 'create', $auditId);

        Session::flash('success', "La facture {$numeroFacture} a été générée avec succès.");
        header('Location: ' . View::url('finance/factures/' . $factureId));
        exit;
    }

    /**
     * Suppression d'une facture — réservée aux rôles autorisés (Chef d'agence, Caissière principale, Assistant DG, DG, Admin).
     */
    public function factureDelete(string $id): void
    {
        RoleMiddleware::check(['chef_agence', 'caissiere_principale', 'assistant_dg', 'dg']);

        if (Auth::isAssistantDg()) {
            Session::flash('error', "Action non autorisée : L'Assistant DG dispose de la consultation globale mais ne peut pas effectuer de suppressions.");
            header('Location: ' . View::url('finance/factures'));
            exit;
        }

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide (CSRF). Veuillez réessayer.');
            header('Location: ' . View::url('finance/factures'));
            exit;
        }

        $id = (int) $id;
        $facture = $this->factureRepo->findById($id);

        if (!$facture) {
            Session::flash('error', 'Facture introuvable.');
            header('Location: ' . View::url('finance/factures'));
            exit;
        }

        // Seul le DG ou l'Admin peut supprimer une facture déjà payée
        if (in_array($facture->statut, ['payee', 'partiellement_payee']) && !Auth::hasRole('dg') && !Auth::isAdmin()) {
            Session::flash('error', 'Cette facture est déjà encaissée. Seul le DG ou l\'administrateur peut la supprimer.');
            header('Location: ' . View::url('finance/factures'));
            exit;
        }

        $stmt = $this->db->prepare("DELETE FROM lbp_factures WHERE id = :id");
        $stmt->execute(['id' => $id]);

        AuditLogService::log('delete_invoice', 'lbp_factures', $id, (array) $facture, null);

        Session::flash('success', "La facture {$facture->numeroFacture} a été supprimée avec succès.");
        header('Location: ' . View::url('finance/factures'));
        exit;
    }

    /**
     * Détails d'une facture.
     */
    public function factureShow(string $id): void
    {
        RoleMiddleware::check(['caissiere', 'caissiere_principale', 'chef_agence', 'dg', 'comptable', 'superviseur_regional', 'superviseur_general']);

        $id = (int) $id;
        $facture = $this->factureRepo->findById($id);

        if (!$facture) {
            Session::flash('error', 'Facture introuvable.');
            header('Location: ' . View::url('finance/factures'));
            exit;
        }

        // Vérifier le scope géographique
        if (!Auth::checkAgencyScope($facture->agenceId)) {
            Session::flash('error', 'Accès refusé : Cette facture appartient à une autre agence.');
            header('Location: ' . View::url('finance/factures'));
            exit;
        }

        // Charger colis et client
        $stmt = $this->db->prepare("SELECT * FROM lbp_colis WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $facture->colisId]);
        $colis = $stmt->fetch() ?: [];

        $stmt = $this->db->prepare("SELECT * FROM lbp_clients WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $facture->clientId]);
        $client = $stmt->fetch() ?: [];

        $stmt = $this->db->prepare("SELECT * FROM lbp_marchandises WHERE colis_id = :colis_id");
        $stmt->execute(['colis_id' => $facture->colisId]);
        $marchandises = $stmt->fetchAll() ?: [];

        $clientName = trim((string)($client['name'] ?? ''));
        $clientPhone = trim((string)($client['phone'] ?? ''));
        $clientWalletBalance = 0.0;
        if ($clientName !== '' || $clientPhone !== '') {
            try {
                $stmtW = $this->db->prepare("SELECT solde_xof FROM lbp_client_wallets WHERE (client_nom = :c_name AND :c_name != '') OR (telephone = :c_phone AND :c_phone != '') LIMIT 1");
                $stmtW->execute(['c_name' => $clientName, 'c_phone' => $clientPhone]);
                $clientWalletBalance = (float) ($stmtW->fetchColumn() ?: 0.0);
            } catch (\Throwable $e) {}
        }

        $paiements = $this->paiementRepo->findByFactureId($facture->id);
        $callbacks = $this->paiementRepo->findCallbacksByFactureId($facture->id);

        $this->financeView('finance/factures/show', 'Facture ' . $facture->numeroFacture, 'factures', [
            'facture' => $facture,
            'paiements' => $paiements,
            'callbacks' => $callbacks,
            'colis' => $colis,
            'client' => $client,
            'marchandises' => $marchandises,
            'clientWalletBalance' => $clientWalletBalance,
        ]);
    }

    /**
     * Enregistrer un encaissement physique.
     */
    public function factureEncaisser(string $id): void
    {
        RoleMiddleware::check(['caissiere', 'caissiere_principale', 'chef_agence', 'dg']);

        $id = (int) $id;
        $facture = $this->factureRepo->findById($id);

        if (!$facture) {
            Session::flash('error', 'Facture introuvable.');
            header('Location: ' . View::url('finance/factures'));
            exit;
        }

        if ($facture->statut === 'payee' || $facture->statut === 'annulee') {
            Session::flash('error', 'Cette facture est déjà soldée ou annulée.');
            header('Location: ' . View::url('finance/factures/' . $id));
            exit;
        }

        // Sécurité SoD : l'agent groupage qui a créé le colis ne devrait pas pouvoir encaisser la facture
        $colisCreatorId = 0;
        try {
            $stmt = $this->db->prepare("SELECT created_by FROM lbp_colis WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $facture->colisId]);
            $colisCreatorId = (int) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            $colisCreatorId = 0;
        }

        if ($colisCreatorId === Auth::id() && !Auth::hasRole('chef_agence') && !$facture->devise === 'EUR') {
            Session::flash('error', '🚨 Double contrôle (SoD) : Vous ne pouvez pas encaisser une facture liée à un colis que vous avez vous-même enregistré.');
            header('Location: ' . View::url('finance/factures/' . $id));
            exit;
        }

        $montant = (float) ($_POST['montant'] ?? 0.0);
        $mode = (string) ($_POST['mode'] ?? 'especes');
        $dateEcheance = !empty($_POST['date_echeance_solde']) ? $_POST['date_echeance_solde'] . ' 12:00:00' : null;

        if ($montant <= 0 || $montant > $facture->montantRestant) {
            Session::flash('error', 'Montant d\'encaissement invalide.');
            header('Location: ' . View::url('finance/factures/' . $id));
            exit;
        }

        // Créer l'écriture de paiement
        $paiement = new Paiement(
            id: null,
            factureId: $facture->id,
            caissiereId: Auth::id(),
            montant: $montant,
            devise: $facture->devise,
            mode: $mode,
            type: 'acompte'
        );

        $this->db->beginTransaction();
        try {
            $paiementId = $this->paiementRepo->create($paiement);

            // Générer le reçu
            $numeroRecu = $this->paiementRepo->generateNextRecuNumber($facture->agenceId);
            $recu = new Recu(
                id: null,
                paiementId: $paiementId,
                numeroRecu: $numeroRecu,
                pdfUrl: null
            );
            $this->paiementRepo->createRecu($recu);

            // Mettre à jour la facture
            $oldFacture = (array) $facture;
            $facture->montantEncaisse += $montant;
            $facture->montantRestant = $facture->montantTotal - $facture->montantEncaisse;
            if ($facture->montantRestant <= 0.01) {
                $facture->statut = 'payee';
                $facture->montantRestant = 0.0;
            } else {
                $facture->statut = 'partiellement_payee';
            }
            if ($dateEcheance) {
                $facture->dateEcheanceSolde = $dateEcheance;
            }

            $this->factureRepo->update($facture);

            // Génération de l'écriture comptable automatique (Syscohada)
            // Débit Caisse (571100) et Crédit Clients (411100 ou 411200)
            $compteCredit = $facture->devise === 'EUR' ? '411200' : '411100';
            $ecriture = new EcritureComptable(
                id: null,
                dateEcriture: date('Y-m-d'),
                journal: 'caisses',
                compteDebit: '571100',
                compteCredit: $compteCredit,
                montant: $montant,
                devise: $facture->devise,
                tauxChange: $facture->tauxChange,
                pieceJustificativeId: $numeroRecu,
                libelle: "Encaissement Facture {$facture->numeroFacture} (Reçu: {$numeroRecu})"
            );
            $this->comptabiliteRepo->createEcriture($ecriture);

            // Enregistrer log d'audit
            $auditId = AuditLogService::log('payment', 'lbp_factures', $facture->id, $oldFacture, (array) $facture);

            // Règle anti-fraude : Cumul de rôles (violation de la séparation des tâches)
            IntegrityRuleEngine::evaluateCumulRoles((int) Auth::id(), $facture->id, 'payment', $auditId);

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            Session::flash('error', 'Erreur lors de l\'encaissement : ' . $e->getMessage());
            header('Location: ' . View::url('finance/factures/' . $id));
            exit;
        }

        Session::flash('success', "Encaissement de " . number_format($montant, 2, ',', ' ') . " {$facture->devise} enregistré avec succès.");
        header('Location: ' . View::url('finance/factures/' . $id));
        exit;
    }

    /**
     * Imputation automatique d'un paiement depuis le portefeuille client.
     */
    public function facturePayerPortefeuille(string $id): void
    {
        RoleMiddleware::check(['caissiere', 'caissiere_principale', 'chef_agence', 'dg']);

        $id = (int) $id;
        $facture = $this->factureRepo->findById($id);

        if (!$facture) {
            Session::flash('error', 'Facture introuvable.');
            header('Location: ' . View::url('finance/factures'));
            exit;
        }

        if ($facture->statut === 'payee' || $facture->statut === 'annulee') {
            Session::flash('error', 'Cette facture est déjà soldée ou annulée.');
            header('Location: ' . View::url('finance/factures/' . $id));
            exit;
        }

        $clientStmt = $this->db->prepare("SELECT name, phone FROM lbp_clients WHERE id = :id LIMIT 1");
        $clientStmt->execute(['id' => $facture->clientId]);
        $clientInfo = $clientStmt->fetch() ?: [];

        $cName = trim((string)($clientInfo['name'] ?? ''));
        $cPhone = trim((string)($clientInfo['phone'] ?? ''));

        $pdo = \App\Models\Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM lbp_client_wallets WHERE (client_nom = :c_name AND :c_name != '') OR (telephone = :c_phone AND :c_phone != '') LIMIT 1");
        $stmt->execute(['c_name' => $cName, 'c_phone' => $cPhone]);
        $wallet = $stmt->fetch();

        if (!$wallet || (float)($wallet['solde_xof'] ?? 0) <= 0) {
            Session::flash('error', 'Le portefeuille du client ne dispose pas d\'un solde créditeur suffisant.');
            header('Location: ' . View::url('finance/factures/' . $id));
            exit;
        }

        $soldeDisponible = (float) $wallet['solde_xof'];
        $montantAPayer = min($soldeDisponible, $facture->montantRestant);

        $this->db->beginTransaction();
        try {
            // Déduire du portefeuille
            $stmtDeduct = $pdo->prepare("UPDATE lbp_client_wallets SET solde_xof = solde_xof - :montant, updated_at = NOW() WHERE id = :id");
            $stmtDeduct->execute(['montant' => $montantAPayer, 'id' => $wallet['id']]);

            // Transaction de portefeuille
            $stmtTx = $pdo->prepare("INSERT INTO lbp_client_wallet_transactions (wallet_id, type, montant_xof, mode_paiement, reference_transac, motif) VALUES (:wallet_id, 'DEBIT', :montant, 'Portefeuille Client', :ref, :motif)");
            $stmtTx->execute([
                'wallet_id' => $wallet['id'],
                'montant' => $montantAPayer,
                'ref' => $facture->numeroFacture,
                'motif' => "Imputation automatique sur Facture N° {$facture->numeroFacture}",
            ]);

            // Enregistrer le paiement
            $paiement = new Paiement(
                id: null,
                factureId: $facture->id,
                caissiereId: Auth::id(),
                montant: $montantAPayer,
                devise: $facture->devise,
                mode: 'portefeuille',
                type: ($montantAPayer >= $facture->montantRestant) ? 'solde' : 'acompte'
            );
            $paiementId = $this->paiementRepo->create($paiement);

            // Mettre à jour la facture
            $oldFacture = (array) $facture;
            $facture->montantEncaisse += $montantAPayer;
            $facture->montantRestant = max(0.0, $facture->montantTotal - $facture->montantEncaisse);
            if ($facture->montantRestant <= 0.01) {
                $facture->statut = 'payee';
                $facture->montantRestant = 0.0;
            } else {
                $facture->statut = 'partiellement_payee';
            }

            $this->factureRepo->update($facture);

            AuditLogService::log('wallet_payment', 'lbp_factures', $facture->id, $oldFacture, (array) $facture);

            $this->db->commit();
            Session::flash('success', "Le montant de " . number_format($montantAPayer, 0, ',', ' ') . " XOF a été déduit du portefeuille client et imputé sur la facture.");
        } catch (\Throwable $e) {
            $this->db->rollBack();
            Session::flash('error', 'Erreur lors du règlement via portefeuille : ' . $e->getMessage());
        }

        header('Location: ' . View::url('finance/factures/' . $id));
        exit;
    }

    /**
     * Réinitialiser une facture (annuler tous les encaissements erronés et remettre la facture à l'état ÉMISE).
     */
    public function factureReinitialiser(string $id): void
    {
        RoleMiddleware::check(['caissiere', 'caissiere_principale', 'chef_agence', 'assistant_dg', 'dg']);

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide (CSRF). Veuillez réessayer.');
            header('Location: ' . View::url('finance/factures/' . $id));
            exit;
        }

        $id = (int) $id;
        $facture = $this->factureRepo->findById($id);

        if (!$facture) {
            Session::flash('error', 'Facture introuvable.');
            header('Location: ' . View::url('finance/factures'));
            exit;
        }

        if (!Auth::checkAgencyScope($facture->agenceId)) {
            Session::flash('error', 'Accès refusé : Cette facture appartient à une autre agence.');
            header('Location: ' . View::url('finance/factures'));
            exit;
        }

        $oldFacture = (array) $facture;

        $this->db->beginTransaction();
        try {
            // 1. Supprimer les paiements associés
            $stmtP = $this->db->prepare("DELETE FROM lbp_paiements WHERE facture_id = :id");
            $stmtP->execute(['id' => $id]);

            // 2. Remettre la facture à zéro encaissement et statut 'emise'
            $facture->montantEncaisse = 0.0;
            $facture->montantRestant = $facture->montantTotal;
            $facture->statut = 'emise';
            $this->factureRepo->update($facture);

            // 3. Traçabilité dans l'audit log
            AuditLogService::log('reset_invoice_payments', 'lbp_factures', $id, $oldFacture, (array) $facture);

            $this->db->commit();
            Session::flash('success', "La facture N° {$facture->numeroFacture} a été réinitialisée avec succès à l'état ÉMISE. L'encaissement erroné a été annulé.");
        } catch (\Throwable $e) {
            $this->db->rollBack();
            Session::flash('error', 'Erreur lors de la réinitialisation de la facture : ' . $e->getMessage());
        }

        header('Location: ' . View::url('finance/factures/' . $id));
        exit;
    }

    /**
     * Envoyer un rappel de solde.
     */
    public function factureRelancer(string $id): void
    {
        RoleMiddleware::check(['caissiere', 'caissiere_principale', 'chef_agence', 'dg']);

        $id = (int) $id;
        $facture = $this->factureRepo->findById($id);

        if (!$facture) {
            Session::flash('error', 'Facture introuvable.');
            header('Location: ' . View::url('finance/factures'));
            exit;
        }

        $canal = (string) ($_POST['canal'] ?? 'whatsapp');

        // Charger le client pour avoir son numéro
        $stmt = $this->db->prepare("SELECT * FROM lbp_clients WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $facture->clientId]);
        $client = $stmt->fetch();

        if (!$client || empty($client['phone'])) {
            Session::flash('error', 'Le client n\'a pas de numéro de téléphone valide.');
            header('Location: ' . View::url('finance/factures/' . $id));
            exit;
        }

        $message = sprintf(
            "Cher client %s, nous vous rappelons que votre facture %s présente un solde restant de %s %s. Vous pouvez la régler directement via ce lien sécurisé : %s",
            $client['name'],
            $facture->numeroFacture,
            number_format($facture->montantRestant, 0, ',', ' '),
            $facture->devise,
            View::url('api/paiements/pay/' . $facture->id)
        );

        $sent = $this->notifService->send($client['phone'], $message, $canal);

        if ($sent) {
            // Historiser le rappel
            $stmt = $this->db->prepare("
                INSERT INTO lbp_rappel_soldes (facture_id, caissiere_id, canal, date_rappel)
                VALUES (:facture_id, :caissiere_id, :canal, NOW())
            ");
            $stmt->execute([
                'facture_id' => $facture->id,
                'caissiere_id' => Auth::id(),
                'canal' => $canal,
            ]);

            Session::flash('success', "Relance client envoyée avec succès par " . strtoupper($canal) . ".");
        } else {
            Session::flash('error', "Échec de l'envoi de la relance.");
        }

        header('Location: ' . View::url('finance/factures/' . $id));
        exit;
    }

    /**
     * Relance groupée par SMS/WhatsApp de toutes les factures impayées.
     */
    public function factureRelancerTout(): void
    {
        RoleMiddleware::check(['caissiere', 'caissiere_principale', 'chef_agence', 'dg', 'comptable']);

        $unpaid = $this->factureRepo->getUnpaidFacturesForRelance();
        if (empty($unpaid)) {
            Session::flash('info', 'Aucune facture impayée à relancer pour le moment.');
            header('Location: ' . View::url('finance/factures'));
            exit;
        }

        $count = 0;
        $totalMontant = 0.0;
        $notifService = $this->notifService;

        foreach ($unpaid as $f) {
            $paymentUrl = View::url('api/paiements/pay/' . $f['id']);
            $msg = "Bonjour " . ($f['client_name'] ?? 'Client') . ", votre facture LBP N°" . $f['numero_facture'] . " présente un solde impayé de " . number_format((float)$f['montant_restant'], 0, ',', ' ') . " " . $f['devise'] . ". Réglez votre solde directement en ligne : " . $paymentUrl;

            $sent = $notifService->dispatchPushOrWebhook('PAIEMENT_RAPPEL', [
                'telephone' => $f['client_phone'] ?? '',
                'facture_id' => $f['id'],
                'message' => $msg
            ]);

            if ($sent) {
                $count++;
                $totalMontant += (float) $f['montant_restant'];
            }
        }

        AuditLogService::log('batch_payment_reminders', 'lbp_factures', 0, null, ['count' => $count, 'total' => $totalMontant]);

        Session::flash('success', "📲 Relance automatique envoyée avec succès à {$count} client(s) pour un solde total de " . number_format($totalMontant, 0, ',', ' ') . " XOF.");
        header('Location: ' . View::url('finance/factures'));
        exit;
    }

    /**
     * Dépenses et règlements prestataires.
     */
    public function depensesIndex(): void
    {
        RoleMiddleware::check(['superviseur_regional', 'superviseur_general', 'caissiere_principale', 'dg', 'comptable']);

        $user = Auth::user();
        if (Auth::hasAnyRole(['caissiere_principale', 'superviseur_general', 'dg', 'comptable'])) {
            $demandes = $this->demandeRepo->getDemandesGlobal();
        } else {
            $demandes = $this->demandeRepo->getDemandesBySuperviseur((int) Auth::id());
        }

        // Hydrater le nom des prestataires
        foreach ($demandes as $d) {
            $stmt = $this->db->prepare("SELECT name FROM lbp_prestataires WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $d->prestataireId]);
            $d->prestataire_name = $stmt->fetchColumn() ?: '';
        }

        // Charger prestataires
        $prestataires = $this->demandeRepo->getPrestataires(Auth::zoneRegionaleId());

        $this->financeView('finance/depenses/index', 'Dépenses Prestataires', 'depenses', [
            'demandes' => $demandes,
            'prestataires' => $prestataires,
        ]);
    }

    /**
     * Enregistrer une nouvelle demande de règlement.
     */
    public function depenseStore(): void
    {
        RoleMiddleware::check(['superviseur_regional', 'superviseur_general', 'dg']);

        $prestataireId = (int) ($_POST['prestataire_id'] ?? 0);
        $montant = (float) ($_POST['montant'] ?? 0.0);
        $devise = (string) ($_POST['devise'] ?? 'XOF');
        $motif = (string) ($_POST['motif'] ?? '');
        $justificatifUrl = (string) ($_POST['justificatif_url'] ?? '');

        if ($prestataireId <= 0 || $montant <= 0 || $motif === '') {
            Session::flash('error', 'Informations de paiement invalides.');
            header('Location: ' . View::url('finance/depenses'));
            exit;
        }

        $demande = new DemandePaiement(
            id: null,
            prestataireId: $prestataireId,
            superviseurRegionalId: (int) Auth::id(),
            montant: $montant,
            devise: $devise,
            motif: $motif,
            justificatifUrl: $justificatifUrl !== '' ? $justificatifUrl : null,
            statut: 'en_attente'
        );

        $demandeId = $this->demandeRepo->create($demande);

        AuditLogService::log('create_request', 'lbp_demandes_paiement_prestataires', $demandeId, null, (array) $demande);

        Session::flash('success', 'Votre demande de paiement prestataire a été soumise avec succès.');
        header('Location: ' . View::url('finance/depenses'));
        exit;
    }

    /**
     * Traiter une demande de dépense (Valider/Rejeter).
     */
    public function depenseValider(string $id): void
    {
        RoleMiddleware::check(['caissiere_principale', 'dg']);

        $id = (int) $id;
        $demande = $this->demandeRepo->findById($id);

        if (!$demande) {
            Session::flash('error', 'Demande introuvable.');
            header('Location: ' . View::url('finance/depenses'));
            exit;
        }

        if ($demande->statut !== 'en_attente') {
            Session::flash('error', 'Cette demande a déjà été traitée.');
            header('Location: ' . View::url('finance/depenses'));
            exit;
        }

        // Séparation des tâches (SoD) : Le décideur ne doit pas être l'auteur
        if ($demande->superviseurRegionalId === Auth::id() && !Auth::hasRole('dg')) {
            Session::flash('error', '🚨 Double contrôle (SoD) : Vous ne pouvez pas approuver une demande dont vous êtes l\'auteur.');
            header('Location: ' . View::url('finance/depenses'));
            exit;
        }

        $decision = (string) ($_POST['decision'] ?? '');

        if ($decision !== 'approuver' && $decision !== 'rejeter') {
            Session::flash('error', 'Décision invalide.');
            header('Location: ' . View::url('finance/depenses'));
            exit;
        }

        $oldDemande = (array) $demande;
        $demande->statut = ($decision === 'approuver') ? 'payee' : 'rejetee';
        $demande->caissierePrincipaleId = Auth::id();

        $this->db->beginTransaction();
        try {
            $this->demandeRepo->update($demande);

            if ($decision === 'approuver') {
                // Charger le prestataire pour le libellé
                $stmt = $this->db->prepare("SELECT name FROM lbp_prestataires WHERE id = :id LIMIT 1");
                $stmt->execute(['id' => $demande->prestataireId]);
                $prestName = $stmt->fetchColumn() ?: 'Prestataire';

                // Générer l'écriture comptable automatique
                // Débit Achats (601100) et Crédit Caisse Principale (571200)
                $ecriture = new EcritureComptable(
                    id: null,
                    dateEcriture: date('Y-m-d'),
                    journal: 'achats',
                    compteDebit: '601100',
                    compteCredit: '571200',
                    montant: $demande->montant,
                    devise: $demande->devise,
                    tauxChange: null,
                    pieceJustificativeId: 'DEM-' . $demande->id,
                    libelle: "Règlement prestataire: {$prestName} (Motif: {$demande->motif})"
                );
                $this->comptabiliteRepo->createEcriture($ecriture);
            }

            AuditLogService::log('process_request', 'lbp_demandes_paiement_prestataires', $demande->id, $oldDemande, (array) $demande);

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            Session::flash('error', 'Erreur lors du traitement : ' . $e->getMessage());
            header('Location: ' . View::url('finance/depenses'));
            exit;
        }

        Session::flash('success', "La demande a été " . ($decision === 'approuver' ? "payée et comptabilisée" : "rejetée") . ".");
        header('Location: ' . View::url('finance/depenses'));
        exit;
    }

    /**
     * Points de caisse et états journaliers.
     */
    public function cloturesIndex(): void
    {
        RoleMiddleware::check(['caissiere', 'chef_agence', 'caissiere_principale', 'dg', 'comptable', 'superviseur_general', 'superviseur_regional', 'admin']);

        $userAgenceId = Auth::agenceId();
        $dateJour = date('Y-m-d');
        $isGlobalRole = Auth::hasAnyRole(['caissiere_principale', 'dg', 'comptable', 'superviseur_general', 'superviseur_regional', 'admin']);

        $agences = $this->db->query("SELECT id, name FROM company_sites WHERE is_active = 1 ORDER BY name ASC")->fetchAll() ?: [];

        $selectedAgenceId = isset($_GET['agence_id']) && $_GET['agence_id'] !== '' ? (int) $_GET['agence_id'] : ($userAgenceId ? (int) $userAgenceId : 0);

        $filters = [
            'date_exacte' => $_GET['date_exacte'] ?? '',
            'semaine' => $_GET['semaine'] ?? '',
            'mois' => $_GET['mois'] ?? '',
            'statut' => $_GET['statut'] ?? '',
        ];

        if ($isGlobalRole) {
            $reports = $selectedAgenceId > 0 ? $this->etatRepo->getEtatsByAgence($selectedAgenceId, $filters) : $this->etatRepo->getEtatsGlobal($filters);
        } else {
            $selectedAgenceId = (int) $userAgenceId;
            $reports = $this->etatRepo->getEtatsByAgence($selectedAgenceId, $filters);
        }

        // Déterminer l'agence dont la caisse en direct est affichée
        $targetAgenceId = $selectedAgenceId;
        if ($targetAgenceId === 0 && !empty($agences)) {
            $targetAgenceId = (int) $agences[0]['id'];
        }

        $activeReport = null;
        if ($targetAgenceId > 0) {
            $existing = $this->etatRepo->findByAgenceAndDate($targetAgenceId, $dateJour);
            if ($existing) {
                $activeReport = (array) $existing;
            } else {
                // Calcul en direct pour affichage en temps réel
                $live = $this->etatRepo->computeTotalsForDay($targetAgenceId, $dateJour);
                $activeReport = $live + [
                    'statut' => 'brouillon',
                    'date_jour' => $dateJour,
                    'agence_id' => $targetAgenceId,
                ];
            }

            // Charger le nom de l'agence pour l'en-tête
            $stmt = $this->db->prepare("SELECT name FROM company_sites WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $targetAgenceId]);
            $activeReport['agence_name'] = $stmt->fetchColumn() ?: ('Agence #' . $targetAgenceId);
        }

        $this->financeView('finance/clotures/index', 'Points de Caisse', 'clotures', [
            'reports' => $reports,
            'agences' => $agences,
            'activeReport' => $activeReport,
            'selectedAgenceId' => $selectedAgenceId,
            'filters' => $filters,
        ]);
    }

    /**
     * Soumission du point de caisse (par la caissière/chef d'agence).
     */
    public function clotureSoumettre(): void
    {
        RoleMiddleware::check(['caissiere', 'chef_agence', 'caissiere_principale', 'dg']);

        $agenceId = Auth::agenceId() ?? (!empty($_POST['agence_id']) ? (int) $_POST['agence_id'] : null);
        if ($agenceId === null) {
            Session::flash('error', 'Veuillez sélectionner l\'agence pour laquelle vous soumettez le point de caisse.');
            header('Location: ' . View::url('finance/clotures'));
            exit;
        }

        $dateJour = date('Y-m-d');

        // Vérifier si un état existe déjà pour ce jour
        $existing = $this->etatRepo->findByAgenceAndDate((int) $agenceId, $dateJour);
        if ($existing && $existing->statut !== 'brouillon') {
            Session::flash('error', 'Le point de caisse de ce jour a déjà été soumis.');
            header('Location: ' . View::url('finance/clotures'));
            exit;
        }

        // Calculer les totaux en temps réel
        $live = $this->etatRepo->computeTotalsForDay((int) $agenceId, $dateJour);

        // Récupérer le comptage physique et l'explication éventuelle d'écart
        $soldePhysique = isset($_POST['solde_physique_declare']) && $_POST['solde_physique_declare'] !== '' ? (float) $_POST['solde_physique_declare'] : null;
        $explication = trim((string) ($_POST['explication_ecart'] ?? ''));

        $soldeTheorique = $live['solde_caisse_agence_xof'];
        $ecart = ($soldePhysique !== null) ? round($soldePhysique - $soldeTheorique, 2) : 0.0;

        // Contrôle d'écart obligatoire
        if (abs($ecart) > 0.01 && $explication === '') {
            Session::flash('error', '🚨 Écart de caisse détecté (' . ($ecart > 0 ? '+' : '') . number_format($ecart, 0, ',', ' ') . ' XOF). Une explication détaillée est obligatoirement requise avant de pouvoir soumettre.');
            header('Location: ' . View::url('finance/clotures'));
            exit;
        }

        // Pièce justificative optionnelle pour l'écart de caisse
        $justificatifUrl = null;
        if (!empty($_FILES['justificatif_ecart_file']['name']) && $_FILES['justificatif_ecart_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = BASE_PATH . '/public/uploads/clotures/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $ext = strtolower(pathinfo($_FILES['justificatif_ecart_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'], true)) {
                $filename = 'justificatif_' . $agenceId . '_' . date('Ymd_His') . '.' . $ext;
                if (move_uploaded_file($_FILES['justificatif_ecart_file']['tmp_name'], $uploadDir . $filename)) {
                    $justificatifUrl = '/uploads/clotures/' . $filename;
                }
            }
        }

        if ($existing) {
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
            $existing->soldePhysiqueDeclare = $soldePhysique;
            $existing->ecartCaisse = $ecart;
            $existing->explicationEcart = $explication !== '' ? $explication : null;
            if ($justificatifUrl !== null) {
                $existing->justificatifUrl = $justificatifUrl;
            }
            $existing->statut = 'soumis';
            $existing->dateSoumission = date('Y-m-d H:i:s');
            $existing->chefAgenceId = Auth::id();

            $this->etatRepo->update($existing);
            $reportId = $existing->id;
        } else {
            $etat = new EtatJournalier(
                id: null,
                agenceId: (int) $agenceId,
                chefAgenceId: Auth::id(),
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
                dateSoumission: date('Y-m-d H:i:s'),
                soldePhysiqueDeclare: $soldePhysique,
                ecartCaisse: $ecart,
                explicationEcart: $explication !== '' ? $explication : null,
                justificatifUrl: $justificatifUrl
            );
            $reportId = $this->etatRepo->create($etat);
        }

        AuditLogService::log('submit_cash_report', 'lbp_etats_journaliers', $reportId, null, $live + ['ecart' => $ecart]);

        Session::flash('success', 'Le point de caisse avec rapprochement a été soumis et verrouillé avec succès.');
        header('Location: ' . View::url('finance/clotures'));
        exit;
    }

    /**
     * Consolidation du point de caisse par la caissière principale (Verrouillage central).
     */
    public function clotureConsolider(string $id): void
    {
        RoleMiddleware::check(['caissiere_principale', 'dg']);

        $id = (int) $id;
        $report = $this->etatRepo->findById($id);

        if (!$report) {
            Session::flash('error', 'Point de caisse introuvable.');
            header('Location: ' . View::url('finance/clotures'));
            exit;
        }

        if ($report->statut !== 'soumis') {
            Session::flash('error', 'Ce point de caisse n\'est pas dans un état soumis.');
            header('Location: ' . View::url('finance/clotures'));
            exit;
        }

        // Séparation des tâches (SoD) : La consolidatrice ne doit pas être la caissière qui a soumis le point
        if ($report->chefAgenceId === Auth::id() && !Auth::hasRole('dg')) {
            Session::flash('error', '🚨 Double contrôle (SoD) : Vous ne pouvez pas consolider un point de caisse que vous avez vous-même soumis.');
            header('Location: ' . View::url('finance/clotures'));
            exit;
        }

        $oldReport = (array) $report;
        $report->statut = 'consolide';
        $report->consolideParId = Auth::id();
        $report->dateConsolidation = date('Y-m-d H:i:s');

        $this->db->beginTransaction();
        try {
            $this->etatRepo->update($report);

            // Charger le nom de l'agence pour le libellé
            $stmt = $this->db->prepare("SELECT name FROM company_sites WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $report->agenceId]);
            $agenceName = $stmt->fetchColumn() ?: 'Agence';

            // Écriture de virement de caisse (Caisse agence -> Caisse principale)
            // Débit 571200 (Caisse Principale) et Crédit 585000 (Virement interne)
            if ($report->totalEncaisseXof > 0) {
                $ecriture = new EcritureComptable(
                    id: null,
                    dateEcriture: date('Y-m-d'),
                    journal: 'OD',
                    compteDebit: '571200',
                    compteCredit: '585000',
                    montant: $report->totalEncaisseXof,
                    devise: 'XOF',
                    tauxChange: null,
                    pieceJustificativeId: 'CON-' . $report->id,
                    libelle: "Virement consolidation caisse agence {$agenceName} du {$report->dateJour}"
                );
                $this->comptabiliteRepo->createEcriture($ecriture);
            }

            AuditLogService::log('consolidate_cash_report', 'lbp_etats_journaliers', $report->id, $oldReport, (array) $report);

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            Session::flash('error', 'Erreur lors de la consolidation : ' . $e->getMessage());
            header('Location: ' . View::url('finance/clotures'));
            exit;
        }

        Session::flash('success', "Le point de caisse de l'agence {$agenceName} du {$report->dateJour} a été consolidé.");
        header('Location: ' . View::url('finance/clotures'));
        exit;
    }

    /**
     * Export PDF du Procès-Verbal de clôture de caisse d'une agence.
     */
    public function exportCloturePdf(string $id): void
    {
        AuthMiddleware::check();

        $id = (int) $id;
        $report = $this->etatRepo->findById($id);

        if (!$report) {
            Session::flash('error', 'Point de caisse introuvable.');
            header('Location: ' . View::url('finance/clotures'));
            exit;
        }

        // Seul le chef de cette agence, la caissière principale, le DG ou le comptable peuvent exporter
        $userAgenceId = Auth::user()?->agenceId ?? 0;
        if (!Auth::hasRole(['caissiere_principale', 'dg', 'comptable', 'superviseur_general']) && (int) $userAgenceId !== $report->agenceId) {
            Session::flash('error', 'Accès non autorisé au point de caisse d\'une autre agence.');
            header('Location: ' . View::url('finance/clotures'));
            exit;
        }

        // Nom de l'agence
        $stmt = $this->db->prepare("SELECT name FROM company_sites WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $report->agenceId]);
        $agenceName = $stmt->fetchColumn() ?: 'Agence #' . $report->agenceId;

        // Nom du Chef / Caissier
        $chefName = 'Caissier';
        if ($report->chefAgenceId) {
            $stmt = $this->db->prepare("SELECT full_name FROM users WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $report->chefAgenceId]);
            $chefName = $stmt->fetchColumn() ?: 'Chef d\'Agence';
        }

        // Nom du Consolidateur
        $consolideParName = 'En attente';
        if ($report->consolideParId) {
            $stmt = $this->db->prepare("SELECT full_name FROM users WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $report->consolideParId]);
            $consolideParName = $stmt->fetchColumn() ?: 'Caissière Principale';
        }

        require BASE_PATH . '/views/finance/cloture_pdf.php';
    }

    /**
     * Export PDF du Bilan Global Consolidé Réseau de toutes les agences.
     */
    public function exportClotureGlobalPdf(): void
    {
        AuthMiddleware::check();
        RoleMiddleware::check(['caissiere_principale', 'dg', 'comptable', 'superviseur_general', 'admin']);

        $dateJour = date('Y-m-d');
        $agences = $this->db->query("SELECT id, name FROM company_sites WHERE is_active = 1 ORDER BY name ASC")->fetchAll() ?: [];

        $agenceRows = [];
        $totalEncaisse = 0.0;
        $totalFacture = 0.0;
        $totalRestant = 0.0;
        $totalEcart = 0.0;
        $totalColis = 0;
        $totalFacturesCnt = 0;
        $agencesCloturees = 0;

        foreach ($agences as $ag) {
            $agId = (int) $ag['id'];
            $existing = $this->etatRepo->findByAgenceAndDate($agId, $dateJour);
            $live = $existing ? (array) $existing : $this->etatRepo->computeTotalsForDay($agId, $dateJour);

            $statut = $existing ? $existing->statut : 'brouillon';
            if ($statut === 'soumis' || $statut === 'consolide') {
                $agencesCloturees++;
            }

            $enc = (float) ($live['totalEncaisseXof'] ?? $live['total_encaisse_xof'] ?? 0);
            $fac = (float) ($live['totalFactureXof'] ?? $live['total_facture_xof'] ?? 0);
            $rest = (float) ($live['totalRestantDuXof'] ?? $live['total_restant_du_xof'] ?? 0);
            $ec = (float) ($live['ecartCaisse'] ?? $live['ecart_caisse'] ?? 0);
            $cCnt = (int) ($live['nbColisEnregistres'] ?? $live['nb_colis'] ?? 0);
            $fCnt = (int) ($live['nbFacturesEmises'] ?? $live['nb_factures'] ?? 0);

            $totalEncaisse += $enc;
            $totalFacture += $fac;
            $totalRestant += $rest;
            $totalEcart += $ec;
            $totalColis += $cCnt;
            $totalFacturesCnt += $fCnt;

            $agenceRows[] = [
                'agence_name' => $ag['name'],
                'nb_colis' => $cCnt,
                'nb_factures' => $fCnt,
                'total_facture' => $fac,
                'total_encaisse' => $enc,
                'ecart' => $ec,
                'statut' => $statut,
                'heure_soumission' => !empty($live['dateSoumission']) ? date('H:i', strtotime($live['dateSoumission'])) : null,
            ];
        }

        $summary = [
            'total_encaisse' => $totalEncaisse,
            'total_facture' => $totalFacture,
            'total_restant' => $totalRestant,
            'total_ecart' => $totalEcart,
            'total_colis' => $totalColis,
            'total_factures_cnt' => $totalFacturesCnt,
            'agences_cloturees' => $agencesCloturees,
            'total_agences' => count($agences),
        ];

        require BASE_PATH . '/views/finance/cloture_global_pdf.php';
    }

    /**
     * Export PDF du Bordereau de Remise & Transfert de Caisse.
     */
    public function exportBordereauPdf(string $id): void
    {
        AuthMiddleware::check();

        $id = (int) $id;
        $reportObj = $this->etatRepo->findById($id);

        if (!$reportObj) {
            Session::flash('error', 'Point de caisse introuvable.');
            header('Location: ' . View::url('finance/clotures'));
            exit;
        }

        $report = (array) $reportObj;

        // Nom de l'agence
        $stmt = $this->db->prepare("SELECT name FROM company_sites WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $report['agenceId']]);
        $agenceName = $stmt->fetchColumn() ?: ('Agence #' . $report['agenceId']);

        // Chef d'agence
        $chefName = 'Caissière Agence';
        if (!empty($report['chefAgenceId'])) {
            $stmt = $this->db->prepare("SELECT full_name FROM users WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $report['chefAgenceId']]);
            $chefName = $stmt->fetchColumn() ?: 'Caissière Agence';
        }

        // Consolidateur
        $consolideParName = 'Caissière Principale / Direction';
        if (!empty($report['consolideParId'])) {
            $stmt = $this->db->prepare("SELECT full_name FROM users WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $report['consolideParId']]);
            $consolideParName = $stmt->fetchColumn() ?: 'Caissière Principale';
        }

        require BASE_PATH . '/views/finance/bordereau_remise_pdf.php';
    }

    /**
     * Livre journal et balance comptable.
     */
    public function comptabilite(): void
    {
        RoleMiddleware::check(['comptable', 'dg']);

        $filters = [
            'journal' => $_GET['journal'] ?? '',
            'compte' => $_GET['compte'] ?? '',
            'date_debut' => $_GET['date_debut'] ?? '',
            'date_fin' => $_GET['date_fin'] ?? '',
        ];

        $ecritures = $this->comptabiliteRepo->getEcritures($filters);
        $accounts = $this->comptabiliteRepo->getPlanComptable();

        $this->financeView('finance/comptabilite/index', 'Comptabilité', 'comptabilite', [
            'ecritures' => $ecritures,
            'accounts' => $accounts,
            'filters' => $filters,
        ]);
    }

    /**
     * Rapport de rentabilité (P&L) par trajet / lot de transport.
     */
    public function rentabilite(): void
    {
        RoleMiddleware::check(['comptable', 'dg', 'chef_agence', 'superviseur_general']);

        $stmt = $this->db->query("
            SELECT t.id, t.code, t.libelle, t.type_transport,
                   COALESCE(fac.total_recettes, 0.0) AS total_recettes,
                   COALESCE(dep.total_depenses, 0.0) AS total_depenses
            FROM trajets t
            LEFT JOIN (
                SELECT COALESCE(f.trajet_id, c.trajet_id) AS t_id, SUM(f.montant_total) AS total_recettes
                FROM lbp_factures f
                JOIN lbp_colis c ON f.colis_id = c.id
                GROUP BY t_id
            ) fac ON fac.t_id = t.id
            LEFT JOIN (
                SELECT trajet_id, SUM(montant) AS total_depenses
                FROM lbp_demandes_paiement_prestataires
                WHERE statut = 'validee'
                GROUP BY trajet_id
            ) dep ON dep.trajet_id = t.id
            ORDER BY t.code ASC
        ");

        $rawTrajets = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $totalRecettesGlobal = 0.0;
        $totalDepensesGlobal = 0.0;

        $processedTrajets = array_map(static function(array $r) use (&$totalRecettesGlobal, &$totalDepensesGlobal): array {
            $recettes = (float) ($r['total_recettes'] ?? 0.0);
            $depenses = (float) ($r['total_depenses'] ?? 0.0);
            $margeNette = $recettes - $depenses;
            $tauxMarge = $recettes > 0 ? ($margeNette / $recettes) * 100.0 : 0.0;

            $totalRecettesGlobal += $recettes;
            $totalDepensesGlobal += $depenses;

            return array_merge($r, [
                'total_recettes' => $recettes,
                'total_depenses' => $depenses,
                'marge_nette' => $margeNette,
                'taux_marge' => $tauxMarge,
            ]);
        }, $rawTrajets);

        $margeNetteGlobale = $totalRecettesGlobal - $totalDepensesGlobal;
        $tauxMargeGlobal = $totalRecettesGlobal > 0 ? ($margeNetteGlobale / $totalRecettesGlobal) * 100.0 : 0.0;

        $page = new \App\View\Pages\Finance\RentabilitePage(
            $processedTrajets,
            [
                'total_recettes' => $totalRecettesGlobal,
                'total_depenses' => $totalDepensesGlobal,
                'marge_nette' => $margeNetteGlobale,
                'taux_marge' => $tauxMargeGlobal,
            ],
            Session::getFlash('success'),
            Session::getFlash('error')
        );

        $this->financeView('finance/rentabilite', 'Rentabilité par Trajet (P&L)', 'comptabilite', [
            'page' => $page,
        ]);
    }

    /**
     * Balance Âgée des Créances (Aging Balance des factures impayées).
     */
    public function balanceAgee(): void
    {
        RoleMiddleware::check(['comptable', 'dg', 'chef_agence', 'superviseur_general']);

        $sql = "
            SELECT f.id, f.numero_facture, f.date_emission, f.montant_total, f.montant_restant, f.devise,
                   c.name AS client_name, c.phone AS client_phone,
                   DATEDIFF(NOW(), f.date_emission) AS jours_anciente
            FROM lbp_factures f
            JOIN lbp_clients c ON f.client_id = c.id
            WHERE f.statut IN ('emise', 'partiellement_payee') AND f.montant_restant > 0
            ORDER BY jours_anciente DESC
        ";

        $stmt = $this->db->query($sql);
        $factures = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $bucket30 = 0.0;
        $bucket60 = 0.0;
        $bucket90 = 0.0;
        $bucketPlus90 = 0.0;

        $clientMap = [];

        foreach ($factures as $f) {
            $restant = (float) $f['montant_restant'];
            $days = (int) $f['jours_anciente'];
            $clientName = (string) $f['client_name'];

            if (!isset($clientMap[$clientName])) {
                $clientMap[$clientName] = [
                    'client_name' => $clientName,
                    'phone' => (string) ($f['client_phone'] ?? ''),
                    'b30' => 0.0,
                    'b60' => 0.0,
                    'b90' => 0.0,
                    'bPlus90' => 0.0,
                    'total' => 0.0,
                ];
            }

            if ($days <= 30) {
                $bucket30 += $restant;
                $clientMap[$clientName]['b30'] += $restant;
            } elseif ($days <= 60) {
                $bucket60 += $restant;
                $clientMap[$clientName]['b60'] += $restant;
            } elseif ($days <= 90) {
                $bucket90 += $restant;
                $clientMap[$clientName]['b90'] += $restant;
            } else {
                $bucketPlus90 += $restant;
                $clientMap[$clientName]['bPlus90'] += $restant;
            }

            $clientMap[$clientName]['total'] += $restant;
        }

        $page = new \App\View\Pages\Finance\BalanceAgeePage(
            [
                'b30' => $bucket30,
                'b60' => $bucket60,
                'b90' => $bucket90,
                'bPlus90' => $bucketPlus90,
                'total' => $bucket30 + $bucket60 + $bucket90 + $bucketPlus90,
            ],
            array_values($clientMap),
            Session::getFlash('success'),
            Session::getFlash('error')
        );

        $this->financeView('finance/balance_agee', 'Balance Âgée des Créances', 'factures', [
            'page' => $page,
        ]);
    }

    /**
     * Export des écritures au format SYSCOHADA (CSV pour Sage / Odoo / Cegid).
     */
    public function exportSyscohada(): void
    {
        RoleMiddleware::check(['comptable', 'dg']);

        $ecritures = $this->comptabiliteRepo->getEcritures([]);

        $filename = 'export_ecritures_syscohada_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $output = fopen('php://output', 'w');
        if ($output) {
            // UTF-8 BOM pour Excel
            fwrite($output, "\xEF\xBB\xBF");
            // Entête CSV SYSCOHADA
            fputcsv($output, ['Date', 'Journal', 'Compte Débit', 'Compte Crédit', 'Pièce Justificative', 'Libellé', 'Montant', 'Devise'], ';');

            foreach ($ecritures as $e) {
                fputcsv($output, [
                    $e->dateEcriture,
                    $e->journal,
                    $e->compteDebit,
                    $e->compteCredit,
                    $e->pieceJustificativeId ?? '',
                    $e->libelle,
                    number_format($e->montant, 2, '.', ''),
                    $e->devise,
                ], ';');
            }

            fclose($output);
        }
        exit;
    }

    public function exportRentabilitePdf(): void
    {
        AuthMiddleware::check();
        RoleMiddleware::check(['comptable', 'dg', 'chef_agence', 'superviseur_general']);

        $stmt = $this->db->query("
            SELECT t.id, t.code, t.libelle, t.type_transport,
                   COALESCE(fac.total_recettes, 0.0) AS total_recettes,
                   COALESCE(dep.total_depenses, 0.0) AS total_depenses
            FROM trajets t
            LEFT JOIN (
                SELECT COALESCE(f.trajet_id, c.trajet_id) AS t_id, SUM(f.montant_total) AS total_recettes
                FROM lbp_factures f
                JOIN lbp_colis c ON f.colis_id = c.id
                GROUP BY t_id
            ) fac ON fac.t_id = t.id
            LEFT JOIN (
                SELECT trajet_id, SUM(montant) AS total_depenses
                FROM lbp_demandes_paiement_prestataires
                WHERE statut = 'validee'
                GROUP BY trajet_id
            ) dep ON dep.trajet_id = t.id
            ORDER BY t.code ASC
        ");

        $rawTrajets = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $totalRecettesGlobal = 0.0;
        $totalDepensesGlobal = 0.0;

        $trajets = array_map(static function(array $r) use (&$totalRecettesGlobal, &$totalDepensesGlobal): array {
            $recettes = (float) ($r['total_recettes'] ?? 0.0);
            $depenses = (float) ($r['total_depenses'] ?? 0.0);
            $margeNette = $recettes - $depenses;
            $tauxMarge = $recettes > 0 ? ($margeNette / $recettes) * 100.0 : 0.0;

            $totalRecettesGlobal += $recettes;
            $totalDepensesGlobal += $depenses;

            return array_merge($r, [
                'total_recettes' => $recettes,
                'total_depenses' => $depenses,
                'marge_nette' => $margeNette,
                'taux_marge' => $tauxMarge,
            ]);
        }, $rawTrajets);

        $margeNetteGlobale = $totalRecettesGlobal - $totalDepensesGlobal;
        $tauxMargeGlobal = $totalRecettesGlobal > 0 ? ($margeNetteGlobale / $totalRecettesGlobal) * 100.0 : 0.0;

        $summary = [
            'total_recettes' => $totalRecettesGlobal,
            'total_depenses' => $totalDepensesGlobal,
            'marge_nette' => $margeNetteGlobale,
            'taux_marge' => $tauxMargeGlobal,
        ];

        require BASE_PATH . '/views/finance/rentabilite_pdf.php';
    }

    public function exportBalanceAgeePdf(): void
    {
        AuthMiddleware::check();
        RoleMiddleware::check(['comptable', 'dg', 'chef_agence', 'superviseur_general']);

        $sql = "
            SELECT f.id, f.numero_facture, f.date_emission, f.montant_total, f.montant_restant, f.devise,
                   c.name AS client_name, c.phone AS client_phone,
                   DATEDIFF(NOW(), f.date_emission) AS jours_anciente
            FROM lbp_factures f
            JOIN lbp_clients c ON f.client_id = c.id
            WHERE f.statut IN ('emise', 'partiellement_payee') AND f.montant_restant > 0
            ORDER BY jours_anciente DESC
        ";

        $stmt = $this->db->query($sql);
        $factures = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $bucket30 = 0.0;
        $bucket60 = 0.0;
        $bucket90 = 0.0;
        $bucketPlus90 = 0.0;
        $clientMap = [];

        foreach ($factures as $f) {
            $restant = (float) $f['montant_restant'];
            $days = (int) $f['jours_anciente'];
            $clientName = (string) $f['client_name'];

            if (!isset($clientMap[$clientName])) {
                $clientMap[$clientName] = [
                    'client_name' => $clientName,
                    'phone' => (string) ($f['client_phone'] ?? ''),
                    'b30' => 0.0,
                    'b60' => 0.0,
                    'b90' => 0.0,
                    'bPlus90' => 0.0,
                    'total' => 0.0,
                ];
            }

            if ($days <= 30) {
                $bucket30 += $restant;
                $clientMap[$clientName]['b30'] += $restant;
            } elseif ($days <= 60) {
                $bucket60 += $restant;
                $clientMap[$clientName]['b60'] += $restant;
            } elseif ($days <= 90) {
                $bucket90 += $restant;
                $clientMap[$clientName]['b90'] += $restant;
            } else {
                $bucketPlus90 += $restant;
                $clientMap[$clientName]['bPlus90'] += $restant;
            }

            $clientMap[$clientName]['total'] += $restant;
        }

        $clientDetails = array_values($clientMap);

        $agingBuckets = [
            'b30' => $bucket30,
            'b60' => $bucket60,
            'b90' => $bucket90,
            'bPlus90' => $bucketPlus90,
            'total' => $bucket30 + $bucket60 + $bucket90 + $bucketPlus90,
        ];
        require BASE_PATH . '/views/finance/balance_agee_pdf.php';
    }

    public function portefeuillesIndex(): void
    {
        RoleMiddleware::check(['caissiere', 'caissiere_principale', 'chef_agence', 'dg', 'comptable', 'superviseur_regional', 'superviseur_general']);

        $pdo = \App\Models\Database::getConnection();
        $wallets = $pdo->query("SELECT * FROM lbp_client_wallets ORDER BY updated_at DESC, created_at DESC")->fetchAll(\PDO::FETCH_ASSOC);
        $recentTx = $pdo->query("SELECT t.*, w.client_nom FROM lbp_client_wallet_transactions t JOIN lbp_client_wallets w ON t.wallet_id = w.id ORDER BY t.created_at DESC LIMIT 20")->fetchAll(\PDO::FETCH_ASSOC);

        $dashService = new \App\Services\Shared\ModuleDashboardService();
        $module = $dashService->dashboard('finance');

        $this->financeView(
            'finance/portefeuilles',
            'Portefeuilles Clients & Acomptes - Finance',
            'portefeuilles',
            $module,
            [
                'wallets' => $wallets,
                'recentTx' => $recentTx,
            ]
        );
    }

    public function portefeuilleCrediter(): void
    {
        RoleMiddleware::check(['caissiere', 'caissiere_principale', 'chef_agence', 'dg', 'comptable']);

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide (CSRF).');
            header('Location: ' . View::url('finance/portefeuilles'));
            exit;
        }

        $walletId = (int)($_POST['wallet_id'] ?? 0);
        $montant = (float)($_POST['montant_xof'] ?? 0.0);
        $modePaiement = trim((string)($_POST['mode_paiement'] ?? 'Espèces'));
        $refTransac = trim((string)($_POST['reference_transac'] ?? ''));
        $motif = trim((string)($_POST['motif'] ?? 'Acompte / Avance sur expédition'));

        if ($walletId <= 0 || $montant <= 0) {
            Session::flash('error', 'Le montant du crédit et le client doivent être valides.');
            header('Location: ' . View::url('finance/portefeuilles'));
            exit;
        }

        $pdo = \App\Models\Database::getConnection();
        $stmt = $pdo->prepare("UPDATE lbp_client_wallets SET solde_xof = solde_xof + :montant, updated_at = NOW() WHERE id = :id");
        $stmt->execute(['montant' => $montant, 'id' => $walletId]);

        $stmtTx = $pdo->prepare("INSERT INTO lbp_client_wallet_transactions (wallet_id, type, montant_xof, mode_paiement, reference_transac, motif) VALUES (:wallet_id, 'AVANCE', :montant, :mode, :ref, :motif)");
        $stmtTx->execute([
            'wallet_id' => $walletId,
            'montant' => $montant,
            'mode' => $modePaiement,
            'ref' => $refTransac,
            'motif' => $motif,
        ]);

        Session::flash('success', "Le portefeuille client a été crédité avec succès de " . number_format($montant, 0, ',', ' ') . " XOF.");
        header('Location: ' . View::url('finance/portefeuilles'));
        exit;
    }

    public function coutsApprocheIndex(): void
    {
        RoleMiddleware::check(['caissiere', 'caissiere_principale', 'chef_agence', 'dg', 'comptable', 'superviseur_regional', 'superviseur_general']);

        $pdo = \App\Models\Database::getConnection();
        $landedCosts = $pdo->query("SELECT * FROM lbp_landed_costs ORDER BY created_at DESC")->fetchAll(\PDO::FETCH_ASSOC);
        $trajets = $pdo->query("SELECT code, libelle FROM trajets ORDER BY code")->fetchAll(\PDO::FETCH_ASSOC);

        $dashService = new \App\Services\Shared\ModuleDashboardService();
        $module = $dashService->dashboard('finance');

        $this->financeView(
            'finance/couts_approche',
            'Ventilation des Coûts d\'Approche (Landed Costs) - Finance',
            'couts_approche',
            $module,
            [
                'landedCosts' => $landedCosts,
                'trajets' => $trajets,
            ]
        );
    }

    public function coutsApprocheCalculer(): void
    {
        RoleMiddleware::check(['caissiere', 'caissiere_principale', 'chef_agence', 'dg', 'comptable']);

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide (CSRF).');
            header('Location: ' . View::url('finance/couts-approche'));
            exit;
        }

        $refLot = trim((string)($_POST['reference_lot'] ?? ''));
        $trajetCode = trim((string)($_POST['trajet_code'] ?? 'LB-FR'));
        $fraisDouane = (float)($_POST['frais_douane_xof'] ?? 0.0);
        $fraisFret = (float)($_POST['frais_fret_xof'] ?? 0.0);
        $fraisManutention = (float)($_POST['frais_manutention_xof'] ?? 0.0);
        $poidsTotal = (float)($_POST['poids_total_kg'] ?? 1.0);

        if (empty($refLot) || $poidsTotal <= 0) {
            Session::flash('error', 'La référence du lot et le poids total en kg sont obligatoires.');
            header('Location: ' . View::url('finance/couts-approche'));
            exit;
        }

        $totalCouts = $fraisDouane + $fraisFret + $fraisManutention;
        $coutParKg = $totalCouts / max($poidsTotal, 0.1);

        $pdo = \App\Models\Database::getConnection();
        $stmt = $pdo->prepare("INSERT INTO lbp_landed_costs (reference_lot, trajet_code, frais_douane_xof, frais_fret_xof, frais_manutention_xof, poids_total_kg, cout_par_kg_xof, statut) VALUES (:ref, :trajet, :douane, :fret, :manut, :poids, :cout_kg, 'VALIDÉ')");
        $stmt->execute([
            'ref' => $refLot,
            'trajet' => $trajetCode,
            'douane' => $fraisDouane,
            'fret' => $fraisFret,
            'manut' => $fraisManutention,
            'poids' => $poidsTotal,
            'cout_kg' => $coutParKg,
        ]);

        Session::flash('success', "Ventilation effectuée : Coût d'approche = " . number_format($coutParKg, 2, '.', ' ') . " XOF / kg pour le lot {$refLot}.");
        header('Location: ' . View::url('finance/couts-approche'));
        exit;
    }

    public function rapprochementMobileMoneyIndex(): void
    {
        RoleMiddleware::check(['caissiere', 'caissiere_principale', 'chef_agence', 'dg', 'comptable', 'superviseur_regional', 'superviseur_general']);

        $pdo = \App\Models\Database::getConnection();
        $reconciliations = $pdo->query("SELECT * FROM lbp_mobile_money_reconciliations ORDER BY date_transaction DESC")->fetchAll(\PDO::FETCH_ASSOC);

        $dashService = new \App\Services\Shared\ModuleDashboardService();
        $module = $dashService->dashboard('finance');

        $this->financeView(
            'finance/rapprochement_mobile_money',
            'Rapprochement Mobile Money & Banque - Finance',
            'rapprochement',
            $module,
            [
                'reconciliations' => $reconciliations,
            ]
        );
    }

    public function rapprochementMobileMoneyValider(): void
    {
        RoleMiddleware::check(['caissiere', 'caissiere_principale', 'chef_agence', 'dg', 'comptable']);

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide (CSRF).');
            header('Location: ' . View::url('finance/rapprochement-mobile-money'));
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $statut = (string)($_POST['statut'] ?? 'RAPPROCHÉ');

        if ($id <= 0) {
            Session::flash('error', 'Identifiant de transaction invalide.');
            header('Location: ' . View::url('finance/rapprochement-mobile-money'));
            exit;
        }

        $pdo = \App\Models\Database::getConnection();
        $stmt = $pdo->prepare("UPDATE lbp_mobile_money_reconciliations SET statut = :statut WHERE id = :id");
        $stmt->execute(['statut' => $statut, 'id' => $id]);

        Session::flash('success', "La transaction a été marquée comme {$statut}.");
        header('Location: ' . View::url('finance/rapprochement-mobile-money'));
        exit;
    }

    public function tresorerieIndex(): void
    {
        RoleMiddleware::check(['caissiere', 'caissiere_principale', 'chef_agence', 'dg', 'comptable', 'superviseur_regional', 'superviseur_general']);

        $pdo = \App\Models\Database::getConnection();
        
        // Encaissements attendus (Balance âgée factures emises/partiellement payees)
        $stmtEnc = $pdo->query("SELECT SUM(montant_restant) FROM lbp_factures WHERE statut IN ('emise', 'partiellement_payee')");
        $totalEncaissementsPrevus = (float)($stmtEnc ? $stmtEnc->fetchColumn() : 0.0);

        // Décaissements prévus (Prestataires en attente de paiement)
        $stmtDec = $pdo->query("SELECT SUM(montant) FROM lbp_demandes_paiement_prestataires WHERE LOWER(statut) = 'en_attente'");
        $totalDecaissementsPrevus = (float)($stmtDec ? $stmtDec->fetchColumn() : 0.0);

        // Solde estimé de trésorerie nette
        $soldeTrésorerieEstime = $totalEncaissementsPrevus - $totalDecaissementsPrevus;

        $dashService = new \App\Services\Shared\ModuleDashboardService();
        $module = $dashService->dashboard('finance');

        $this->financeView(
            'finance/tresorerie',
            'Trésorerie Prévisionnelle & Cashflow (30/60/90j) - Finance',
            'tresorerie',
            $module,
            [
                'totalEncaissementsPrevus' => $totalEncaissementsPrevus,
                'totalDecaissementsPrevus' => $totalDecaissementsPrevus,
                'soldeTrésorerieEstime' => $soldeTrésorerieEstime,
            ]
        );
    }

    public function exportRecuPdf(string $id): void
    {
        RoleMiddleware::check(['caissiere', 'caissiere_principale', 'chef_agence', 'dg', 'comptable', 'superviseur_regional', 'superviseur_general', 'admin', 'agent']);

        $id = (int) $id;
        $facture = null;
        $paiement = $this->paiementRepo->findById($id);

        if ($paiement && $paiement->factureId) {
            $facture = $this->factureRepo->findById($paiement->factureId);
        }

        if (!$facture) {
            $facture = $this->factureRepo->findById($id);
        }

        if (!$facture) {
            Session::flash('error', 'Facture ou reçu introuvable.');
            header('Location: ' . View::url('finance/factures'));
            exit;
        }

        $colisService = new \App\Services\Colisage\ColisageService(new \App\Repositories\Colisage\ColisageRepository($this->db));
        $colis = $colisService->getParcelDetails($facture->colisId);

        if (!$colis) {
            Session::flash('error', 'Colis associé à cette facture introuvable.');
            header('Location: ' . View::url('finance/factures'));
            exit;
        }

        require BASE_PATH . '/views/colisage/parcels/facture.php';
    }
}
