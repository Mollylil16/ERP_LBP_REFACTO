<?php

declare(strict_types=1);

namespace App\Controllers\Rh;

use App\Middleware\AuthMiddleware;
use App\View\Pages\Rh\PayrollEnginePage;

use App\Models\Database;
use App\Repositories\Rh\RhPayrollRepository;

final class RhPayrollEngineController extends RhBaseController
{
    private RhPayrollRepository $repository;

    public function __construct()
    {
        $this->repository = new RhPayrollRepository(Database::getConnection());
    }

    public function index(): void
    {
        AuthMiddleware::check();

        $contractRules = $this->repository->getContractRules();
        $lineItems = $this->repository->getLineItems();
        $payrollSettings = $this->repository->getPayrollSettings();
        $periods = $this->repository->getPeriods();
        $slips = $this->repository->getAllSlips();

        $this->rhView('rh/payroll-engine/index', 'Moteur de Paie CI', 'payroll', [
            'page' => new PayrollEnginePage(
                contractRules: $contractRules,
                lineItems: $lineItems,
                payrollSettings: $payrollSettings,
                periods: $periods,
                slips: $slips,
            ),
        ]);
    }
}
