<?php

declare(strict_types=1);

namespace App\Controllers\Rh;

use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\Rh\RhValidationRepository;
use App\View\Pages\Rh\ValidationIndexPage;
use RuntimeException;

final class RhValidationController extends RhBaseController
{
    private RhValidationRepository $repository;

    public function __construct()
    {
        $this->repository = new RhValidationRepository(Database::getConnection());
    }

    public function index(): void
    {
        AuthMiddleware::check();
        $tab = (string) ($_GET['tab'] ?? 'pending');
        if (!in_array($tab, ['pending', 'approved', 'rejected', 'cancelled', 'all'], true)) {
            $tab = 'pending';
        }

        $requests = $this->repository->getEmployeeRequests($tab);
        $workflows = $this->repository->getPendingWorkflows();

        $this->rhView('rh/validations/index', 'Validations RH', 'validations', [
            'page' => new ValidationIndexPage($requests, $workflows),
            'tab' => $tab,
        ]);
    }

    public function decideRequest(string $id): void
    {
        AuthMiddleware::check();
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expiree. Veuillez recommencer.');
            $this->redirect('/rh/validations');
        }

        try {
            $decision = (string) ($_POST['decision'] ?? '');
            $comment = (string) ($_POST['comment'] ?? '');
            $this->repository->decideEmployeeRequest((int) $id, $decision, (int) Auth::id(), $comment);
            Session::flash('success', 'La demande a ete mise a jour.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect('/rh/validations');
    }

    public function decideWorkflow(string $id): void
    {
        AuthMiddleware::check();
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expiree. Veuillez recommencer.');
            $this->redirect('/rh/validations');
        }

        try {
            $decision = (string) ($_POST['decision'] ?? '');
            $this->repository->decideWorkflow((int) $id, $decision, (int) Auth::id());
            Session::flash('success', 'Le workflow a ete mis a jour.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect('/rh/validations');
    }
}
