<?php

declare(strict_types=1);

namespace App\Controllers\Finance;

use App\Database\Database;
use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Repositories\Finance\DemandeFondsRepository;
use App\Services\Shared\AuditLogService;

final class DemandesFondsController extends FinanceBaseController
{
    private DemandeFondsRepository $fondsRepo;

    public function __construct()
    {
        $pdo = Database::getConnection();
        $this->fondsRepo = new DemandeFondsRepository($pdo);
    }

    /**
     * Sous-menu 1 : Demandes de fonds (Tableau principal avec filtres période, état, agence, recherche).
     */
    public function index(): void
    {
        AuthMiddleware::check();

        $user = Auth::user();
        $userAgId = Auth::agenceId();
        $isSuperUser = Auth::isAdmin() || Auth::hasAnyRole(['dg', 'assistant_dg', 'caissiere_principale', 'comptable', 'superviseur_general']);

        $selectedAgence = isset($_GET['agence_id']) && $_GET['agence_id'] !== '' ? (int) $_GET['agence_id'] : null;
        if (!$isSuperUser && $selectedAgence === null && $userAgId !== null && $userAgId > 0) {
            $selectedAgence = (int) $userAgId;
        }

        $filters = [
            'agence_id'  => $selectedAgence,
            'statut'     => trim((string) ($_GET['statut'] ?? '')),
            'cadre'      => trim((string) ($_GET['cadre'] ?? '')),
            'date_from'  => trim((string) ($_GET['date_from'] ?? '')),
            'date_to'    => trim((string) ($_GET['date_to'] ?? '')),
            'q'          => trim((string) ($_GET['q'] ?? '')),
        ];

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = $this->fondsRepo->paginateDemandes($filters, $page, 15);
        $stats = $this->fondsRepo->getStats($filters);
        $agences = $this->fondsRepo->getAgences();

        $this->financeView('finance/fonds/index', 'Demandes de Fonds — Finance & Décaissements', 'fonds', [
            'items'       => $result['items'],
            'total'       => $result['total'],
            'page'        => $result['page'],
            'totalPages'  => $result['totalPages'],
            'filters'     => $filters,
            'stats'       => $stats,
            'agences'     => $agences,
            'isSuperUser' => $isSuperUser,
            'canValidate' => Auth::isAdmin() || Auth::hasAnyRole(['dg', 'assistant_dg']),
        ]);
    }

    /**
     * Formulaire d'ajout d'une demande de décaissement.
     */
    public function create(): void
    {
        AuthMiddleware::check();

        $agences = $this->fondsRepo->getAgences();
        $dossiersRecents = $this->fondsRepo->getDossiersTransitRecents();
        $defaultNum = $this->fondsRepo->generateNumeroDemande();

        $this->financeView('finance/fonds/create', 'Nouvelle Demande de Fonds — LBP Finance', 'fonds', [
            'agences'         => $agences,
            'dossiersRecents' => $dossiersRecents,
            'defaultNum'      => $defaultNum,
            'userAgenceId'    => Auth::agenceId() ?? 1,
        ]);
    }

    /**
     * Enregistrement d'une nouvelle demande de décaissement.
     */
    public function store(): void
    {
        AuthMiddleware::check();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide (CSRF).');
            header('Location: ' . View::url('finance/fonds/nouveau'));
            exit;
        }

        $cadre = trim((string) ($_POST['cadre'] ?? 'traitement_dossier'));
        $dossierNum = trim((string) ($_POST['dossier_num'] ?? ''));
        $motif = trim((string) ($_POST['motif'] ?? ''));
        $montant = (float) ($_POST['montant'] ?? 0);
        $agenceId = (int) ($_POST['agence_id'] ?? (Auth::agenceId() ?? 1));
        $devise = trim((string) ($_POST['devise'] ?? 'XOF'));

        if ($montant <= 0 || empty($motif)) {
            Session::flash('error', 'Le montant et le libellé/motif sont obligatoires.');
            header('Location: ' . View::url('finance/fonds/nouveau'));
            exit;
        }

        if ($cadre === 'traitement_dossier' && empty($dossierNum)) {
            Session::flash('error', 'Le N° de dossier est obligatoire pour les demandes de traitement de dossier.');
            header('Location: ' . View::url('finance/fonds/nouveau'));
            exit;
        }

        $data = [
            'agence_id'    => $agenceId,
            'cadre'        => $cadre,
            'dossier_num'  => $dossierNum !== '' ? $dossierNum : null,
            'motif'        => $motif,
            'montant'      => $montant,
            'devise'       => $devise,
            'demandeur_id' => (int) Auth::id(),
        ];

        $demandeId = $this->fondsRepo->createDemande($data);

        AuditLogService::log('create', 'lbp_demandes_fonds', $demandeId, null, $data);

        Session::flash('success', "La demande de fonds a été enregistrée avec succès. Elle est transmise à la Direction pour validation.");
        header('Location: ' . View::url('finance/fonds/' . $demandeId));
        exit;
    }

    /**
     * Fiche détaillée d'une demande avec Timeline d'audit.
     */
    public function show(string $id): void
    {
        AuthMiddleware::check();

        $demandeId = (int) $id;
        $demande = $this->fondsRepo->findDemandeById($demandeId);

        if (!$demande) {
            Session::flash('error', 'Demande de fonds introuvable.');
            header('Location: ' . View::url('finance/fonds'));
            exit;
        }

        $historique = $this->fondsRepo->getHistorique($demandeId);
        $canValidate = Auth::isAdmin() || Auth::hasAnyRole(['dg', 'assistant_dg']);
        $canDecaisser = Auth::isAdmin() || Auth::hasAnyRole(['dg', 'assistant_dg', 'caissiere_principale', 'caissiere', 'chef_agence']);
        $canImputer = Auth::isAdmin() || Auth::hasAnyRole(['dg', 'assistant_dg', 'comptable', 'chef_agence']) || (int) Auth::id() === $demande->demandeurId;

        $this->financeView('finance/fonds/show', 'Demande ' . $demande->numeroDemande . ' — LBP Finance', 'fonds', [
            'demande'      => $demande,
            'historique'   => $historique,
            'canValidate'  => $canValidate,
            'canDecaisser' => $canDecaisser,
            'canImputer'   => $canImputer,
        ]);
    }

    /**
     * Validation officielle par l'Assistante DG, le DG ou l'Admin.
     */
    public function valider(string $id): void
    {
        AuthMiddleware::check();

        if (!(Auth::isAdmin() || Auth::hasAnyRole(['dg', 'assistant_dg']))) {
            Session::flash('error', 'Accès refusé. Seule la Direction (Assistante DG, DG ou Administrateur) peut valider les demandes de fonds.');
            header('Location: ' . View::url('finance/fonds/' . $id));
            exit;
        }

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide (CSRF).');
            header('Location: ' . View::url('finance/fonds/' . $id));
            exit;
        }

        $demandeId = (int) $id;
        $commentaire = !empty($_POST['commentaire']) ? trim((string) $_POST['commentaire']) : 'Validation et autorisation de décaissement accordée.';

        $ok = $this->fondsRepo->validerDemande($demandeId, (int) Auth::id(), $commentaire);

        if ($ok) {
            Session::flash('success', 'La demande de fonds a été validée avec succès. Elle est maintenant transmise en Caisse pour décaissement.');
        } else {
            Session::flash('error', 'Impossible de valider cette demande (statut invalide ou déjà traitée).');
        }

        header('Location: ' . View::url('finance/fonds/' . $demandeId));
        exit;
    }

    /**
     * Rejet d'une demande par l'Assistante DG, le DG ou l'Admin.
     */
    public function rejeter(string $id): void
    {
        AuthMiddleware::check();

        if (!(Auth::isAdmin() || Auth::hasAnyRole(['dg', 'assistant_dg']))) {
            Session::flash('error', 'Accès refusé. Seule la Direction peut rejeter les demandes de fonds.');
            header('Location: ' . View::url('finance/fonds/' . $id));
            exit;
        }

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide (CSRF).');
            header('Location: ' . View::url('finance/fonds/' . $id));
            exit;
        }

        $demandeId = (int) $id;
        $motifRejet = trim((string) ($_POST['motif_rejet'] ?? 'Rejet par la Direction'));

        if (empty($motifRejet)) {
            Session::flash('error', 'Veuillez préciser le motif du rejet.');
            header('Location: ' . View::url('finance/fonds/' . $id));
            exit;
        }

        $ok = $this->fondsRepo->rejeterDemande($demandeId, (int) Auth::id(), $motifRejet);

        if ($ok) {
            Session::flash('warn', 'La demande de fonds a été rejetée.');
        } else {
            Session::flash('error', 'Impossible de rejeter cette demande.');
        }

        header('Location: ' . View::url('finance/fonds/' . $demandeId));
        exit;
    }

    /**
     * Sous-menu 2 : Prise en compte (Espace Caisse / Décaissements).
     */
    public function priseEnCompteIndex(): void
    {
        AuthMiddleware::check();
        RoleMiddleware::check(['admin', 'dg', 'assistant_dg', 'caissiere_principale', 'caissiere', 'chef_agence', 'comptable', 'superviseur_general']);

        $userAgId = Auth::agenceId();
        $isSuperUser = Auth::isAdmin() || Auth::hasAnyRole(['dg', 'assistant_dg', 'caissiere_principale', 'comptable', 'superviseur_general']);

        $selectedAgence = isset($_GET['agence_id']) && $_GET['agence_id'] !== '' ? (int) $_GET['agence_id'] : null;
        if (!$isSuperUser && $selectedAgence === null && $userAgId !== null && $userAgId > 0) {
            $selectedAgence = (int) $userAgId;
        }

        $filters = [
            'agence_id' => $selectedAgence,
            'statut'    => 'validee', // Demandes validées prêtes pour décaissement
            'cadre'     => trim((string) ($_GET['cadre'] ?? '')),
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to'   => trim((string) ($_GET['date_to'] ?? '')),
            'q'         => trim((string) ($_GET['q'] ?? '')),
        ];

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = $this->fondsRepo->paginateDemandes($filters, $page, 25);
        $agences = $this->fondsRepo->getAgences();

        $this->financeView('finance/fonds/prise_en_compte', 'Prise en Compte — Caisse & Décaissements', 'prise_en_compte', [
            'items'       => $result['items'],
            'total'       => $result['total'],
            'page'        => $result['page'],
            'totalPages'  => $result['totalPages'],
            'filters'     => $filters,
            'agences'     => $agences,
            'isSuperUser' => $isSuperUser,
        ]);
    }

    /**
     * Décaissement effectif en Caisse.
     */
    public function decaisser(string $id): void
    {
        AuthMiddleware::check();
        RoleMiddleware::check(['admin', 'dg', 'assistant_dg', 'caissiere_principale', 'caissiere', 'chef_agence']);

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide (CSRF).');
            header('Location: ' . View::url('finance/fonds/prise-en-compte'));
            exit;
        }

        $demandeId = (int) $id;
        $modePaiement = trim((string) ($_POST['mode_paiement'] ?? 'Espèces'));

        $ok = $this->fondsRepo->decaisserDemande($demandeId, (int) Auth::id(), $modePaiement);

        if ($ok) {
            Session::flash('success', "Décaissement enregistré avec succès ({$modePaiement}). Le Bon de Sortie de Caisse est prêt.");
            header('Location: ' . View::url('finance/fonds/' . $demandeId . '/bon-caisse-pdf'));
            exit;
        }

        Session::flash('error', 'Impossible de procéder au décaissement (statut invalide ou déjà décaissé).');
        header('Location: ' . View::url('finance/fonds/prise-en-compte'));
        exit;
    }

    /**
     * Sous-menu 3 : Imputation (Justificatifs & Restitution de reliquats).
     */
    public function imputationIndex(): void
    {
        AuthMiddleware::check();
        RoleMiddleware::check(['admin', 'dg', 'assistant_dg', 'comptable', 'chef_agence', 'caissiere_principale', 'superviseur_general', 'agent', 'suivi_recouvrement']);

        $userAgId = Auth::agenceId();
        $isSuperUser = Auth::isAdmin() || Auth::hasAnyRole(['dg', 'assistant_dg', 'caissiere_principale', 'comptable', 'superviseur_general']);

        $selectedAgence = isset($_GET['agence_id']) && $_GET['agence_id'] !== '' ? (int) $_GET['agence_id'] : null;
        if (!$isSuperUser && $selectedAgence === null && $userAgId !== null && $userAgId > 0) {
            $selectedAgence = (int) $userAgId;
        }

        // Par défaut, afficher les demandes décaissées qui nécessitent une imputation
        $currentStatut = trim((string) ($_GET['statut'] ?? 'decaissee'));

        $filters = [
            'agence_id' => $selectedAgence,
            'statut'    => $currentStatut,
            'cadre'     => trim((string) ($_GET['cadre'] ?? '')),
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to'   => trim((string) ($_GET['date_to'] ?? '')),
            'q'         => trim((string) ($_GET['q'] ?? '')),
        ];

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = $this->fondsRepo->paginateDemandes($filters, $page, 25);
        $agences = $this->fondsRepo->getAgences();

        $this->financeView('finance/fonds/imputation', 'Imputation & Décharge des Fonds — LBP Finance', 'imputation', [
            'items'         => $result['items'],
            'total'         => $result['total'],
            'page'          => $result['page'],
            'totalPages'    => $result['totalPages'],
            'filters'       => $filters,
            'agences'       => $agences,
            'isSuperUser'   => $isSuperUser,
            'currentStatut' => $currentStatut,
        ]);
    }

    /**
     * Enregistrement de l'imputation comptable et régularisation du reliquat.
     */
    public function imputer(string $id): void
    {
        AuthMiddleware::check();
        RoleMiddleware::check(['admin', 'dg', 'assistant_dg', 'comptable', 'chef_agence']);

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide (CSRF).');
            header('Location: ' . View::url('finance/fonds/imputation'));
            exit;
        }

        $demandeId = (int) $id;
        $montantReel = (float) ($_POST['montant_reel_depense'] ?? 0);
        $pieces = trim((string) ($_POST['pieces_justificatives'] ?? ''));
        $commentaires = trim((string) ($_POST['commentaires'] ?? ''));

        if ($montantReel <= 0) {
            Session::flash('error', 'Le montant réel dépensé doit être supérieur à zéro.');
            header('Location: ' . View::url('finance/fonds/' . $demandeId));
            exit;
        }

        $imputationData = [
            'montant_reel_depense'  => $montantReel,
            'pieces_justificatives' => $pieces !== '' ? $pieces : null,
            'commentaires'          => $commentaires !== '' ? $commentaires : null,
        ];

        $ok = $this->fondsRepo->imputerDemande($demandeId, $imputationData, (int) Auth::id());

        if ($ok) {
            Session::flash('success', 'L\'imputation a été enregistrée avec succès. Le dossier de fonds est désormais clôturé.');
        } else {
            Session::flash('error', 'Impossible d\'imputer cette demande (statut non décaissé ou déjà imputé).');
        }

        header('Location: ' . View::url('finance/fonds/' . $demandeId));
        exit;
    }

    /**
     * Génération & Impression du Bon de Sortie de Caisse officiel LBP en PDF.
     */
    public function exportBonCaissePdf(string $id): void
    {
        AuthMiddleware::check();

        $demandeId = (int) $id;
        $demande = $this->fondsRepo->findDemandeById($demandeId);

        if (!$demande) {
            Session::flash('error', 'Demande introuvable.');
            header('Location: ' . View::url('finance/fonds'));
            exit;
        }

        require BASE_PATH . '/views/finance/fonds/bon_sortie_pdf.php';
        exit;
    }

    /**
     * Suppression d'une demande de fonds (uniquement si encore en attente).
     */
    public function delete(string $id): void
    {
        AuthMiddleware::check();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide (CSRF).');
            header('Location: ' . View::url('finance/fonds'));
            exit;
        }

        $demandeId = (int) $id;
        $demande = $this->fondsRepo->findDemandeById($demandeId);

        if (!$demande) {
            Session::flash('error', 'Demande introuvable.');
            header('Location: ' . View::url('finance/fonds'));
            exit;
        }

        // Seul le demandeur ou un admin/DG peut supprimer une demande en attente
        $canDelete = Auth::isAdmin() || Auth::hasAnyRole(['dg', 'assistant_dg']) || (int) Auth::id() === $demande->demandeurId;

        if (!$canDelete) {
            Session::flash('error', 'Vous n\'avez pas l\'autorisation de supprimer cette demande.');
            header('Location: ' . View::url('finance/fonds'));
            exit;
        }

        $ok = $this->fondsRepo->deleteDemande($demandeId, (int) Auth::id());

        if ($ok) {
            Session::flash('success', 'La demande de fonds a été supprimée.');
        } else {
            Session::flash('error', 'Seules les demandes en attente de validation peuvent être supprimées.');
        }

        header('Location: ' . View::url('finance/fonds'));
        exit;
    }
}
