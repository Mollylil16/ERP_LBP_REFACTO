<?php

use App\View\Components\ModuleCatalog;
use App\View\Pages\Portal\SelectionPage;

/** @var SelectionPage $page */

$pageTitle = $page->title;
ob_start();
?>
<div class="portal-page">
    <?= ModuleCatalog::hero($page->userName, count($page->modules)) ?>

    <?= ModuleCatalog::moduleFilter($page->moduleOptions(), count($page->modules)) ?>

    <?= ModuleCatalog::moduleGrid($page->modules) ?>

    <?= ModuleCatalog::footerNote() ?>
</div>
<?php
$content = ob_get_clean();

require BASE_PATH . '/views/layouts/app.php';
