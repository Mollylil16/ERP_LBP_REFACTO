<?php

use App\Helpers\View;
use App\View\Components\Site;
use App\View\Pages\Site\SitePage;

/** @var SitePage $page */

ob_start();
?>
<div class="site-content">
    <?= Site::pageHero('Communauté LBP', 'Le forum pratique du transit et du commerce international.', 'Des réponses concrètes proposées par des importateurs, transitaires, acheteurs et conseillers métier.') ?>
    <section class="site-forum-toolbar"><div><strong>Discussions récentes</strong><span>Une communauté professionnelle en construction</span></div><a href="<?= View::url('login') ?>">Créer un compte bientôt <span>→</span></a></section>
    <?= Site::topics($page->topics) ?>
    <section class="site-forum-categories"><article><strong>Import Chine</strong><span>Sourcing, fournisseurs, contrôle qualité</span></article><article><strong>Douane & conformité</strong><span>Documents, taxes et réglementation</span></article><article><strong>Transport & corridors</strong><span>Maritime, aérien et livraison régionale</span></article><article><strong>Retours d’expérience</strong><span>Conseils et bonnes pratiques terrain</span></article></section>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/site.php';
