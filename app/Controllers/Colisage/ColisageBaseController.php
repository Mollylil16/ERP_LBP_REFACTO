<?php

declare(strict_types=1);

namespace App\Controllers\Colisage;

use App\Controllers\BaseController;
use App\View\Navigation\ColisageNavigation;

abstract class ColisageBaseController extends BaseController
{
    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $layout
     */
    protected function colisageView(
        string $view,
        string $pageTitle,
        string $activeModule,
        array $data = [],
        array $layout = [],
    ): void {
        $data = array_replace(
            \App\Support\ViewBag::defaults(),
            $this->colisageLayoutData($pageTitle, $activeModule),
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
    private function colisageLayoutData(string $pageTitle, string $activeModule): array
    {
        return [
            'pageTitle' => $pageTitle,
            'moduleName' => 'Facturation',
            'moduleCode' => 'COL',
            'activeModule' => $activeModule,
            'additionalStyles' => ['css/finea-ui.css', 'css/colisage.css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'],
            'additionalScripts' => ['https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'],
            'moduleNavigation' => ColisageNavigation::items(),
        ];
    }
}
