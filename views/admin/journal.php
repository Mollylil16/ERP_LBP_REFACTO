<?php

use App\View\Components\AdminComptesJournal;
use App\View\Pages\Admin\JournalPage;

/** @var JournalPage $page */

ob_start();
echo AdminComptesJournal::page($page->lignes, $page->filtres, $page->comptes, $page->acteurs);
$content = ob_get_clean();

require BASE_PATH . '/views/layouts/module.php';
