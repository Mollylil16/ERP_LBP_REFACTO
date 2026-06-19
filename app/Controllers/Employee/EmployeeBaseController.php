<?php

declare(strict_types=1);

namespace App\Controllers\Employee;

use App\Controllers\BaseController;
use App\View\Navigation\EmployeeNavigation;

abstract class EmployeeBaseController extends BaseController
{
    /** @param array<string,mixed> $data */
    protected function employeeView(string $view, string $title, string $active, array $data = []): void
    {
        $this->view($view, array_replace([
            'pageTitle' => $title,
            'moduleName' => 'Espace employé',
            'moduleCode' => 'EMP',
            'activeModule' => $active,
            'moduleTheme' => [
                'accent' => '#0ea5e9',
                'accent2' => '#0369a1',
                'gradient' => 'linear-gradient(135deg,#0369a1,#0ea5e9)',
                'iconKey' => 'employee',
            ],
            'moduleNavigation' => EmployeeNavigation::items(),
            'additionalStyles' => ['css/finea-ui.css', 'css/employee.css'],
            'additionalScripts' => ['js/employee.js'],
        ], $data));
    }
}
