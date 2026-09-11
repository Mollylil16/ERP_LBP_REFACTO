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
    /**
     * Modes de règlement acceptés au guichet, alignés sur l'ENUM `lbp_paiements.mode`
     * et sur les options proposées par le formulaire d'encaissement.
     * 'portefeuille' en est volontairement exclu : il est réservé au règlement
     * automatique par portefeuille client, jamais saisi à la main.
     */
    private const MODES_ENCAISSEMENT_GUICHET = ['especes', 'mobile_money', 'carte', 'virement', 'cheque'];

    /**
     * Roles qui voient le cumul de l'agence dans les points de caisse : leurs propres
     * operations additionnees a celles de tous les autres agents.
     *
     * Tout autre utilisateur est restreint a ce qu'il a lui-meme facture
     * (lbp_factures.created_by) et encaisse (lbp_paiements.caissiere_id).
     *
     * Attention : cette liste couvre aujourd'hui tous les roles que le middleware de
     * cloturesIndex laisse entrer sur la page. Aucun utilisateur n'est donc restreint
     * en pratique. Retirer un role d'ici suffit a le basculer sur son seul perimetre.
     */
    private const ROLES_CUMUL_AGENCE = [
        'caissiere_principale',
        'chef_agence',
        'caissiere',
        'dg',
        'assistant_dg',
        'assistante_dg',
        'comptable',
        'superviseur_general',
        'superviseur_regional',
        'admin',
    ];

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
        RoleMiddleware::check(['caissiere', 'caissiere_principale', 'chef_agence', 'dg', 'comptable', 'superviseur_regional', 'superviseur_general', 'suivi_recouvrement']);

        $userAgId = Auth::agenceId();
        $selectedAgence = $_GET['agence_id'] ?? null;
        $typeEnvoi = trim((string) ($_GET['type_envoi'] ?? ''));

        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'statut' => trim((string) ($_GET['statut'] ?? '')),
            'agence_id' => $selectedAgence !== null ? (string)$selectedAgence : '',
            'type_envoi' => $typeEnvoi,
        ];

        $isGlobalRole = Auth::isAdmin() || Auth::isAssistantDg() || Auth::hasAnyRole(['caissiere_principale', 'dg', 'assistant_dg', 'assistante_dg', 'comptable', 'superviseur_general']);

        // Par défaut, si l'utilisateur a un rôle local et qu'aucun filtre d'agence n'est dans l'URL, limiter à son agence
        if (!$isGlobalRole && $selectedAgence === null && $userAgId !== null && $userAgId > 0) {
            $filters['agence_id'] = (string) $userAgId;
        }

        if (!empty($filters['agence_id']) && $filters['agence_id'] !== 'all' && (int)$filters['agence_id'] > 0) {
            $factures = $this->factureRepo->getFacturesByAgence((int) $filters['agence_id'], $filters);
        } else {
            $factures = $this->factureRepo->getFacturesGlobal($filters);
        }

        // Pagination: 25 factures par page
        $perPage = 25;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $totalItems = count($factures);
        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $pagedFactures = array_slice($factures, $offset, $perPage);

        // Hydrater les jointures colis et clients pour l'affichage
        // Deux requetes groupees, et non deux par facture : sur une page de vingt-cinq
        // lignes, cinquante allers-retours devenaient deux.
        $this->hydraterFactures($pagedFactures);

        $agences = $this->db->query("SELECT id, name FROM company_sites WHERE is_active = 1")->fetchAll() ?: [];
        $categoryStats = $this->factureRepo->getCategoryStats($filters);

        $this->financeView('finance/factures/index', 'Gestion de la Facturation', 'factures', [
            'factures' => $pagedFactures,
            'filters' => $filters,
            'agences' => $agences,
            'categoryStats' => $categoryStats,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'itemsPerPage' => $perPage,
                'totalItems' => $totalItems,
            ],
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
        if (Auth::isAdmin() || Auth::isAssistantDg() || Auth::hasAnyRole(['caissiere_principale', 'superviseur_general', 'assistant_dg', 'assistante_dg', 'dg'])) {
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

        // Sans ce controle, une page piegee peut declencher cette action a l insu
        // de l utilisateur connecte, avec ses propres droits.
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expiree ou requete invalide. Veuillez reessayer.');
            header('Location: ' . View::url('finance/factures'));
            exit;
        }

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

        // Vérifier si une facture existe déjà pour ce colis
        $existingFacture = $this->factureRepo->findByColisId($colisId);
        if ($existingFacture) {
            Session::flash('info', "Une facture existe déjà pour ce colis ({$existingFacture->numeroFacture}).");
            header('Location: ' . View::url('finance/factures/' . $existingFacture->id));
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

        $candidateAgenceId = !empty($colis['agence_depart_id']) ? (int) $colis['agence_depart_id'] : (Auth::agenceId() ?: null);
        $agenceId = $this->factureRepo->resolveValidAgencyId($candidateAgenceId);
        $numeroFacture = $this->factureRepo->generateNextInvoiceNumber($agenceId);

        // Auto-heal l'agence de départ du colis si elle était manquante ou à 0
        if (empty($colis['agence_depart_id']) || (int)$colis['agence_depart_id'] === 0) {
            $upColis = $this->db->prepare("UPDATE lbp_colis SET agence_depart_id = :ag_id WHERE id = :id AND (agence_depart_id IS NULL OR agence_depart_id = 0)");
            $upColis->execute(['ag_id' => $agenceId, 'id' => $colisId]);
        }

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
            dateEcheanceSolde: $dateEcheanceSolde,
            trajetId: !empty($colis['trajet_id']) ? (int) $colis['trajet_id'] : null,
            agentId: !empty($colis['agent_groupage_id']) ? (int) $colis['agent_groupage_id'] : (int) Auth::id(),
            createdBy: (int) Auth::id()
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
        RoleMiddleware::check(['caissiere', 'caissiere_principale', 'chef_agence', 'dg', 'comptable', 'superviseur_regional', 'superviseur_general', 'suivi_recouvrement', 'agent_enregistrement']);

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
        RoleMiddleware::check(['caissiere', 'caissiere_principale', 'chef_agence', 'dg', 'agent_groupage', 'suivi_recouvrement']);

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

        if ($facture->statut === 'payee' || $facture->statut === 'annulee') {
            Session::flash('error', 'Cette facture est déjà soldée ou annulée.');
            header('Location: ' . View::url('finance/factures/' . $id));
            exit;
        }

        // Traçabilité de l'auteur du colis pour l'audit d'encaissement
        $colisCreatorId = 0;
        try {
            $stmt = $this->db->prepare("SELECT created_by FROM lbp_colis WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $facture->colisId]);
            $colisCreatorId = (int) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            $colisCreatorId = 0;
        }

        $montant = (float) ($_POST['montant'] ?? 0.0);
        $mode = strtolower(trim((string) ($_POST['mode'] ?? 'especes')));
        $dateEcheance = !empty($_POST['date_echeance_solde']) ? $_POST['date_echeance_solde'] . ' 12:00:00' : null;

        if ($montant <= 0 || $montant > $facture->montantRestant) {
            Session::flash('error', 'Montant d\'encaissement invalide.');
            header('Location: ' . View::url('finance/factures/' . $id));
            exit;
        }

        // Le mode arrive du formulaire et alimente directement une colonne ENUM :
        // sans contrôle, une valeur inattendue est soit rejetée par MySQL en mode strict,
        // soit stockée en chaîne vide et faussée ensuite dans la ventilation de caisse.
        if (!in_array($mode, self::MODES_ENCAISSEMENT_GUICHET, true)) {
            Session::flash('error', 'Mode d\'encaissement invalide.');
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
        } catch (\Throwable $e) {
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

        // Sans ce controle, une page piegee peut declencher cette action a l insu
        // de l utilisateur connecte, avec ses propres droits.
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expiree ou requete invalide. Veuillez reessayer.');
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
            // 'DEBIT_FACTURE' est la valeur déclarée par l'ENUM `type` de la table.
            // 'DEBIT' y était écrit alors qu'elle n'en fait pas partie : rejetée en
            // sql_mode strict (le règlement complet échouait), tronquée en chaîne vide sinon.
            $stmtTx = $pdo->prepare("INSERT INTO lbp_client_wallet_transactions (wallet_id, type, montant_xof, mode_paiement, reference_transac, motif) VALUES (:wallet_id, 'DEBIT_FACTURE', :montant, 'Portefeuille Client', :ref, :motif)");
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
        RoleMiddleware::check(['caissiere', 'caissiere_principale', 'chef_agence', 'dg', 'suivi_recouvrement']);

        // Sans ce controle, une page piegee peut declencher cette action a l insu
        // de l utilisateur connecte, avec ses propres droits.
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expiree ou requete invalide. Veuillez reessayer.');
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
        RoleMiddleware::check(['caissiere', 'caissiere_principale', 'chef_agence', 'dg', 'comptable', 'suivi_recouvrement']);

        // Sans ce controle, une page piegee peut declencher cette action a l insu
        // de l utilisateur connecte, avec ses propres droits.
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expiree ou requete invalide. Veuillez reessayer.');
            header('Location: ' . View::url('finance/factures'));
            exit;
        }

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
        if (Auth::isAdmin() || Auth::isAssistantDg() || Auth::hasAnyRole(['caissiere_principale', 'superviseur_general', 'dg', 'assistant_dg', 'assistante_dg', 'comptable'])) {
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

        // Sans ce controle, une page piegee peut declencher cette action a l insu
        // de l utilisateur connecte, avec ses propres droits.
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expiree ou requete invalide. Veuillez reessayer.');
            header('Location: ' . View::url('finance/depenses'));
            exit;
        }

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

        // Sans ce controle, une page piegee peut declencher cette action a l insu
        // de l utilisateur connecte, avec ses propres droits.
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expiree ou requete invalide. Veuillez reessayer.');
            header('Location: ' . View::url('finance/depenses'));
            exit;
        }

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
     * Complete une liste de factures avec le numero de tracking de leur colis et le
     * nom de leur client, en deux requetes groupees.
     *
     * @param array<int, \App\Models\Finance\Facture> $factures
     */
    private function hydraterFactures(array $factures): void
    {
        if ($factures === []) {
            return;
        }

        $colisIds = array_values(array_unique(array_filter(array_map(
            static fn($f): int => (int) $f->colisId,
            $factures
        ))));

        $clientIds = array_values(array_unique(array_filter(array_map(
            static fn($f): int => (int) $f->clientId,
            $factures
        ))));

        $trackings = $this->indexer('SELECT id, numero_tracking AS valeur FROM lbp_colis WHERE id IN', $colisIds);
        $clients = $this->indexer('SELECT id, name AS valeur FROM lbp_clients WHERE id IN', $clientIds);

        foreach ($factures as $facture) {
            $facture->colis_tracking = $trackings[(int) $facture->colisId] ?? '';
            $facture->client_name = $clients[(int) $facture->clientId] ?? '';
        }
    }

    /**
     * Execute une requete « id / valeur » sur une liste d identifiants et retourne
     * le resultat indexe par identifiant.
     *
     * @param array<int, int> $ids
     * @return array<int, string>
     */
    private function indexer(string $debutSql, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $marqueurs = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare($debutSql . ' (' . $marqueurs . ')');
        $stmt->execute($ids);

        $indexe = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $ligne) {
            $indexe[(int) $ligne['id']] = (string) ($ligne['valeur'] ?? '');
        }

        return $indexe;
    }

    /**
     * Identifiant limitant les points de caisse aux seules operations de l'utilisateur,
     * ou null lorsqu'il a droit au cumul de l'agence.
     */
    private function pointCaisseScopeUserId(): ?int
    {
        if (Auth::isAdmin() || Auth::isAssistantDg() || Auth::hasAnyRole(self::ROLES_CUMUL_AGENCE)) {
            return null;
        }

        return Auth::id();
    }

    /**
     * Points de caisse et états journaliers.
     */
    public function cloturesIndex(): void
    {
        // agent_enregistrement est admis mais absent de ROLES_CUMUL_AGENCE :
        // il ne voit que les factures qu'il a lui-meme saisies.
        RoleMiddleware::check(['caissiere', 'chef_agence', 'caissiere_principale', 'dg', 'comptable', 'superviseur_general', 'superviseur_regional', 'agent_enregistrement', 'admin']);

        $userAgenceId = Auth::agenceId();
        $dateJour = date('Y-m-d');
        $isGlobalRole = Auth::isAdmin() || Auth::isAssistantDg() || Auth::hasAnyRole(['caissiere_principale', 'dg', 'assistant_dg', 'assistante_dg', 'comptable', 'superviseur_general', 'superviseur_regional', 'admin']);

        $agences = $this->db->query("SELECT id, name FROM company_sites WHERE is_active = 1 ORDER BY name ASC")->fetchAll() ?: [];

        // Portee : null = cumul de l'agence, sinon restriction aux operations de l'agent.
        $scopeUserId = $this->pointCaisseScopeUserId();

        $selectedAgenceId = isset($_GET['agence_id']) && $_GET['agence_id'] !== '' ? (int) $_GET['agence_id'] : ($userAgenceId ? (int) $userAgenceId : 0);

        $filters = [
            'date_exacte' => $_GET['date_exacte'] ?? '',
            'semaine' => $_GET['semaine'] ?? '',
            'mois' => $_GET['mois'] ?? '',
            'annee' => $_GET['annee'] ?? '',
            'statut' => $_GET['statut'] ?? '',
        ];

        if ($isGlobalRole) {
            $reports = $selectedAgenceId > 0 ? $this->etatRepo->getEtatsByAgence($selectedAgenceId, $filters) : $this->etatRepo->getEtatsGlobal($filters);
        } else {
            $selectedAgenceId = (int) $userAgenceId;
            $reports = $this->etatRepo->getEtatsByAgence($selectedAgenceId, $filters);
        }

        // Déterminer l'agence et la date dont la caisse est affichée
        $targetAgenceId = $selectedAgenceId;
        if ($targetAgenceId === 0 && !empty($agences)) {
            $targetAgenceId = (int) $agences[0]['id'];
        }

        $targetDate = !empty($filters['date_exacte']) ? $filters['date_exacte'] : date('Y-m-d');

        $activeReport = null;
        if ($targetAgenceId > 0) {
            $existing = $this->etatRepo->findByAgenceAndDate($targetAgenceId, $targetDate);
            $live = $this->etatRepo->computeTotalsForDay($targetAgenceId, $targetDate, $scopeUserId);

            // Le bandeau annonce une position « en temps reel » : ses compteurs sont donc
            // toujours recalcules. Les valeurs figees dans l'etat soumis ignoraient toute
            // facture posterieure a la soumission, alors que la liste de detail juste en
            // dessous, elle, restait a jour : le compteur affichait moins que la liste.
            $activeReport = $live + [
                'statut' => 'brouillon',
                'date_jour' => $targetDate,
                'agence_id' => $targetAgenceId,
            ];

            if ($existing) {
                $etat = (array) $existing;

                // Le comptage physique et l'ecart n'existent que dans l'etat soumis.
                $activeReport['id'] = $etat['id'] ?? null;
                $activeReport['statut'] = $etat['statut'] ?? 'brouillon';
                $activeReport['dateSoumission'] = $etat['dateSoumission'] ?? null;
                $activeReport['soldePhysiqueDeclare'] = $etat['soldePhysiqueDeclare'] ?? null;
                $activeReport['ecartCaisse'] = $etat['ecartCaisse'] ?? 0.0;
                $activeReport['explicationEcart'] = $etat['explicationEcart'] ?? null;
                $activeReport['consolideParId'] = $etat['consolideParId'] ?? null;

                // Ecart entre le total fige a la soumission et le total reel du jour :
                // signale les operations enregistrees apres la cloture.
                $activeReport['totalFactureSoumis'] = (float) ($etat['totalFactureXof'] ?? 0.0);
                $activeReport['totalEncaisseSoumis'] = (float) ($etat['totalEncaisseXof'] ?? 0.0);
            }

            $activeReport['scope_user_id'] = $scopeUserId;
            $activeReport['scope_user_name'] = $scopeUserId !== null
                ? (Auth::user()?->fullName ?? 'Mes opérations')
                : null;

            // Charger le nom de l'agence pour l'en-tête
            $stmt = $this->db->prepare("SELECT name FROM company_sites WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $targetAgenceId]);
            $activeReport['agence_name'] = $stmt->fetchColumn() ?: ('Agence #' . $targetAgenceId);
        }

        // Historique : les lignes de lbp_etats_journaliers portent le cumul de l'agence.
        // Pour un agent restreint, ses propres totaux sont recalcules journee par journee.
        if ($scopeUserId !== null && $reports !== []) {
            $dates = array_map(static fn($r): string => substr((string) $r->dateJour, 0, 10), $reports);
            $userTotals = $this->etatRepo->getUserDailyTotals(
                $scopeUserId,
                min($dates),
                max($dates),
                $selectedAgenceId > 0 ? $selectedAgenceId : 0
            );

            foreach ($reports as $r) {
                $key = $r->agenceId . '|' . substr((string) $r->dateJour, 0, 10);
                $t = $userTotals[$key] ?? null;

                $r->nbColisEnregistres = (int) ($t['nb_colis'] ?? 0);
                $r->nbFacturesEmises = (int) ($t['nb_factures'] ?? 0);
                $r->totalFactureXof = (float) ($t['total_facture_xof'] ?? 0.0);
                $r->totalFactureEur = (float) ($t['total_facture_eur'] ?? 0.0);
                $r->totalEncaisseXof = (float) ($t['total_encaisse_xof'] ?? 0.0);
                $r->totalEncaisseEur = (float) ($t['total_encaisse_eur'] ?? 0.0);
                $r->totalRestantDuXof = (float) ($t['total_restant_du_xof'] ?? 0.0);
                $r->totalRestantDuEur = (float) ($t['total_restant_du_eur'] ?? 0.0);

                // L'ecart de caisse porte sur le comptage physique de toute l'agence :
                // il n'a pas de sens ramene au perimetre d'un seul agent.
                $r->ecartCaisse = 0.0;
                $r->explicationEcart = null;
                $r->soldePhysiqueDeclare = null;
            }
        }

        // Calculer les jours non soumis (rétroactifs) pour l'agence ciblée
        $joursNonSoumis = [];
        if ($targetAgenceId > 0) {
            $joursNonSoumis = $this->etatRepo->getJoursNonSoumis($targetAgenceId, 4);
        }

        $this->financeView('finance/clotures/index', 'Points de Caisse', 'clotures', [
            'reports' => $reports,
            'agences' => $agences,
            'activeReport' => $activeReport,
            'selectedAgenceId' => $selectedAgenceId,
            'filters' => $filters,
            'joursNonSoumis' => $joursNonSoumis,
            'scopeUserId' => $scopeUserId,
        ]);
    }

    /**
     * Soumission du point de caisse (par la caissière/chef d'agence).
     */
    public function clotureSoumettre(): void
    {
        RoleMiddleware::check(['caissiere', 'chef_agence', 'caissiere_principale', 'dg']);

        // Sans ce controle, une page piegee peut declencher cette action a l insu
        // de l utilisateur connecte, avec ses propres droits.
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expiree ou requete invalide. Veuillez reessayer.');
            header('Location: ' . View::url('finance/clotures'));
            exit;
        }

        $agenceId = Auth::agenceId() ?? (!empty($_POST['agence_id']) ? (int) $_POST['agence_id'] : null);
        if ($agenceId === null) {
            Session::flash('error', 'Veuillez sélectionner l\'agence pour laquelle vous soumettez le point de caisse.');
            header('Location: ' . View::url('finance/clotures'));
            exit;
        }

        // Déterminer la date cible : aujourd'hui par défaut, ou une date antérieure (rétroactif)
        $dateJour = date('Y-m-d');
        $dateCible = !empty($_POST['date_cible']) ? trim($_POST['date_cible']) : $dateJour;

        // Valider que la date cible est un format YYYY-MM-DD valide et pas dans le futur
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateCible) || $dateCible > $dateJour) {
            Session::flash('error', 'La date sélectionnée est invalide ou située dans le futur.');
            header('Location: ' . View::url('finance/clotures'));
            exit;
        }

        $isRetroactif = ($dateCible !== $dateJour);
        $justificationRetard = isset($_POST['justification_retard']) ? trim((string) $_POST['justification_retard']) : null;
        if ($justificationRetard === '') {
            $justificationRetard = null;
        }

        // Vérifier si un état existe déjà pour cette date
        $existing = $this->etatRepo->findByAgenceAndDate((int) $agenceId, $dateCible);
        if ($existing && $existing->statut !== 'brouillon') {
            Session::flash('error', 'Le point de caisse de ce jour a déjà été soumis.');
            header('Location: ' . View::url('finance/clotures'));
            exit;
        }

        // Calculer les totaux en temps réel pour la date cible.
        // Toujours au niveau agence : le point de caisse soumis est un acte unique
        // couvrant la caisse entiere, quel que soit le perimetre d'affichage de l'agent.
        $live = $this->etatRepo->computeTotalsForDay((int) $agenceId, $dateCible);

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
            $existing->soumissionRetroactive = $isRetroactif;
            $existing->justificationRetard = $justificationRetard;
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
                dateJour: $dateCible,
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
                justificatifUrl: $justificatifUrl,
                soumissionRetroactive: $isRetroactif,
                justificationRetard: $justificationRetard
            );
            $reportId = $this->etatRepo->create($etat);
        }

        AuditLogService::log('submit_cash_report', 'lbp_etats_journaliers', $reportId, null, $live + ['ecart' => $ecart, 'retroactif' => $isRetroactif, 'justification_retard' => $justificationRetard]);

        // Alerte immédiate sur le téléphone de la direction si l'écart dépasse le seuil.
        // L'envoi ne doit jamais empêcher la soumission d'aboutir : une panne du service
        // de notification ne peut pas bloquer la clôture d'une caisse.
        if (abs($ecart) >= \App\Services\Mobile\NotificationDirectionService::SEUIL_ECART_XOF) {
            try {
                $stmtAg = $this->db->prepare("SELECT name FROM company_sites WHERE id = :id LIMIT 1");
                $stmtAg->execute(['id' => $agenceId]);
                $nomAgence = (string) ($stmtAg->fetchColumn() ?: ('Agence #' . $agenceId));

                \App\Services\Mobile\NotificationDirectionService::creer($this->db)
                    ->ecartDeCaisse($reportId, $nomAgence, $dateCible, $ecart, $explication !== '' ? $explication : null);
            } catch (\Throwable $e) {
                error_log('[LBP] Alerte écart de caisse non envoyée : ' . $e->getMessage());
            }
        }

        $msgRetro = $isRetroactif ? ' (soumission rétroactive pour le ' . date('d/m/Y', strtotime($dateCible)) . ')' : '';
        Session::flash('success', 'Le point de caisse avec rapprochement a été soumis et verrouillé avec succès' . $msgRetro . '.');
        header('Location: ' . View::url('finance/clotures'));
        exit;
    }

    /**
     * Consolidation du point de caisse par la caissière principale (Verrouillage central).
     */
    public function clotureConsolider(string $id): void
    {
        RoleMiddleware::check(['caissiere_principale', 'dg']);

        // Sans ce controle, une page piegee peut declencher cette action a l insu
        // de l utilisateur connecte, avec ses propres droits.
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expiree ou requete invalide. Veuillez reessayer.');
            header('Location: ' . View::url('finance/clotures'));
            exit;
        }

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

        // Seul le chef de cette agence, la caissière principale, le DG, l'assistante DG ou le comptable peuvent exporter
        $userAgenceId = Auth::user()?->agenceId ?? 0;
        if (!Auth::isAdmin() && !Auth::isAssistantDg() && !Auth::hasRole(['caissiere_principale', 'dg', 'assistant_dg', 'assistante_dg', 'comptable', 'superviseur_general']) && (int) $userAgenceId !== $report->agenceId) {
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
     * Export PDF détaillé des points de caisse sur une journée ou une plage de dates.
     * Regroupement : par agence, puis par journée.
     *
     * Paramètres GET : agence_id (0 = toutes), date_debut, date_fin (Y-m-d).
     */
    public function exportPointCaisseDetaillePdf(): void
    {
        AuthMiddleware::check();
        RoleMiddleware::check(['caissiere', 'chef_agence', 'caissiere_principale', 'dg', 'comptable', 'superviseur_general', 'superviseur_regional', 'agent_enregistrement', 'admin']);

        $userAgenceId = (int) (Auth::user()?->agenceId ?? 0);
        $isGlobal = Auth::isAdmin() || Auth::isAssistantDg() || Auth::hasAnyRole(['caissiere_principale', 'dg', 'assistant_dg', 'assistante_dg', 'comptable', 'superviseur_general', 'superviseur_regional', 'admin']);

        $dateDebut = trim((string) ($_GET['date_debut'] ?? ''));
        $dateFin = trim((string) ($_GET['date_fin'] ?? ''));

        if ($dateDebut === '') {
            $dateDebut = date('Y-m-d');
        }
        if ($dateFin === '') {
            $dateFin = $dateDebut;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDebut) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFin)) {
            Session::flash('error', 'Les dates de la plage sont invalides. Format attendu : JJ/MM/AAAA.');
            header('Location: ' . View::url('finance/clotures'));
            exit;
        }

        if ($dateDebut > $dateFin) {
            [$dateDebut, $dateFin] = [$dateFin, $dateDebut];
        }

        // Garde-fou : une plage trop large produirait un document ingérable.
        $nbJours = (int) ((strtotime($dateFin) - strtotime($dateDebut)) / 86400) + 1;
        if ($nbJours > 366) {
            Session::flash('error', 'La plage demandée dépasse 366 jours. Veuillez la réduire (par exemple année par année).');
            header('Location: ' . View::url('finance/clotures'));
            exit;
        }

        $agenceId = isset($_GET['agence_id']) && $_GET['agence_id'] !== '' ? (int) $_GET['agence_id'] : 0;

        // Un utilisateur rattaché à une agence ne peut imprimer que sa propre caisse.
        if (!$isGlobal) {
            if ($userAgenceId <= 0) {
                Session::flash('error', 'Aucune agence n\'est rattachée à votre compte.');
                header('Location: ' . View::url('finance/clotures'));
                exit;
            }
            $agenceId = $userAgenceId;
        }

        // Un agent non habilite au cumul n'imprime que ses propres operations.
        $scopeUserId = $this->pointCaisseScopeUserId();

        $operations = $this->etatRepo->getDetailedOperations($agenceId, $dateDebut, $dateFin, $scopeUserId);
        $encaissements = $this->etatRepo->getDetailedEncaissements($agenceId, $dateDebut, $dateFin, $scopeUserId);
        $etatsIndexes = $scopeUserId === null
            ? $this->etatRepo->getEtatsForRangeIndexed($agenceId, $dateDebut, $dateFin)
            : [];
        $natureBreakdown = $this->etatRepo->getNatureBreakdown($agenceId, $dateDebut, $dateFin, $scopeUserId);

        $agencesDetail = $this->buildAgencesDetailStructure($operations, $encaissements, $etatsIndexes);
        $summary = $this->buildDetailSummary($agencesDetail);

        if ($agenceId > 0) {
            $stmt = $this->db->prepare("SELECT name FROM company_sites WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $agenceId]);
            $perimetre = 'Agence ' . ($stmt->fetchColumn() ?: ('#' . $agenceId));
        } else {
            $perimetre = 'Toutes les agences du réseau';
        }

        if ($scopeUserId !== null) {
            $perimetre .= ' — opérations de ' . (Auth::user()?->fullName ?? 'l\'agent connecté');
        }

        $periode = [
            'debut' => $dateDebut,
            'fin' => $dateFin,
            'nb_jours' => $nbJours,
            'est_journee_unique' => $dateDebut === $dateFin,
        ];

        $editePar = Auth::user()?->fullName ?? 'Utilisateur';
        $editeLe = date('d/m/Y à H:i');

        require BASE_PATH . '/views/finance/point_caisse_detaille_pdf.php';
    }

    /**
     * Regroupe les opérations et encaissements par agence, puis par journée.
     *
     * @param array<int, array<string, mixed>> $operations
     * @param array<int, array<string, mixed>> $encaissements
     * @param array<string, array<string, mixed>> $etatsIndexes
     * @return array<int, array<string, mixed>>
     */
    private function buildAgencesDetailStructure(array $operations, array $encaissements, array $etatsIndexes): array
    {
        $agences = [];

        $ensureJour = static function (array &$agences, int $agenceId, string $agenceName, string $date): void {
            if (!isset($agences[$agenceId])) {
                $agences[$agenceId] = [
                    'agence_id' => $agenceId,
                    'agence_name' => $agenceName,
                    'jours' => [],
                ];
            }
            if (!isset($agences[$agenceId]['jours'][$date])) {
                $agences[$agenceId]['jours'][$date] = [
                    'date' => $date,
                    'operations' => [],
                    'encaissements' => [],
                    'etat' => null,
                ];
            }
        };

        foreach ($operations as $op) {
            $agId = (int) ($op['agence_id'] ?? 0);
            $date = substr((string) ($op['date_jour'] ?? ''), 0, 10);
            $ensureJour($agences, $agId, (string) ($op['agence_name'] ?? ('Agence #' . $agId)), $date);
            $agences[$agId]['jours'][$date]['operations'][] = $op;
        }

        foreach ($encaissements as $enc) {
            $agId = (int) ($enc['agence_id'] ?? 0);
            $date = substr((string) ($enc['date_jour'] ?? ''), 0, 10);
            $ensureJour($agences, $agId, (string) ($enc['agence_name'] ?? ('Agence #' . $agId)), $date);
            $agences[$agId]['jours'][$date]['encaissements'][] = $enc;
        }

        // Les journées clôturées sans aucune opération doivent tout de même figurer au rapport.
        foreach ($etatsIndexes as $key => $etat) {
            [$agIdStr, $date] = explode('|', $key, 2);
            $agId = (int) $agIdStr;
            $agenceName = (string) ($agences[$agId]['agence_name'] ?? $etat['agence_name'] ?? ('Agence #' . $agId));
            $ensureJour($agences, $agId, $agenceName, $date);
            $agences[$agId]['jours'][$date]['etat'] = $etat;
        }

        // Totaux par journée, puis cumul par agence.
        foreach ($agences as $agId => &$agence) {
            ksort($agence['jours']);

            $agTotals = $this->emptyDetailTotals();

            foreach ($agence['jours'] as $date => &$jour) {
                $jourTotals = $this->emptyDetailTotals();

                foreach ($jour['operations'] as $op) {
                    $jourTotals['nb_factures']++;
                    $jourTotals['nb_colis'] += (int) ($op['nombre_colis'] ?? 0);
                    $jourTotals['poids'] += (float) ($op['poids_total'] ?? 0);
                    $jourTotals['volume'] += (float) ($op['volume'] ?? 0);
                    $jourTotals['valeur_declaree'] += (float) ($op['valeur_declaree'] ?? 0);

                    $devise = strtoupper((string) ($op['devise'] ?? 'XOF'));
                    if ($devise === 'EUR') {
                        $jourTotals['facture_eur'] += (float) ($op['montant_total'] ?? 0);
                    } else {
                        $jourTotals['facture_xof'] += (float) ($op['montant_total'] ?? 0);
                    }
                    $jourTotals['restant'] += (float) ($op['montant_restant'] ?? 0);
                }

                foreach ($jour['encaissements'] as $enc) {
                    $montant = (float) ($enc['montant'] ?? 0);
                    $devise = strtoupper((string) ($enc['devise'] ?? 'XOF'));
                    if ($devise === 'EUR') {
                        $jourTotals['encaisse_eur'] += $montant;
                    } else {
                        $jourTotals['encaisse_xof'] += $montant;
                    }

                    $mode = strtoupper((string) ($enc['mode_reglement'] ?? 'ESPECES'));
                    if (!isset($jourTotals['par_mode'][$mode])) {
                        $jourTotals['par_mode'][$mode] = ['montant' => 0.0, 'nb' => 0];
                    }
                    $jourTotals['par_mode'][$mode]['montant'] += $montant;
                    $jourTotals['par_mode'][$mode]['nb']++;
                }

                if (!empty($jour['etat'])) {
                    $jourTotals['solde_physique'] = isset($jour['etat']['solde_physique_declare']) && is_numeric($jour['etat']['solde_physique_declare'])
                        ? (float) $jour['etat']['solde_physique_declare']
                        : null;
                    $jourTotals['ecart'] = (float) ($jour['etat']['ecart_caisse'] ?? 0);
                    $jourTotals['statut'] = (string) ($jour['etat']['statut'] ?? 'brouillon');
                }

                $jour['totals'] = $jourTotals;

                $agTotals['nb_jours']++;
                $agTotals['nb_factures'] += $jourTotals['nb_factures'];
                $agTotals['nb_colis'] += $jourTotals['nb_colis'];
                $agTotals['poids'] += $jourTotals['poids'];
                $agTotals['volume'] += $jourTotals['volume'];
                $agTotals['valeur_declaree'] += $jourTotals['valeur_declaree'];
                $agTotals['facture_xof'] += $jourTotals['facture_xof'];
                $agTotals['facture_eur'] += $jourTotals['facture_eur'];
                $agTotals['encaisse_xof'] += $jourTotals['encaisse_xof'];
                $agTotals['encaisse_eur'] += $jourTotals['encaisse_eur'];
                $agTotals['restant'] += $jourTotals['restant'];
                $agTotals['ecart'] += $jourTotals['ecart'];

                foreach ($jourTotals['par_mode'] as $mode => $data) {
                    if (!isset($agTotals['par_mode'][$mode])) {
                        $agTotals['par_mode'][$mode] = ['montant' => 0.0, 'nb' => 0];
                    }
                    $agTotals['par_mode'][$mode]['montant'] += $data['montant'];
                    $agTotals['par_mode'][$mode]['nb'] += $data['nb'];
                }
            }
            unset($jour);

            $agence['totals'] = $agTotals;
        }
        unset($agence);

        uasort($agences, static fn(array $a, array $b): int => strcmp((string) $a['agence_name'], (string) $b['agence_name']));

        return array_values($agences);
    }

    /**
     * Cumul global de la période, toutes agences confondues.
     *
     * @param array<int, array<string, mixed>> $agencesDetail
     * @return array<string, mixed>
     */
    private function buildDetailSummary(array $agencesDetail): array
    {
        $summary = $this->emptyDetailTotals();
        $summary['nb_agences'] = count($agencesDetail);

        foreach ($agencesDetail as $agence) {
            $t = $agence['totals'];
            $summary['nb_jours'] += $t['nb_jours'];
            $summary['nb_factures'] += $t['nb_factures'];
            $summary['nb_colis'] += $t['nb_colis'];
            $summary['poids'] += $t['poids'];
            $summary['volume'] += $t['volume'];
            $summary['valeur_declaree'] += $t['valeur_declaree'];
            $summary['facture_xof'] += $t['facture_xof'];
            $summary['facture_eur'] += $t['facture_eur'];
            $summary['encaisse_xof'] += $t['encaisse_xof'];
            $summary['encaisse_eur'] += $t['encaisse_eur'];
            $summary['restant'] += $t['restant'];
            $summary['ecart'] += $t['ecart'];

            foreach ($t['par_mode'] as $mode => $data) {
                if (!isset($summary['par_mode'][$mode])) {
                    $summary['par_mode'][$mode] = ['montant' => 0.0, 'nb' => 0];
                }
                $summary['par_mode'][$mode]['montant'] += $data['montant'];
                $summary['par_mode'][$mode]['nb'] += $data['nb'];
            }
        }

        uasort($summary['par_mode'], static fn(array $a, array $b): int => $b['montant'] <=> $a['montant']);

        return $summary;
    }

    /**
     * Squelette de totaux utilisé pour les cumuls journée / agence / période.
     *
     * @return array<string, mixed>
     */
    private function emptyDetailTotals(): array
    {
        return [
            'nb_jours' => 0,
            'nb_factures' => 0,
            'nb_colis' => 0,
            'poids' => 0.0,
            'volume' => 0.0,
            'valeur_declaree' => 0.0,
            'facture_xof' => 0.0,
            'facture_eur' => 0.0,
            'encaisse_xof' => 0.0,
            'encaisse_eur' => 0.0,
            'restant' => 0.0,
            'ecart' => 0.0,
            'solde_physique' => null,
            'statut' => null,
            'par_mode' => [],
        ];
    }

    /**
     * Livre journal et balance comptable.
     */
    public function comptabilite(): void
    {
        RoleMiddleware::check(['comptable', 'dg', 'superviseur_general']);

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
     * Enregistrer une écriture manuelle (Opérations Diverses / OD).
     */
    public function ecritureManuelleStore(): void
    {
        RoleMiddleware::check(['comptable', 'dg']);

        // Sans ce controle, une page piegee peut declencher cette action a l insu
        // de l utilisateur connecte, avec ses propres droits.
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expiree ou requete invalide. Veuillez reessayer.');
            header('Location: ' . View::url('finance/comptabilite'));
            exit;
        }

        $dateEcriture = trim((string) ($_POST['date_ecriture'] ?? date('Y-m-d')));
        $journal = trim((string) ($_POST['journal'] ?? 'OD'));
        $compteDebit = trim((string) ($_POST['compte_debit'] ?? ''));
        $compteCredit = trim((string) ($_POST['compte_credit'] ?? ''));
        $montant = (float) ($_POST['montant'] ?? 0.0);
        $libelle = trim((string) ($_POST['libelle'] ?? ''));
        $pieceRef = trim((string) ($_POST['piece_justificative_id'] ?? ''));

        if ($compteDebit === '' || $compteCredit === '' || $montant <= 0 || $libelle === '') {
            Session::flash('error', 'Veuillez remplir tous les champs obligatoires (comptes, montant et libellé).');
            header('Location: ' . View::url('finance/comptabilite'));
            exit;
        }

        if ($compteDebit === $compteCredit) {
            Session::flash('error', 'Le compte débiteur et le compte créditeur doivent être différents.');
            header('Location: ' . View::url('finance/comptabilite'));
            exit;
        }

        $ecriture = new EcritureComptable(
            id: null,
            dateEcriture: $dateEcriture,
            journal: $journal,
            compteDebit: $compteDebit,
            compteCredit: $compteCredit,
            montant: $montant,
            devise: 'XOF',
            tauxChange: null,
            pieceJustificativeId: $pieceRef !== '' ? $pieceRef : 'OD-' . date('Ymd-His'),
            libelle: $libelle
        );

        $this->comptabiliteRepo->createEcriture($ecriture);
        AuditLogService::log('create_manual_entry', 'lbp_ecritures_comptables', 0, null, (array) $ecriture);

        Session::flash('success', 'Écriture manuelle (OD) enregistrée avec succès.');
        header('Location: ' . View::url('finance/comptabilite'));
        exit;
    }

    /**
     * Lettrage des écritures sélectionnées.
     */
    public function lettrer(): void
    {
        RoleMiddleware::check(['comptable', 'dg']);

        // Sans ce controle, une page piegee peut declencher cette action a l insu
        // de l utilisateur connecte, avec ses propres droits.
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expiree ou requete invalide. Veuillez reessayer.');
            header('Location: ' . View::url('finance/comptabilite'));
            exit;
        }

        $ids = $_POST['ids'] ?? [];
        $code = trim((string) ($_POST['code'] ?? ''));

        if (!is_array($ids) || $ids === [] || $code === '') {
            Session::flash('error', 'Veuillez sélectionner au moins une écriture et indiquer un code de lettrage.');
            header('Location: ' . View::url('finance/comptabilite'));
            exit;
        }

        $this->comptabiliteRepo->lettrerEcritures($ids, $code);
        Session::flash('success', "Les écritures sélectionnées ont été lettrées avec le code '{$code}'.");
        header('Location: ' . View::url('finance/comptabilite'));
        exit;
    }

    /**
     * Contre-passation d'une écriture comptable.
     */
    public function contrePasser(string $id): void
    {
        RoleMiddleware::check(['comptable', 'dg']);

        // Sans ce controle, une page piegee peut declencher cette action a l insu
        // de l utilisateur connecte, avec ses propres droits.
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expiree ou requete invalide. Veuillez reessayer.');
            header('Location: ' . View::url('finance/comptabilite'));
            exit;
        }

        $id = (int) $id;
        $motif = trim((string) ($_POST['motif'] ?? 'Annulation/Erreur de saisie'));

        try {
            $newId = $this->comptabiliteRepo->contrePasserEcriture($id, $motif);
            Session::flash('success', "L'écriture #{$id} a été contre-passée avec succès (Nouvelle écriture d'annulation #{$newId}).");
        } catch (\Throwable $e) {
            Session::flash('error', "Erreur lors de la contre-passation : " . $e->getMessage());
        }

        header('Location: ' . View::url('finance/comptabilite'));
        exit;
    }

    /**
     * Balance des comptes (Balance générale).
     */
    public function balanceComptes(): void
    {
        RoleMiddleware::check(['comptable', 'dg', 'superviseur_general']);

        $filters = [
            'date_debut' => $_GET['date_debut'] ?? '',
            'date_fin' => $_GET['date_fin'] ?? '',
        ];

        $balance = $this->comptabiliteRepo->getBalanceDesComptes($filters);

        $this->financeView('finance/balance_comptes/index', 'Balance des Comptes', 'balance_comptes', [
            'balance' => $balance,
            'filters' => $filters,
        ]);
    }

    /**
     * Plan comptable (Index & Création).
     */
    public function planComptableIndex(): void
    {
        RoleMiddleware::check(['comptable', 'dg', 'superviseur_general']);

        $plan = $this->comptabiliteRepo->getPlanComptable();

        $this->financeView('finance/plan_comptable/index', 'Plan Comptable SYSCOHADA', 'plan_comptable', [
            'plan' => $plan,
        ]);
    }

    public function planComptableStore(): void
    {
        RoleMiddleware::check(['comptable', 'dg']);

        // Sans ce controle, une page piegee peut declencher cette action a l insu
        // de l utilisateur connecte, avec ses propres droits.
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expiree ou requete invalide. Veuillez reessayer.');
            header('Location: ' . View::url('finance/plan-comptable'));
            exit;
        }

        $code = trim((string) ($_POST['code'] ?? ''));
        $libelle = trim((string) ($_POST['libelle'] ?? ''));
        $classe = (int) ($_POST['classe'] ?? 6);

        if ($code === '' || $libelle === '') {
            Session::flash('error', 'Le numéro de compte et l\'intitulé sont obligatoires.');
            header('Location: ' . View::url('finance/plan-comptable'));
            exit;
        }

        $this->comptabiliteRepo->addCompteComptable($code, $libelle, $classe);
        Session::flash('success', "Le compte {$code} - {$libelle} a été ajouté au plan comptable.");
        header('Location: ' . View::url('finance/plan-comptable'));
        exit;
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
        RoleMiddleware::check(['comptable', 'dg', 'chef_agence', 'superviseur_general', 'suivi_recouvrement']);

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
        RoleMiddleware::check(['comptable', 'dg', 'superviseur_general']);

        $filters = [
            'journal' => $_GET['journal'] ?? '',
            'compte' => $_GET['compte'] ?? '',
            'date_debut' => $_GET['date_debut'] ?? '',
            'date_fin' => $_GET['date_fin'] ?? '',
        ];

        $ecritures = $this->comptabiliteRepo->getEcritures($filters);

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
        if (PHP_SAPI !== 'cli') {
            exit;
        }
    }

    /**
     * Export CSV de la synthèse des 13 catégories (Codes Payés vs Non Payés).
     */
    public function exportCategoriesCsv(): void
    {
        RoleMiddleware::check(['caissiere', 'caissiere_principale', 'chef_agence', 'dg', 'comptable', 'superviseur_regional', 'superviseur_general', 'suivi_recouvrement']);

        $filters = [
            'agence_id' => $_GET['agence_id'] ?? '',
            'q' => $_GET['q'] ?? '',
        ];

        $stats = $this->factureRepo->getCategoryStats($filters);
        $filename = 'export_synthese_categories_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $output = fopen('php://output', 'w');
        if ($output) {
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Catégorie de Code', 'Nombre Total Factures', 'Montant Total (XOF)', 'Factures Payées (Comptage)', 'Montant Payé (XOF)', 'Factures Non Payées (Comptage)', 'Montant Non Payé / Créances (XOF)', 'Taux de Recouvrement (%)'], ';');

            foreach ($stats as $cat => $st) {
                fputcsv($output, [
                    $st['code'],
                    $st['total_count'],
                    number_format($st['total_montant'], 2, '.', ''),
                    $st['count_paye'],
                    number_format($st['montant_paye'], 2, '.', ''),
                    $st['count_non_paye'],
                    number_format($st['montant_non_paye'], 2, '.', ''),
                    $st['taux_recouvrement'] . '%',
                ], ';');
            }

            fclose($output);
        }

        if (PHP_SAPI !== 'cli') {
            exit;
        }
    }

    /**
     * Export PDF de la synthèse des 13 catégories (Codes Payés vs Non Payés).
     */
    public function exportCategoriesPdf(): void
    {
        AuthMiddleware::check();
        RoleMiddleware::check(['caissiere', 'caissiere_principale', 'chef_agence', 'dg', 'comptable', 'superviseur_regional', 'superviseur_general', 'suivi_recouvrement']);

        $filters = [
            'agence_id' => $_GET['agence_id'] ?? '',
            'q' => $_GET['q'] ?? '',
        ];

        $stats = $this->factureRepo->getCategoryStats($filters);
        require BASE_PATH . '/views/finance/categories_pdf.php';
    }

    /**
     * Export PDF du point de caisse d'une agence spécifique (temps réel / brouillon / soumis).
     */
    public function exportClotureAgencePdf(): void
    {
        AuthMiddleware::check();

        $userAgenceId = Auth::user()?->agenceId ?? 0;
        $isGlobal = Auth::isAdmin() || Auth::isAssistantDg() || Auth::hasRole(['caissiere_principale', 'dg', 'assistant_dg', 'assistante_dg', 'comptable', 'superviseur_general', 'admin']);

        $agenceId = isset($_GET['agence_id']) && $_GET['agence_id'] !== '' ? (int) $_GET['agence_id'] : ($userAgenceId ? (int) $userAgenceId : 0);
        $dateJour = !empty($_GET['date']) ? trim((string) $_GET['date']) : date('Y-m-d');

        if ($agenceId <= 0) {
            Session::flash('error', 'Agence non spécifiée.');
            header('Location: ' . View::url('finance/clotures'));
            exit;
        }

        if (!$isGlobal && (int) $userAgenceId !== $agenceId) {
            Session::flash('error', 'Accès non autorisé au point de caisse d\'une autre agence.');
            header('Location: ' . View::url('finance/clotures'));
            exit;
        }

        $existing = $this->etatRepo->findByAgenceAndDate($agenceId, $dateJour);
        if ($existing) {
            $report = $existing;
        } else {
            $live = $this->etatRepo->computeTotalsForDay($agenceId, $dateJour);
            $report = new EtatJournalier(
                id: null,
                agenceId: $agenceId,
                chefAgenceId: Auth::id(),
                dateJour: $dateJour,
                nbColisEnregistres: (int) ($live['nb_colis'] ?? 0),
                nbFacturesEmises: (int) ($live['nb_factures'] ?? 0),
                totalFactureXof: (float) ($live['total_facture_xof'] ?? 0),
                totalFactureEur: (float) ($live['total_facture_eur'] ?? 0),
                totalEncaisseXof: (float) ($live['total_encaisse_xof'] ?? 0),
                totalEncaisseEur: (float) ($live['total_encaisse_eur'] ?? 0),
                totalRestantDuXof: (float) ($live['total_restant_du_xof'] ?? 0),
                totalRestantDuEur: (float) ($live['total_restant_du_eur'] ?? 0),
                soldeCaisseAgenceXof: (float) ($live['solde_caisse_agence_xof'] ?? 0),
                soldeCaisseAgenceEur: (float) ($live['solde_caisse_agence_eur'] ?? 0),
                statut: 'brouillon',
                dateSoumission: null,
                soldePhysiqueDeclare: null,
                ecartCaisse: 0.0,
                explicationEcart: null
            );
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

    public function exportRentabilitePdf(): void
    {
        AuthMiddleware::check();
        RoleMiddleware::check(['comptable', 'dg', 'chef_agence', 'superviseur_general', 'suivi_recouvrement']);

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
        RoleMiddleware::check(['comptable', 'dg', 'chef_agence', 'superviseur_general', 'suivi_recouvrement']);

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
        RoleMiddleware::check(['caissiere', 'caissiere_principale', 'chef_agence', 'dg', 'comptable', 'superviseur_regional', 'superviseur_general', 'suivi_recouvrement']);

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
        RoleMiddleware::check(['caissiere', 'caissiere_principale', 'chef_agence', 'dg', 'comptable', 'suivi_recouvrement']);

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
        RoleMiddleware::check(['caissiere', 'caissiere_principale', 'chef_agence', 'dg', 'comptable', 'superviseur_regional', 'superviseur_general', 'suivi_recouvrement']);

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
        RoleMiddleware::check(['caissiere', 'caissiere_principale', 'chef_agence', 'dg', 'comptable', 'superviseur_regional', 'superviseur_general', 'suivi_recouvrement']);

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

    public function guideIndex(): void
    {
        RoleMiddleware::check(['caissiere', 'caissiere_principale', 'chef_agence', 'dg', 'comptable', 'superviseur_regional', 'superviseur_general', 'suivi_recouvrement']);

        $dashService = new \App\Services\Shared\ModuleDashboardService();
        $module = $dashService->dashboard('finance');

        $this->financeView(
            'finance/guide/index',
            'Guide Interactif & Formation Finance & Comptabilité - LBP',
            'guide',
            $module,
            []
        );
    }

    public function exportRecuPdf(string $id): void
    {
        RoleMiddleware::check(['caissiere', 'caissiere_principale', 'chef_agence', 'dg', 'comptable', 'superviseur_regional', 'superviseur_general', 'admin', 'agent']);

        $id = (int) $id;
        $facture = null;
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        if (str_contains($uri, '/factures/')) {
            $facture = $this->factureRepo->findById($id);
        }

        if (!$facture) {
            $paiement = $this->paiementRepo->findById($id);
            if ($paiement && $paiement->factureId) {
                $facture = $this->factureRepo->findById($paiement->factureId);
            }
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
