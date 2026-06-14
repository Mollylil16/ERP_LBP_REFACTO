<?php
use App\Helpers\View;
use App\View\Components\Ui;
$appConfig = require BASE_PATH . '/config/app.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= View::e($pageTitle ?? 'Site') ?> - <?= View::e($appConfig['name']) ?></title>
    <link href="<?= View::asset('css/app.css') ?>" rel="stylesheet">
    <link href="<?= View::asset('css/components.css') ?>" rel="stylesheet">
    <link href="<?= View::asset('css/finea-ui.css') ?>" rel="stylesheet">
    <link href="<?= View::asset('css/site.css') ?>" rel="stylesheet">
</head>
<body class="site-body">
<header class="site-header">
    <a class="site-brand" href="<?= View::url('site') ?>" aria-label="Accueil LBP Transit">
        <span class="site-brand__mark">LBP</span>
        <span><strong>LBP Transit</strong><small>Import • Export • Logistics</small></span>
    </a>
    <?= Ui::button('Menu', ['variant' => 'plain', 'type' => 'button', 'class' => 'site-menu-button', 'data-site-menu' => true]) ?>
    <nav class="site-nav" data-site-nav>
        <a href="<?= View::url('site') ?>">Accueil</a>
        <a href="<?= View::url('site/tracking') ?>">Suivi colis</a>
        <a href="<?= View::url('site/agences') ?>">Agences</a>
        <a href="<?= View::url('site/devis') ?>">Devis</a>
        <a href="<?= View::url('site/contact') ?>">Contact</a>
        <a class="site-nav__erp" href="<?= View::url('login') ?>">Accès ERP</a>
    </nav>
</header>
<main class="site-main"><?= $content ?? '' ?></main>
<footer class="site-footer">
    <div>
        <strong>LBP Transit</strong>
        <p>Site public connecté à l’ERP : tracking, devis, agences, contenus, leads et publicité.</p>
    </div>
    <div class="site-footer__links">
        <a href="<?= View::url('site/agences') ?>">Nos agences</a>
        <a href="<?= View::url('site/tracking') ?>">Suivre un colis</a>
        <a href="<?= View::url('site-admin/dashboard') ?>">Pilotage site</a>
    </div>
</footer>
<script src="<?= View::asset('js/site.js') ?>" defer></script>
</body>
</html>
