<?php

declare(strict_types=1);

namespace App\Controllers\PilotageDg;

use App\Controllers\BaseController;
use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Models\Database;
use App\Repositories\PilotageDg\SignalementTraitementRepository;
use App\Repositories\Rh\RhValidationRepository;
use RuntimeException;
use App\Repositories\PilotageDg\PilotageDgDashboardRepository;
use App\Services\PilotageDg\PilotageDgDashboardService;

final class PilotageDgDashboardController extends BaseController
{
    private PilotageDgDashboardService $service;

    public function __construct()
    {
        $this->service = new PilotageDgDashboardService(new PilotageDgDashboardRepository(Database::getConnection()));
    }

    public function index(): void
    {
        AuthMiddleware::check();
        RoleMiddleware::check(['dg', 'admin']);

        $module = $this->service->dashboard();

        $this->view('pilotage_dg/dashboard', $this->viewData($module) + [
            'dashboardModule' => $module,
        ]);
    }

    public function personnel(): void
    {
        AuthMiddleware::check();
        RoleMiddleware::check(['dg', 'admin']);

        $module = $this->service->moduleMeta();
        $supervision = $this->service->personnelSupervision();

        $this->view('pilotage_dg/personnel', $this->viewData($module, 'personnel') + [
            'employees' => $supervision['employees'],
            'topHonnetes' => $supervision['topHonnetes'] ?? [],
            'alerts' => $supervision['alerts'],
        ]);
    }

    public function validations(): void
    {
        AuthMiddleware::check();
        RoleMiddleware::check(['dg', 'admin']);

        $module = $this->service->moduleMeta();
        $pending = $this->service->pendingValidations();

        $this->view('pilotage_dg/validations', $this->viewData($module, 'validations') + [
            'workflows' => $pending['workflows'],
            'legalRequests' => $pending['legalRequests'],
            'paymentRequests' => $pending['paymentRequests'],
        ]);
    }

    public function anomalies(): void
    {
        AuthMiddleware::check();
        RoleMiddleware::check(['dg', 'admin']);

        $module = $this->service->moduleMeta();
        $anomalies = $this->service->anomalies();

        // Un signalement regularise ne doit plus remonter indefiniment : son
        // traitement est rattache a sa cle deterministe.
        $suivi = (new SignalementTraitementRepository(Database::getConnection()))
            ->enrichir($anomalies['signalements'] ?? []);

        $this->view('pilotage_dg/anomalies', $this->viewData($module, 'anomalies') + [
            'signalementsATraiter' => $suivi['aTraiter'],
            'signalementsTraites' => $suivi['traites'],
            'signalements' => $suivi['signalements'],
            'ecartsCaisse' => $anomalies['ecartsCaisse'],
            'agentsSuspects' => $anomalies['agentsSuspects'],
            'agencesImpayes' => $anomalies['agencesImpayes'],
            'colisSuspects' => $anomalies['colisSuspects'] ?? [],
            'rapprochementIndependant' => $anomalies['rapprochementIndependant'] ?? [],
        ]);
    }

    public function audit(): void
    {
        AuthMiddleware::check();
        RoleMiddleware::check(['dg', 'admin']);

        $module = $this->service->moduleMeta();
        $filters = [
            'entity_type' => $_GET['entity_type'] ?? '',
            'user_id' => $_GET['user_id'] ?? '',
            'start_date' => $_GET['start_date'] ?? '',
            'end_date' => $_GET['end_date'] ?? '',
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = $this->service->auditLog($filters, $page);

        $this->view('pilotage_dg/audit', $this->viewData($module, 'audit') + [
            'logs' => $result['logs'],
            'entityTypes' => $result['entityTypes'],
            'pagination' => $result['pagination'],
            'filters' => $filters,
        ]);
    }

    /**
     * Enregistre le traitement d'un signalement : vu, traite, classe sans suite,
     * ou reouverture.
     */
    public function traiterSignalement(): void
    {
        AuthMiddleware::check();
        RoleMiddleware::check(['dg', 'admin']);

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide.');
            $this->redirect('/pilotage-dg/anomalies');
            return;
        }

        $cle = trim((string) ($_POST['cle'] ?? ''));
        $statut = trim((string) ($_POST['statut'] ?? ''));
        $commentaire = trim((string) ($_POST['commentaire'] ?? ''));

        if ($cle === '') {
            Session::flash('error', 'Signalement introuvable.');
            $this->redirect('/pilotage-dg/anomalies');
            return;
        }

        $depot = new SignalementTraitementRepository(Database::getConnection());

        if ($statut === 'rouvrir') {
            $depot->rouvrir($cle);
            Session::flash('success', 'Signalement rouvert.');
        } elseif (in_array($statut, SignalementTraitementRepository::STATUTS, true)) {
            $depot->marquer($cle, $statut, (int) Auth::id(), $commentaire !== '' ? $commentaire : null);
            Session::flash('success', match ($statut) {
                'traite' => 'Signalement marqué comme traité.',
                'classe' => 'Signalement classé sans suite.',
                default => 'Signalement marqué comme vu.',
            });
        } else {
            Session::flash('error', 'Action inconnue.');
        }

        $this->redirect('/pilotage-dg/anomalies');
    }

    /**
     * Approuver ou rejeter un workflow RH sans quitter le Centre de Validation.
     * La decision est deleguee a RhValidationRepository : meme logique, meme tracabilite
     * que depuis le module RH.
     */
    public function decideWorkflow(string $id): void
    {
        $this->decide(
            fn(RhValidationRepository $repo, int $recordId, string $decision) => $repo->decideWorkflow($recordId, $decision, (int) Auth::id()),
            $id,
            'Le workflow a été mis à jour.'
        );
    }

    /**
     * Approuver ou rejeter une demande legale d'employe depuis le Centre de Validation.
     */
    public function decideLegalRequest(string $id): void
    {
        $comment = trim((string) ($_POST['comment'] ?? ''));

        $this->decide(
            fn(RhValidationRepository $repo, int $recordId, string $decision) => $repo->decideEmployeeRequest($recordId, $decision, (int) Auth::id(), $comment !== '' ? $comment : null),
            $id,
            'La demande a été mise à jour.'
        );
    }

    /**
     * Socle commun des decisions : authentification, habilitation, CSRF, puis delegation.
     */
    private function decide(callable $action, string $id, string $successMessage): void
    {
        AuthMiddleware::check();
        RoleMiddleware::check(['dg', 'admin']);

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expirée ou requête invalide. Veuillez réessayer.');
            $this->redirect('/pilotage-dg/validations');
            return;
        }

        $decision = (string) ($_POST['decision'] ?? '');
        if ($decision !== 'approve' && $decision !== 'reject') {
            Session::flash('error', 'Décision invalide.');
            $this->redirect('/pilotage-dg/validations');
            return;
        }

        try {
            $action(new RhValidationRepository(Database::getConnection()), (int) $id, $decision);
            Session::flash('success', $successMessage);
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            Session::flash('error', 'La décision n\'a pas pu être enregistrée.');
        }

        $this->redirect('/pilotage-dg/validations');
    }

    /**
     * @param array<string,mixed> $module
     * @return array<string,mixed>
     */
    private function viewData(array $module, string $activeKey = 'dashboard'): array
    {
        return [
            'pageTitle' => 'Tableau de bord ' . (string) $module['label'],
            'moduleName' => (string) $module['label'],
            'moduleCode' => (string) $module['code'],
            'moduleTheme' => $module,
            'activeModule' => $activeKey,
            'moduleNavigation' => (array) $module['navigation'],
            'additionalStyles' => ['css/finea-ui.css'],
        ];
    }
}
