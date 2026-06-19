<?php

namespace App\Controllers\Rh;

use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Middleware\AuthMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Models\Database;
use App\Repositories\Rh\RhPersonnelRepository;
use App\Security\OperationPolicy;
use App\Security\PermissionAction;
use App\Security\PermissionEntityRegistry;
use App\Services\Rh\RhPersonnelService;
use App\View\Pages\Rh\PersonnelExitPage;
use App\View\Pages\Rh\PersonnelFormPage;
use App\View\Pages\Rh\PersonnelIndexPage;
use App\View\Pages\Rh\PersonnelMutationPage;
use App\View\Pages\Rh\PersonnelRegisterPage;
use App\View\Pages\Rh\PersonnelShowPage;
use RuntimeException;

class RhPersonnelController extends RhBaseController
{
    private RhPersonnelService $service;

    public function __construct()
    {
        $this->service = new RhPersonnelService(
            new RhPersonnelRepository(Database::getConnection())
        );
    }

    public function index(): void
    {
        AuthMiddleware::check();
        $data = $this->service->list($_GET);
        $this->rhView('rh/personnel/index', 'Liste du personnel', 'personnel', [
            'page' => new PersonnelIndexPage($data, [
                'view' => Auth::canOperation(OperationPolicy::RH_EMPLOYEE_VIEW),
                'create' => Auth::canOperation(OperationPolicy::RH_EMPLOYEE_CREATE),
                'update' => Auth::canOperation(OperationPolicy::RH_EMPLOYEE_UPDATE),
                'mutate' => Auth::canOperation(OperationPolicy::RH_MUTATION_CREATE),
            ]),
        ]);
    }

    public function create(): void
    {
        PermissionMiddleware::checkOperation(OperationPolicy::RH_EMPLOYEE_CREATE);
        $this->rhView('rh/personnel/form', 'Integrer un collaborateur', 'personnel', [
            'page' => new PersonnelFormPage(
                [],
                $this->service->options(),
                $this->service->restrictedTables(),
                'Integrer un collaborateur',
                '/rh/personnel',
                'Creer le dossier',
            ),
        ]);
    }

    public function mutationsIndex(): void
    {
        AuthMiddleware::check();
        $this->rhView(
            'rh/personnel/mutations-index',
            'Registre des mutations',
            'mutations',
            [
                'page' => new PersonnelRegisterPage($this->service->mutationRegister(), 'mutations'),
            ]
        );
    }

    public function movementsIndex(): void
    {
        AuthMiddleware::check();
        $this->rhView(
            'rh/personnel/movements-index',
            'Entrees et sorties',
            'sorties',
            [
                'page' => new PersonnelRegisterPage($this->service->movementRegister(), 'movements'),
            ]
        );
    }

    public function store(): void
    {
        $this->guardOperation(OperationPolicy::RH_EMPLOYEE_CREATE);
        try {
            $id = $this->service->create($_POST, $_FILES, (int) Auth::id());
            Session::flash('success', 'Le collaborateur a ete integre avec succes.');
            $this->redirect('/rh/personnel/' . $id);
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->back();
        }
    }

    public function show(string $id): void
    {
        PermissionMiddleware::checkOperation(OperationPolicy::RH_EMPLOYEE_VIEW);
        try {
            $this->rhView('rh/personnel/show', 'Dossier personnel', 'personnel', [
                'page' => new PersonnelShowPage($this->service->dossier((int) $id), [
                    'update' => Auth::canOperation(OperationPolicy::RH_EMPLOYEE_UPDATE),
                    'mutate' => Auth::canOperation(OperationPolicy::RH_MUTATION_CREATE),
                    'exit' => Auth::canOperation(OperationPolicy::RH_EXIT_MANAGE),
                    'viewHistory' => Auth::can(
                        PermissionEntityRegistry::RH_EMPLOYEE_HISTORY,
                        PermissionAction::VIEW
                    ),
                    'addHistory' => Auth::canOperation(OperationPolicy::RH_HISTORY_CREATE),
                ]),
            ]);
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/rh/personnel');
        }
    }

    public function edit(string $id): void
    {
        PermissionMiddleware::checkOperation(OperationPolicy::RH_EMPLOYEE_UPDATE);
        try {
            $dossier = $this->service->dossier((int) $id);
            $this->rhView('rh/personnel/form', 'Modifier le dossier', 'personnel', [
                'page' => new PersonnelFormPage(
                    $dossier['employee'],
                    $dossier['options'],
                    $dossier['restrictedTables'],
                    'Modifier le dossier',
                    '/rh/personnel/' . (int) $id . '/modifier',
                    'Enregistrer les modifications',
                ),
            ]);
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/rh/personnel');
        }
    }

    public function update(string $id): void
    {
        $this->guardOperation(OperationPolicy::RH_EMPLOYEE_UPDATE);
        try {
            $this->service->update((int) $id, $_POST, $_FILES);
            Session::flash('success', 'Le dossier personnel a ete mis a jour.');
            $this->redirect('/rh/personnel/' . (int) $id);
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->back();
        }
    }

    public function mutation(string $id): void
    {
        PermissionMiddleware::checkOperation(OperationPolicy::RH_MUTATION_CREATE);
        try {
            $this->rhView('rh/personnel/mutation', 'Mutation du personnel', 'mutations', [
                'page' => new PersonnelMutationPage($this->service->dossier((int) $id)),
            ]);
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/rh/personnel');
        }
    }

    public function applyMutation(string $id): void
    {
        $this->guardOperation(OperationPolicy::RH_MUTATION_CREATE);
        try {
            $this->service->mutate((int) $id, $_POST, (int) Auth::id());
            Session::flash('success', 'La mutation a ete enregistree et historisee.');
            $this->redirect('/rh/personnel/' . (int) $id);
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->back();
        }
    }

    public function exit(string $id): void
    {
        PermissionMiddleware::checkOperation(OperationPolicy::RH_EXIT_MANAGE);
        try {
            $this->rhView('rh/personnel/exit', 'Sortie du personnel', 'sorties', [
                'page' => new PersonnelExitPage($this->service->dossier((int) $id)),
            ]);
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/rh/personnel');
        }
    }

    public function applyExit(string $id): void
    {
        $this->guardOperation(OperationPolicy::RH_EXIT_MANAGE);
        try {
            $this->service->exit((int) $id, $_POST, (int) Auth::id());
            Session::flash('success', 'La sortie du collaborateur a ete enregistree.');
            $this->redirect('/rh/personnel/' . (int) $id);
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->back();
        }
    }

    public function reintegrate(string $id): void
    {
        $this->guardOperation(OperationPolicy::RH_EXIT_MANAGE);
        try {
            $this->service->reintegrate((int) $id, $_POST, (int) Auth::id());
            Session::flash('success', 'Le collaborateur a ete reintegre.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/rh/personnel/' . (int) $id);
    }

    public function addHistory(string $id): void
    {
        $this->guardOperation(OperationPolicy::RH_HISTORY_CREATE);
        try {
            $this->service->addHistory((int) $id, $_POST, (int) Auth::id());
            Session::flash('success', 'L’evenement RH a ete ajoute.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/rh/personnel/' . (int) $id);
    }

    private function guardOperation(string $operation): void
    {
        PermissionMiddleware::checkOperation($operation);
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Jeton CSRF invalide.');
            $this->back();
        }
    }

}
