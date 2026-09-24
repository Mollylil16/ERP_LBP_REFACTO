<?php

declare(strict_types=1);

namespace App\Controllers\Finance;

use App\Helpers\Auth;
use App\Middleware\RoleMiddleware;
use App\Security\ControleCaisseAcces;
use App\Services\Finance\ControleCaisseService;

/**
 * Finance > Contrôle des caisses.
 *
 * L'écran de surveillance de la direction : ce qui est entré en caisse, ce
 * qui a été compté, ce qui ne l'a jamais été. En lecture seule — aucune
 * route d'écriture n'existe, une correction passe par le point de caisse de
 * l'agence, sous son nom.
 */
final class ControleCaisseController extends FinanceBaseController
{
    private ControleCaisseService $service;

    public function __construct()
    {
        $this->service = ControleCaisseService::creer();
    }

    public function index(): void
    {
        RoleMiddleware::check(ControleCaisseAcces::ROLES_LECTURE);

        $this->financeView(
            'finance/controle-caisse/index',
            'Contrôle des caisses',
            'controle-caisse',
            $this->service->tableau($_GET)
        );
    }

    /** Le même tableau, en page imprimable, pour la réunion de direction. */
    public function exportPdf(): void
    {
        RoleMiddleware::check(ControleCaisseAcces::ROLES_LECTURE);

        $controle = $this->service->tableau($_GET) + ['edite_par' => Auth::user()?->fullName ?? ''];

        require BASE_PATH . '/views/finance/controle-caisse/export_pdf.php';
    }
}
