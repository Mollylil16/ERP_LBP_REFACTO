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
            'moduleNavigation' => [
                ['key' => 'dashboard', 'label' => 'Tableau de bord', 'icon' => 'DB', 'url' => '/finance/dashboard', 'available' => true],
                ['key' => 'factures', 'label' => 'Factures Clients', 'icon' => 'FAC', 'url' => '/finance/factures', 'available' => true],
                ['key' => 'clotures', 'label' => 'Points de Caisse', 'icon' => 'CLT', 'url' => '/finance/clotures', 'available' => true],
                ['key' => 'depenses', 'label' => 'Dépenses Prestataires', 'icon' => 'DEP', 'url' => '/finance/depenses', 'available' => true],
                ['key' => 'comptabilite', 'label' => 'Comptabilité', 'icon' => 'CPT', 'url' => '/finance/comptabilite', 'available' => Auth::hasAnyRole(['comptable', 'dg'])],
            ],
            'additionalStyles' => ['css/finea-ui.css', 'css/finance.css'],
        ];
    }
}
