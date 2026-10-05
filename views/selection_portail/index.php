<?php

use App\View\Components\Avis;
use App\View\Components\ModuleCatalog;
use App\View\Pages\Portal\SelectionPage;

/** @var SelectionPage $page */

$pageTitle = $page->title;

// Le style du portail vit dans sa propre feuille depuis le 05/10/2026 : il
// tenait jusque-la au milieu du PHP, et personne n allait l y retoucher.
$additionalStyles = ['css/portail.css'];
ob_start();
?>
<div class="portal-page portail">
    <?= Avis::portail() ?>

    <?= ModuleCatalog::hero($page->userName, count($page->modules)) ?>

    <?= ModuleCatalog::moduleFilter($page->moduleOptions(), count($page->modules)) ?>

    <?= ModuleCatalog::moduleGrid($page->modules) ?>

    <?= ModuleCatalog::footerNote() ?>
</div>
<?php
$content = ob_get_clean();

require BASE_PATH . '/views/layouts/app.php';
