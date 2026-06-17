<?php

namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\FinanceRepository;
use Exception;

final class FinanceController extends BaseController
{
    private FinanceRepository $repository;

    public function __construct()
    {
        $this->repository = new FinanceRepository(Database::getConnection());
    }

    private function getAgencyId(): int
    {
        $user = Auth::user();
        if ($user && $user->rhEmployeeId) {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT site_id FROM rh_employees WHERE id = :id");
            $stmt->execute(['id' => $user->rhEmployeeId]);
            $siteId = $stmt->fetchColumn();
            if ($siteId) {
                return (int) $siteId;
            }
        }
        return 1; // Default to Siege Abidjan
    }

    public function dashboard(): void
    {
        AuthMiddleware::check();
        
        $agencyId = $this->getAgencyId();
        $caisse = $this->repository->getOrCreateCaisse($agencyId);
        
        // Fetch stats/lists
        $mouvements = $this->repository->getMouvements((int)$caisse['id'], 5);
        $invoices = $this->repository->getClientInvoices($agencyId);
        $facturesPrestataires = $this->repository->getFacturesPrestataires();
        
        // Calculate basic KPIs
        $unpaidClient = 0.0;
        foreach ($invoices as $inv) {
            if ($inv['status'] !== 'PAYEE') {
                $unpaidClient += ((float)$inv['amount_ttc'] - (float)$inv['paid_amount']);
            }
        }
        
        $unpaidPrestataires = 0.0;
        foreach ($facturesPrestataires as $fp) {
            if ($fp['status'] !== 'PAYEE') {
                $unpaidPrestataires += ((float)$fp['amount'] - (float)$fp['amount_paid']);
            }
        }

        $this->view('finance/dashboard', $this->viewData('Tableau de bord Finance', 'dashboard') + [
            'caisse' => $caisse,
            'mouvements' => $mouvements,
            'unpaidClient' => $unpaidClient,
            'unpaidPrestataires' => $unpaidPrestataires,
            'invoicesCount' => count($invoices),
        ]);
    }

    public function facturesIndex(): void
    {
        AuthMiddleware::check();
        $agencyId = $this->getAgencyId();
        $invoices = $this->repository->getClientInvoices($agencyId);

        $this->view('finance/factures/index', $this->viewData('Règlements Clients', 'factures') + [
            'invoices' => $invoices,
        ]);
    }

    public function factureShow(int $id): void
    {
        AuthMiddleware::check();
        
        // Find specific invoice globally
        $invoices = $this->repository->getClientInvoices(null);
        $invoice = null;
        foreach ($invoices as $inv) {
            if ((int)$inv['id'] === $id) {
                $invoice = $inv;
                break;
            }
        }
        
        if (!$invoice) {
            Session::flash('error', 'Facture introuvable.');
            $this->redirect('/finance/factures');
        }

        // Get past payments
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT p.*, u.full_name as recorder_name
            FROM lbp_paiements p
            LEFT JOIN users u ON p.recorded_by = u.id
            WHERE p.invoice_id = :invoice_id
            ORDER BY p.created_at DESC
        ");
        $stmt->execute(['invoice_id' => $id]);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->view('finance/factures/show', $this->viewData('Détails Facture Client', 'factures') + [
            'invoice' => $invoice,
            'payments' => $payments,
        ]);
    }

    public function imprimerFacture(int $id): void
    {
        AuthMiddleware::check();
        
        $invoices = $this->repository->getClientInvoices(null);
        $invoice = null;
        foreach ($invoices as $inv) {
            if ((int)$inv['id'] === $id) {
                $invoice = $inv;
                break;
            }
        }
        
        if (!$invoice) {
            Session::flash('error', 'Facture introuvable.');
            $this->redirect('/finance/factures');
        }

        $db = Database::getConnection();
        $stmtColis = $db->prepare("
            SELECT c.*, 
                   s.name as sender_name, s.phone as sender_phone,
                   r.name as receiver_name, r.phone as receiver_phone,
                   da.name as departure_agency, da.phone as departure_phone,
                   aa.name as arrival_agency, aa.phone as arrival_phone, aa.country as arrival_country, aa.city as arrival_city
            FROM lbp_colis c
            LEFT JOIN crm_clients s ON c.sender_id = s.id
            LEFT JOIN crm_clients r ON c.receiver_id = r.id
            LEFT JOIN company_sites da ON c.departure_agency_id = da.id
            LEFT JOIN company_sites aa ON c.arrival_agency_id = aa.id
            WHERE c.tracking_number = :ref
        ");
        $stmtColis->execute(['ref' => $invoice['reference']]);
        $colis = $stmtColis->fetch(\PDO::FETCH_ASSOC);

        $marchandises = [];
        if ($colis) {
            $stmtM = $db->prepare("SELECT * FROM lbp_marchandises WHERE colis_id = :colis_id ORDER BY id");
            $stmtM->execute(['colis_id' => $colis['id']]);
            $marchandises = $stmtM->fetchAll(\PDO::FETCH_ASSOC);
        }

        require BASE_PATH . '/views/finance/factures/imprimer.php';
    }

    public function storePaiement(int $id): void
    {
        AuthMiddleware::check();
        
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée. Veuillez recommencer.');
            $this->redirect("/finance/factures/{$id}");
        }

        $amount = (float)($_POST['amount'] ?? 0);
        $method = $_POST['payment_method'] ?? '';
        $reference = $_POST['reference'] ?? '';
        $userId = Auth::id() ?: 1;
        $agencyId = $this->getAgencyId();
        $caisse = $this->repository->getOrCreateCaisse($agencyId);

        if ($caisse['status'] !== 'OUVERTE') {
            Session::flash('error', 'La caisse est actuellement FERMEE. Ouvrez-la avant de recevoir un règlement.');
            $this->redirect("/finance/factures/{$id}");
        }

        if ($amount <= 0) {
            Session::flash('error', 'Veuillez saisir un montant valide.');
            $this->redirect("/finance/factures/{$id}");
        }

        try {
            $this->repository->storePaiement($id, $amount, $method, $reference, $userId, (int)$caisse['id']);
            Session::flash('success', 'Règlement enregistré avec succès.');
            $this->redirect("/finance/factures/{$id}");
        } catch (Exception $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect("/finance/factures/{$id}");
        }
    }

    public function decaissementsIndex(): void
    {
        AuthMiddleware::check();
        $factures = $this->repository->getFacturesPrestataires();

        $this->view('finance/decaissements/index', $this->viewData('Décaissements Prestataires', 'decaissements') + [
            'factures' => $factures,
        ]);
    }

    public function storeDecaissementPrestataire(int $id): void
    {
        AuthMiddleware::check();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée. Veuillez recommencer.');
            $this->redirect('/finance/decaissements');
        }

        $amount = (float)($_POST['amount'] ?? 0);
        $reference = $_POST['reference'] ?? '';
        $userId = Auth::id() ?: 1;
        $agencyId = $this->getAgencyId();
        $caisse = $this->repository->getOrCreateCaisse($agencyId);

        if ($caisse['status'] !== 'OUVERTE') {
            Session::flash('error', 'La caisse est actuellement FERMEE. Ouvrez-la avant d\'effectuer un décaissement.');
            $this->redirect('/finance/decaissements');
        }

        if ($amount <= 0) {
            Session::flash('error', 'Veuillez saisir un montant de paiement valide.');
            $this->redirect('/finance/decaissements');
        }

        if ($caisse['balance'] < $amount) {
            Session::flash('error', 'Solde de caisse insuffisant pour cette dépense.');
            $this->redirect('/finance/decaissements');
        }

        try {
            $this->repository->storeDecaissementPrestataire($id, $amount, $reference, $userId, (int)$caisse['id']);
            Session::flash('success', 'Décaissement enregistré.');
            $this->redirect('/finance/decaissements');
        } catch (Exception $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/finance/decaissements');
        }
    }

    public function creditsIndex(): void
    {
        AuthMiddleware::check();
        $agencyId = $this->getAgencyId();
        $credits = $this->repository->getCredits($agencyId);

        $this->view('finance/credits/index', $this->viewData('Crédits Inter-Agences', 'credits') + [
            'credits' => $credits,
            'agencyId' => $agencyId,
        ]);
    }

    public function createCredit(): void
    {
        AuthMiddleware::check();
        $agencies = $this->repository->getAgencies();
        $agencyId = $this->getAgencyId();

        $this->view('finance/credits/form', $this->viewData('Nouveau Crédit Inter-Agence', 'credits') + [
            'agencies' => $agencies,
            'currentAgencyId' => $agencyId,
        ]);
    }

    public function storeCredit(): void
    {
        AuthMiddleware::check();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée. Veuillez recommencer.');
            $this->redirect('/finance/credits/nouveau');
        }

        $toAgencyId = (int)($_POST['to_agency_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        $reason = $_POST['reason'] ?? '';
        $fromAgencyId = $this->getAgencyId();
        $userId = Auth::id() ?: 1;

        $caisseFrom = $this->repository->getOrCreateCaisse($fromAgencyId);

        if ($caisseFrom['status'] !== 'OUVERTE') {
            Session::flash('error', 'La caisse de l\'agence émettrice est FERMEE.');
            $this->redirect('/finance/credits/nouveau');
        }

        if ($toAgencyId === $fromAgencyId) {
            Session::flash('error', 'L\'agence destinatrice doit être différente.');
            $this->redirect('/finance/credits/nouveau');
        }

        if ($amount <= 0) {
            Session::flash('error', 'Montant invalide.');
            $this->redirect('/finance/credits/nouveau');
        }

        if ($caisseFrom['balance'] < $amount) {
            Session::flash('error', 'Solde de caisse insuffisant pour effectuer ce transfert de crédit.');
            $this->redirect('/finance/credits/nouveau');
        }

        try {
            $this->repository->storeCredit($fromAgencyId, $toAgencyId, $amount, $reason, $userId);
            Session::flash('success', 'Crédit inter-agence transféré avec succès.');
            $this->redirect('/finance/credits');
        } catch (Exception $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/finance/credits/nouveau');
        }
    }

    public function apurerCredit(int $id): void
    {
        AuthMiddleware::check();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée.');
            $this->redirect('/finance/credits');
        }

        $userId = Auth::id() ?: 1;

        try {
            $this->repository->apurerCredit($id, $userId);
            Session::flash('success', 'Crédit inter-agence apuré (fonds reçus).');
            $this->redirect('/finance/credits');
        } catch (Exception $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/finance/credits');
        }
    }

    public function caisseShow(): void
    {
        AuthMiddleware::check();
        $agencyId = $this->getAgencyId();
        $caisse = $this->repository->getOrCreateCaisse($agencyId);
        $mouvements = $this->repository->getMouvements((int)$caisse['id']);

        $this->view('finance/caisse/show', $this->viewData('Journal de Caisse', 'caisse') + [
            'caisse' => $caisse,
            'mouvements' => $mouvements,
        ]);
    }

    public function storeAppro(): void
    {
        AuthMiddleware::check();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée.');
            $this->redirect('/finance/caisse');
        }

        $amount = (float)($_POST['amount'] ?? 0);
        $justification = $_POST['justification'] ?? '';
        $agencyId = $this->getAgencyId();
        $caisse = $this->repository->getOrCreateCaisse($agencyId);
        $userId = Auth::id() ?: 1;

        if ($amount <= 0) {
            Session::flash('error', 'Montant invalide.');
            $this->redirect('/finance/caisse');
        }

        try {
            // Un approvisionnement force l'ouverture de la caisse si fermée
            if ($caisse['status'] === 'FERMEE') {
                $db = Database::getConnection();
                $db->prepare("UPDATE lbp_caisses SET status = 'OUVERTE' WHERE id = :id")->execute(['id' => $caisse['id']]);
            }
            $this->repository->storeMouvement((int)$caisse['id'], 'APPRO', $amount, $justification, $userId);
            Session::flash('success', 'Approvisionnement de caisse enregistré.');
            $this->redirect('/finance/caisse');
        } catch (Exception $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/finance/caisse');
        }
    }

    public function storeDecaissementMouvement(): void
    {
        AuthMiddleware::check();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée.');
            $this->redirect('/finance/caisse');
        }

        $amount = (float)($_POST['amount'] ?? 0);
        $justification = $_POST['justification'] ?? '';
        $agencyId = $this->getAgencyId();
        $caisse = $this->repository->getOrCreateCaisse($agencyId);
        $userId = Auth::id() ?: 1;

        if ($caisse['status'] !== 'OUVERTE') {
            Session::flash('error', 'La caisse est FERMEE.');
            $this->redirect('/finance/caisse');
        }

        if ($amount <= 0) {
            Session::flash('error', 'Montant invalide.');
            $this->redirect('/finance/caisse');
        }

        if ($caisse['balance'] < $amount) {
            Session::flash('error', 'Solde insuffisant pour ce décaissement.');
            $this->redirect('/finance/caisse');
        }

        try {
            $this->repository->storeMouvement((int)$caisse['id'], 'DECAISSEMENT', $amount, $justification, $userId);
            Session::flash('success', 'Décaissement de caisse enregistré.');
            $this->redirect('/finance/caisse');
        } catch (Exception $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/finance/caisse');
        }
    }

    public function cloturesIndex(): void
    {
        AuthMiddleware::check();
        $agencyId = $this->getAgencyId();
        $caisse = $this->repository->getOrCreateCaisse($agencyId);
        $clotures = $this->repository->getClotures((int)$caisse['id']);

        $this->view('finance/clotures/index', $this->viewData('Clôtures de Caisse', 'clotures') + [
            'caisse' => $caisse,
            'clotures' => $clotures,
        ]);
    }

    public function storeCloture(): void
    {
        AuthMiddleware::check();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée.');
            $this->redirect('/finance/clotures');
        }

        $declared = (float)($_POST['declared_balance'] ?? 0);
        $agencyId = $this->getAgencyId();
        $caisse = $this->repository->getOrCreateCaisse($agencyId);
        $userId = Auth::id() ?: 1;

        if ($caisse['status'] !== 'OUVERTE') {
            Session::flash('error', 'La caisse est déjà fermée ou en attente de validation.');
            $this->redirect('/finance/clotures');
        }

        try {
            $this->repository->storeCloture((int)$caisse['id'], $declared, (float)$caisse['balance'], $userId);
            Session::flash('success', 'Point de caisse soumis avec succès pour validation.');
            $this->redirect('/finance/clotures');
        } catch (Exception $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/finance/clotures');
        }
    }

    public function validerCloture(int $id): void
    {
        AuthMiddleware::check();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée.');
            $this->redirect('/finance/clotures');
        }

        $status = $_POST['status'] ?? '';
        $reason = $_POST['rejection_reason'] ?? '';
        $userId = Auth::id() ?: 1;

        if (!in_array($status, ['VALIDE', 'REJETE'])) {
            Session::flash('error', 'Action invalide.');
            $this->redirect('/finance/clotures');
        }

        try {
            $this->repository->validerCloture($id, $status, $reason, $userId);
            Session::flash('success', 'Clôture de caisse traitée avec succès.');
            $this->redirect('/finance/clotures');
        } catch (Exception $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/finance/clotures');
        }
    }

    private function viewData(string $pageTitle, string $activeSubModule): array
    {
        $base = '/finance';
        $moduleNavigation = [
            ['key' => 'dashboard', 'label' => 'Tableau de bord', 'icon' => 'DB', 'url' => $base . '/dashboard', 'available' => true],
            ['key' => 'caisse', 'label' => 'Journal de Caisse', 'icon' => 'CS', 'url' => $base . '/caisse', 'available' => true],
            ['key' => 'factures', 'label' => 'Règlements Clients', 'icon' => 'FAC', 'url' => $base . '/factures', 'available' => true],
            ['key' => 'decaissements', 'label' => 'Paiements Prestataires', 'icon' => 'DEC', 'url' => $base . '/decaissements', 'available' => true],
            ['key' => 'credits', 'label' => 'Crédits Inter-Agences', 'icon' => 'CRD', 'url' => $base . '/credits', 'available' => true],
            ['key' => 'clotures', 'label' => 'Points de Caisse', 'icon' => 'CLT', 'url' => $base . '/clotures', 'available' => true],
        ];

        return [
            'pageTitle' => $pageTitle,
            'moduleName' => 'Finance & Caisse',
            'moduleCode' => 'FIN',
            'activeModule' => $activeSubModule,
            'additionalStyles' => ['css/finea-ui.css'],
            'moduleNavigation' => $moduleNavigation,
        ];
    }
}
