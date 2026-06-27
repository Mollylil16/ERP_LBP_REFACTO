<?php

declare(strict_types=1);

namespace App\Controllers\Rh;

use App\Middleware\AuthMiddleware;
use App\View\Pages\Rh\PayrollEnginePage;

final class RhPayrollEngineController extends RhBaseController
{
    public function index(): void
    {
        AuthMiddleware::check();

        $this->rhView('rh/payroll-engine/index', 'Moteur de Paie CI', 'payroll', [
            'page' => new PayrollEnginePage(),
        ]);
    }
}
