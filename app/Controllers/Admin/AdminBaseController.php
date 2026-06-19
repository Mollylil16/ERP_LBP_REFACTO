<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\View\Navigation\AdminNavigation;

abstract class AdminBaseController extends BaseController
{
    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $layout
     */
    protected function adminView(
        string $view,
        string $pageTitle,
        string $activeModule,
        array $data = [],
        array $layout = [],
    ): void {
        $this->view($view, array_replace(
            [
                'pageTitle' => $pageTitle,
                'moduleName' => 'Administration',
                'moduleCode' => 'ADM',
                'activeModule' => $activeModule,
                'moduleNavigation' => AdminNavigation::items(),
                'additionalStyles' => ['css/finea-ui.css', 'css/admin.css'],
                'additionalScripts' => ['js/admin.js'],
            ],
            $layout,
            $data,
        ));
    }
}
