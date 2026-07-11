<?php

declare(strict_types=1);

namespace App\Controllers\Finance;

use App\Controllers\BaseController;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\Response;
use App\Helpers\View;
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
use App\Services\Shared\NotificationService;
use PDO;

final class FinanceController extends BaseController
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
        $this->factureRepo = new FactureRepository($this->db);
        $this->paiementRepo = new PaiementRepository($this->db);
        $this->etatRepo = new EtatJournalierRepository($this->db);
        $this->demandeRepo = new DemandePaiementRepository($this->db);
        $this->comptabiliteRepo = new ComptabiliteRepository($this->db);
        $this->notifService = new NotificationService();

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

        // Restriction de scope
        if (!Auth::hasAnyRole(['caissiere_principale', 'superviseur_general', 'assistant_dg', 'dg', 'comptable'])) {
            // Rôles locaux
            if (Auth::hasRole('superviseur_regional')) {
                // Doit filtrer par sa région. Pour simplifier, on récupère les agences de sa région
                $user = Auth::user();
                $regionId = $user->zoneRegionaleId;
                if ($regionId !== null) {
                    $stmt = $this->db->prepare("SELECT id FROM company_sites WHERE zone_regionale_id = :region_id");
                    $stmt->execute(['region_id' => $regionId]);
                    $siteIds = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [-1];
                    $factures = [];
                    foreach ($siteIds as $siteId) {
                        $filters['agence_id'] = $siteId;
                        $factures = array_merge($factures, $this->factureRepo->getFacturesGlobal($filters));
                    }
                } else {
                    $factures = [];
                }
            } else {
                // Agent local
                $factures = $this->factureRepo->getFacturesByAgence((int) Auth::agenceId(), $filters);
            }
        } else {
            $factures = $this->factureRepo->getFacturesGlobal($filters);
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

        $nav = $this->viewData();
        $nav['activeModule'] = 'factures';

        $this->view('finance/factures/index', $nav + [
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

        $nav = $this->viewData();
        $nav['activeModule'] = 'factures';

        $this->view('finance/factures/create', $nav + [
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
        AuditLogService::log('create', 'lbp_factures', $factureId, null, (array) $facture);

        Session::flash('success', "La facture {$numeroFacture} a été générée avec succès.");
        header('Location: ' . View::url('finance/factures/' . $factureId));
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

        $paiements = $this->paiementRepo->findByFactureId($facture->id);
        $callbacks = $this->paiementRepo->findCallbacksByFactureId($facture->id);

        $nav = $this->viewData();
        $nav['activeModule'] = 'factures';

        $this->view('finance/factures/show', $nav + [
            'facture' => $facture,
            'paiements' => $paiements,
            'callbacks' => $callbacks,
            'colis' => $colis,
            'client' => $client,
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
        $stmt = $this->db->prepare("SELECT created_by FROM lbp_colis WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $facture->colisId]);
        $colisCreatorId = (int) $stmt->fetchColumn();

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
            AuditLogService::log('payment', 'lbp_factures', $facture->id, $oldFacture, (array) $facture);

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            Session::flash('error', 'Erreur lors de l\'encaissement : ' . $e->getMessage());
            header('Location: ' . View::url('finance/factures/' . $id));
            exit;
        }

        Session::flash('success', "Encaissement de " . number_format($montant, 2, ',', ' ') . " {$facture->devise} validé.");
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

        $nav = $this->viewData();
        $nav['activeModule'] = 'depenses';

        $this->view('finance/depenses/index', $nav + [
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
        RoleMiddleware::check(['caissiere', 'chef_agence', 'caissiere_principale', 'dg', 'comptable']);

        $agenceId = Auth::agenceId();
        $dateJour = date('Y-m-d');

        if (Auth::hasAnyRole(['caissiere_principale', 'dg', 'comptable'])) {
            $reports = $this->etatRepo->getEtatsGlobal();
        } else {
            $reports = $this->etatRepo->getEtatsByAgence((int) $agenceId);
        }

        // Pour la caissière connectée, calculer en temps réel l'état du jour actuel
        $activeReport = null;
        if ($agenceId !== null) {
            $existing = $this->etatRepo->findByAgenceAndDate((int) $agenceId, $dateJour);
            if ($existing) {
                $activeReport = (array) $existing;
            } else {
                // Calcul en direct pour affichage
                $live = $this->etatRepo->computeTotalsForDay((int) $agenceId, $dateJour);
                if ($live['nb_colis'] > 0 || $live['nb_factures'] > 0 || $live['total_encaisse_xof'] > 0) {
                    $activeReport = $live + [
                        'statut' => 'brouillon',
                        'date_jour' => $dateJour,
                    ];
                }
            }
        }

        $agences = $this->db->query("SELECT id, name FROM company_sites WHERE is_active = 1")->fetchAll() ?: [];

        $nav = $this->viewData();
        $nav['activeModule'] = 'clotures';

        $this->view('finance/clotures/index', $nav + [
            'reports' => $reports,
            'agences' => $agences,
            'activeReport' => $activeReport,
        ]);
    }

    /**
     * Soumission du point de caisse (par la caissière/chef d'agence).
     */
    public function clotureSoumettre(): void
    {
        RoleMiddleware::check(['caissiere', 'chef_agence', 'dg']);

        $agenceId = Auth::agenceId();
        if ($agenceId === null) {
            Session::flash('error', 'Vous n\'êtes affecté à aucune agence.');
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
                dateSoumission: date('Y-m-d H:i:s')
            );
            $reportId = $this->etatRepo->create($etat);
        }

        AuditLogService::log('submit_cash_report', 'lbp_etats_journaliers', $reportId, null, $live);

        Session::flash('success', 'Le point de caisse a été soumis et verrouillé avec succès.');
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

        $nav = $this->viewData();
        $nav['activeModule'] = 'comptabilite';

        $this->view('finance/comptabilite/index', $nav + [
            'ecritures' => $ecritures,
            'accounts' => $accounts,
            'filters' => $filters,
        ]);
    }

    /**
     * Prépare le menu de navigation et les styles pour les pages Finance.
     */
    private function viewData(): array
    {
        return [
            'pageTitle' => 'Gestion Financière',
            'moduleName' => 'Finance',
            'moduleCode' => 'FIN',
            'moduleTheme' => [
                'accent' => '#2563eb',
                'accent2' => '#1d2b57',
                'gradient' => 'linear-gradient(135deg, #1d2b57, #2563eb)',
            ],
            'moduleNavigation' => [
                ['key' => 'dashboard', 'label' => 'Tableau de bord', 'icon' => 'DB', 'url' => '/finance/dashboard', 'available' => true],
                ['key' => 'factures', 'label' => 'Factures Clients', 'icon' => 'FAC', 'url' => '/finance/factures', 'available' => true],
                ['key' => 'clotures', 'label' => 'Points de Caisse', 'icon' => 'CLT', 'url' => '/finance/clotures', 'available' => true],
                ['key' => 'depenses', 'label' => 'Dépenses Prestataires', 'icon' => 'DEP', 'url' => '/finance/depenses', 'available' => true],
                ['key' => 'comptabilite', 'label' => 'Comptabilité', 'icon' => 'CPT', 'url' => '/finance/comptabilite', 'available' => Auth::hasAnyRole(['comptable', 'dg'])],
            ],
            'additionalStyles' => ['css/finea-ui.css', 'css/finance.css'],
        ];
    }
}
