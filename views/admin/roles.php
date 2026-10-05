<?php

use App\View\Components\AdminRoles;
use App\View\Pages\Admin\RolesPage;

/** @var RolesPage $page */

ob_start();
echo AdminRoles::page($page->roles, $page->sansRole, $page->dormants, $page->administrateurs);
$content = ob_get_clean();

require BASE_PATH . '/views/layouts/module.php';
