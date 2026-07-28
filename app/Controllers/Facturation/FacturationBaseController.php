<?php

declare(strict_types=1);

namespace App\Controllers\Facturation;

use App\Controllers\BaseController;

abstract class FacturationBaseController extends BaseController
{
    /**
     * @param array<string,mixed> $module
     * @param array<string,mixed> $data
     * @param array<string,mixed> $layout
     */
    protected function facturationView(
        string $view,
        string $pageTitle,
        string $activeModule,
        array $module = [],
        array $data = [],
        array $layout = [],
    ): void {
        $layoutData = [
            'pageTitle' => $pageTitle,
            'moduleName' => $module['label'] ?? 'Facturation',
            'moduleCode' => $module['code'] ?? 'FAC',
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
