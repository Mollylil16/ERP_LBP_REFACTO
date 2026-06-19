<?php

declare(strict_types=1);

namespace App\Controllers\SiteAdmin;

use App\Controllers\BaseController;
use App\View\Navigation\SiteAdminNavigation;

abstract class SiteAdminBaseController extends BaseController
{
    /** @param array<string,mixed> $data */
    protected function siteAdminView(
        string $view,
        string $title,
        string $active,
        array $data = [],
        array $theme = [],
    ): void {
        $this->view($view, array_replace([
            'pageTitle' => $title,
            'moduleName' => 'Site internet',
            'moduleCode' => 'WEB',
            'moduleTheme' => $theme,
            'activeModule' => $active,
            'moduleNavigation' => SiteAdminNavigation::items(),
            'additionalStyles' => ['css/finea-ui.css'],
        ], $data));
    }
}
