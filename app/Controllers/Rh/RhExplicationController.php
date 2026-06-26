<?php

declare(strict_types=1);

namespace App\Controllers\Rh;

use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\Rh\RhExplicationRepository;
use App\View\Pages\Rh\ExplicationIndexPage;
use RuntimeException;

final class RhExplicationController extends RhBaseController
{
    private RhExplicationRepository $repository;

    public function __construct()
    {
        $this->repository = new RhExplicationRepository(Database::getConnection());
    }

    public function index(): void
    {
        AuthMiddleware::check();
        $explications = $this->repository->all();
        $employees = $this->repository->getActiveEmployees();

        // Get filter tab
        $tab = (string) ($_GET['tab'] ?? 'open');
        if (!in_array($tab, ['open', 'closed', 'cancelled', 'all'], true)) {
            $tab = 'open';
        }

        // Apply filtering in PHP
        $filtered = [];
        $countSurveillance = 0;
        $countDecision = 0;

        foreach ($explications as $exp) {
            $status = (string)($exp['status'] ?? 'pending_response');
            
            // Compute metrics based on ALL items
            if ($status === 'pending_response' || $status === 'complement_requested') {
                $countSurveillance++;
            } elseif ($status === 'responded') {
                $countDecision++;
            }

            // Filter for list
            if ($tab === 'open' && ($status === 'closed' || $status === 'cancelled')) {
                continue;
            }
            if ($tab === 'closed' && $status !== 'closed') {
                continue;
            }
            if ($tab === 'cancelled' && $status !== 'cancelled') {
                continue;
            }
            $filtered[] = $exp;
        }

        $this->rhView('rh/explications/index', 'Demandes d\'explications', 'explications', [
            'page' => new ExplicationIndexPage($filtered, $employees),
            'tab' => $tab,
            'metrics' => [
                'flux' => count($filtered),
                'surveillance' => $countSurveillance,
                'decision' => $countDecision
            ]
        ]);
    }

    public function store(): void
    {
        AuthMiddleware::check();
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expiree. Veuillez recommencer.');
            $this->redirect('/rh/explications');
        }

        try {
            $this->repository->create($_POST, (int) Auth::id());
            Session::flash('success', 'Demande d\'explication emise.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect('/rh/explications');
    }

    public function respond(string $id): void
    {
        AuthMiddleware::check();
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expiree. Veuillez recommencer.');
            $this->redirect('/rh/explications');
        }

        try {
            $response = trim((string) ($_POST['response'] ?? ''));
            if ($response === '') {
                throw new RuntimeException('La reponse ne peut pas etre vide.');
            }
            $this->repository->respond((int) $id, $response);
            Session::flash('success', 'Reponse enregistree.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect('/rh/explications');
    }

    public function close(string $id): void
    {
        AuthMiddleware::check();
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expiree. Veuillez recommencer.');
            $this->redirect('/rh/explications');
        }

        try {
            $this->repository->close((int) $id);
            Session::flash('success', 'Demande d\'explication cloturee.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect('/rh/explications');
    }

    public function relancer(string $id): void
    {
        AuthMiddleware::check();
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Session expiree. Veuillez recommencer.');
            $this->redirect('/rh/explications');
        }

        try {
            $this->repository->relancer((int) $id);
            Session::flash('success', 'Relance effectuee (statut passe en attente de complements).');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect('/rh/explications');
    }
}
