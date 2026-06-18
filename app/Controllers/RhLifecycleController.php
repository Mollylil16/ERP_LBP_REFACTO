<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\RhLifecycleRepository;
use App\Services\RhLifecycleService;
use RuntimeException;

class RhLifecycleController extends BaseController
{
    private RhLifecycleService $service;

    public function __construct()
    {
        $this->service = new RhLifecycleService(new RhLifecycleRepository(Database::getConnection()));
    }

    public function index(): void
    {
        AuthMiddleware::check();
        $section = (string) ($_GET['section'] ?? 'contracts');
        if (!in_array($section, ['contracts', 'assignments', 'evaluations', 'trainings', 'workflows', 'organization', 'recruitment', 'discipline'], true)) {
            $section = 'contracts';
        }
        $this->view('rh/lifecycle/index', [
            'pageTitle' => 'Cycle de vie RH',
            'moduleName' => 'Ressources humaines',
            'moduleCode' => 'RH',
            'activeModule' => $section,
            'section' => $section,
            'csrfToken' => Csrf::token(),
            'additionalStyles' => ['css/finea-ui.css', 'css/rh.css'],
            'additionalScripts' => ['js/rh.js'],
        ] + $this->service->dashboard());
    }

    public function storeContract(): void { $this->execute(fn() => $this->service->createContract($_POST, (int) Auth::id()), 'Contrat créé et transmis au workflow.', 'contracts'); }
    public function storeAssignment(): void { $this->execute(fn() => $this->service->createAssignment($_POST, (int) Auth::id()), 'Mission créée et transmise au workflow.', 'assignments'); }
    public function storeEvaluation(): void { $this->execute(fn() => $this->service->createEvaluation($_POST, (int) Auth::id()), 'Évaluation planifiée.', 'evaluations'); }
    public function storeTraining(): void { $this->execute(fn() => $this->service->createTraining($_POST, (int) Auth::id()), 'Session de formation créée.', 'trainings'); }
    public function storeDiscipline(): void { $this->execute(fn() => $this->service->createDisciplinaryAction($_POST, (int) Auth::id()), 'Mesure disciplinaire enregistrée.', 'discipline'); }
    public function decideWorkflow(string $id): void { $this->execute(fn() => $this->service->decideWorkflow((int) $id, (string) ($_POST['decision'] ?? ''), (int) Auth::id()), 'Workflow mis à jour.', 'workflows'); }
    public function decideEmployeeRequest(string $id): void { $this->execute(fn() => $this->service->decideEmployeeRequest((int) $id, (string) ($_POST['decision'] ?? ''), (int) Auth::id(), $_POST['comment'] ?? null), 'Demande employé mise à jour.', 'workflows'); }

    private function execute(callable $action, string $success, string $section): void
    {
        AuthMiddleware::check();
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Jeton CSRF invalide.');
            $this->redirect('/rh/cycle-vie?section=' . $section);
        }
        try {
            $action();
            Session::flash('success', $success);
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/rh/cycle-vie?section=' . $section);
    }
}
