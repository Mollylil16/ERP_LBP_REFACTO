<?php

declare(strict_types=1);

namespace App\Controllers\Facturation;

use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Helpers\View;
use App\Repositories\Finance\FactureRepository;
use App\Security\PermissionEntityRegistry;
use PDO;

/**
 * Consultation & modification d'une facture verrouillée (Règle point 4 du cahier des charges) :
 * un agent standard ne peut plus modifier une facture après création, seul un Responsable/Admin
 * le peut, et toute modification est tracée dans factures_audit_log.
 */
final class FactureEditController extends FacturationBaseController
{
    private PDO $pdo;
    private FactureRepository $repo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
        $this->repo = new FactureRepository($this->pdo);
    }

    public function edit(int $id): void
    {
        AuthMiddleware::check();

        $facture = $this->repo->getDetailedById($id);
        if (!$facture) {
            Session::flash('error', 'Facture introuvable.');
            header('Location: ' . View::url('facturation/filtre'));
            exit;
        }

        if (!$this->canAccess((int) $facture['agence_id'])) {
            Session::flash('error', "Vous n'avez pas accès à cette facture (agence différente).");
            header('Location: ' . View::url('facturation/filtre'));
            exit;
        }

        $this->renderView('facturation/facture_edit', [
            'pageTitle' => 'Facture ' . $facture['numero_facture'],
            'facture' => $facture,
            'canModify' => $this->repo->canModify($id),
            'auditLog' => $this->repo->getAuditLog($id),
        ]);
    }

    public function update(int $id): void
    {
        AuthMiddleware::check();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide (CSRF). Veuillez réessayer.');
            header('Location: ' . View::url('facturation/factures/' . $id . '/modifier'));
            exit;
        }

        $facture = $this->repo->getDetailedById($id);
        if (!$facture) {
            Session::flash('error', 'Facture introuvable.');
            header('Location: ' . View::url('facturation/filtre'));
            exit;
        }

        if (!$this->canAccess((int) $facture['agence_id'])) {
            Session::flash('error', "Vous n'avez pas accès à cette facture (agence différente).");
            header('Location: ' . View::url('facturation/filtre'));
            exit;
        }

        // Rejet strict côté serveur : un agent standard ne peut jamais modifier une facture verrouillée,
        // même en contournant l'interface (défense en profondeur).
        if (!$this->repo->canModify($id)) {
            Session::flash('error', 'Cette facture est verrouillée. Seul un Responsable peut la modifier.');
            header('Location: ' . View::url('facturation/factures/' . $id . '/modifier'));
            exit;
        }

        $userId = Auth::id();
        if (!$userId) {
            header('Location: ' . View::url('login'));
            exit;
        }

        $montantTotal = isset($_POST['montant_total']) ? (float) $_POST['montant_total'] : (float) $facture['montant_total'];
        $montantEncaisse = isset($_POST['montant_encaisse']) ? (float) $_POST['montant_encaisse'] : (float) $facture['montant_encaisse'];
        $statut = $_POST['statut'] ?? $facture['statut'];
        if (!in_array($statut, ['emise', 'partiellement_payee', 'payee', 'annulee'], true)) {
            $statut = $facture['statut'];
        }

        $newFields = [
            'montant_total' => $montantTotal,
            'montant_encaisse' => $montantEncaisse,
            'montant_restant' => max(0.0, $montantTotal - $montantEncaisse),
            'statut' => $statut,
        ];

        $this->repo->updateWithAudit($id, $newFields, $userId);

        Session::flash('success', 'Facture mise à jour avec succès. Modification tracée dans le journal d\'audit.');
        header('Location: ' . View::url('facturation/factures/' . $id . '/modifier'));
        exit;
    }

    private function canAccess(int $factureAgenceId): bool
    {
        if (Auth::isAdmin()) {
            return true;
        }
        if (Auth::can(PermissionEntityRegistry::CONSULTER_TOUTES_FACTURES_TOUTES_AGENCES)) {
            return true;
        }
        if (Auth::isFacturationPrivileged()) {
            return true;
        }

        $userAgenceId = $_SESSION['user']['agence_id'] ?? null;
        return $userAgenceId !== null && (int) $userAgenceId === $factureAgenceId;
    }

    private function renderView(string $view, array $data): void
    {
        $dashService = new \App\Services\Shared\ModuleDashboardService();
        $module = $dashService->dashboard('facturation');

        $layoutData = [
            'pageTitle' => $data['pageTitle'] ?? 'Facturation',
            'moduleName' => 'Facturation',
            'moduleCode' => 'FAC',
            'activeModule' => 'factures',
            'moduleNavigation' => $module['items'] ?? [],
            'additionalStyles' => ['css/finea-ui.css'],
        ];

        $data = array_merge(\App\Support\ViewBag::defaults(), $layoutData, $data);
        extract($data, EXTR_SKIP);

        ob_start();
        require BASE_PATH . '/views/' . $view . '.php';
        $content = ob_get_clean();

        require BASE_PATH . '/views/layouts/module.php';
    }
}
