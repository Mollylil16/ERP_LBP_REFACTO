<?php

declare(strict_types=1);

namespace App\Controllers\Finance;

use App\Controllers\BaseController;
use App\Helpers\Auth;

abstract class FinanceBaseController extends BaseController
{
    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $layout
     */
    protected function financeView(
        string $view,
        string $pageTitle,
        string $activeModule,
        array $data = [],
        array $layout = [],
    ): void {
        $data = array_replace(
            \App\Support\ViewBag::defaults(),
            $this->financeLayoutData($pageTitle, $activeModule),
            $layout,
            $data,
        );
        $viewData = \App\Support\ViewBag::from($data);
        extract($data, EXTR_SKIP);

        ob_start();
        require BASE_PATH . '/views/' . $view . '.php';
        $content = ob_get_clean();

        require BASE_PATH . '/views/layouts/module.php';
    }

    /** @return array<string,mixed> */
    private function financeLayoutData(string $pageTitle, string $activeModule): array
    {
        $dashService = new \App\Services\Shared\ModuleDashboardService();
        $module = $dashService->dashboard('finance');

        return [
            'pageTitle' => $pageTitle,
            'moduleName' => 'Finance',
            'moduleCode' => 'FIN',
            'moduleTheme' => [
                'accent' => '#2563eb',
                'accent2' => '#1d2b57',
                'gradient' => 'linear-gradient(135deg, #1d2b57, #2563eb)',
            ],
            'activeModule' => $activeModule,
            'moduleNavigation' => $module['navigation'] ?? $module['items'] ?? [],
            'additionalStyles' => ['css/finea-ui.css', 'css/finance.css'],
        ];
    }
}
