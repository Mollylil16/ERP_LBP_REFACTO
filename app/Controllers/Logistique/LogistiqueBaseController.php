<?php

declare(strict_types=1);

namespace App\Controllers\Logistique;

use App\Controllers\BaseController;

abstract class LogistiqueBaseController extends BaseController
{
    /**
     * @param array<string,mixed> $module
     * @param array<string,mixed> $data
     * @param array<string,mixed> $layout
     */
    protected function logistiqueView(
        string $view,
        string $pageTitle,
        string $activeModule,
        array $module = [],
        array $data = [],
        array $layout = [],
    ): void {
        $layoutData = [
            'pageTitle' => $pageTitle,
            'moduleName' => $module['label'] ?? 'Logistique',
            'moduleCode' => $module['code'] ?? 'LOG',
            'moduleTheme' => $module,
            'activeModule' => $activeModule,
            'moduleNavigation' => $module['navigation'] ?? [],
            'additionalStyles' => ['css/finea-ui.css'],
        ];

        $data = array_replace(
            \App\Support\ViewBag::defaults(),
            $layoutData,
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
}
