<?php

declare(strict_types=1);

namespace App\Controllers\Rh;

use App\Controllers\BaseController;
use App\View\Navigation\RhNavigation;

/**
 * Point d'entrée commun des contrôleurs du module RH.
 *
 * Cette classe centralise uniquement le contexte de présentation du module.
 * Les données métier restent préparées par les services et les Page Objects.
 */
abstract class RhBaseController extends BaseController
{
    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $layout
     */
    protected function rhView(
        string $view,
        string $pageTitle,
        string $activeModule,
        array $data = [],
        array $layout = [],
    ): void {
        $this->view($view, array_replace(
            $this->rhLayoutData($pageTitle, $activeModule),
            $layout,
            $data,
        ));
    }

    /** @return array<string,mixed> */
    private function rhLayoutData(string $pageTitle, string $activeModule): array
    {
        return [
            'pageTitle' => $pageTitle,
            'moduleName' => 'Ressources humaines',
            'moduleCode' => 'RH',
            'activeModule' => $activeModule,
            'additionalStyles' => ['css/finea-ui.css', 'css/rh.css'],
            'additionalScripts' => ['js/rh.js'],
            'moduleNavigation' => RhNavigation::items(),
        ];
    }
}
