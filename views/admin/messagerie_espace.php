<?php

use App\View\Components\AdminMessagerieEspace;
use App\View\Pages\Admin\MessagerieEspacePage;

/** @var MessagerieEspacePage $page */

ob_start();
echo AdminMessagerieEspace::page($page->etat, $page->groupes);
$content = ob_get_clean();

require BASE_PATH . '/views/layouts/module.php';
