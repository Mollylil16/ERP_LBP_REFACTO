<?php

use App\Helpers\View;
use App\Helpers\Session;
use App\View\Pages\Site\SitePage;

/** @var SitePage $page */

$appConfig = require BASE_PATH . '/config/app.php';
$brandName = $page->brand('company_name', 'LBP Transit');
$logoText = $page->brand('logo_text', 'LBP');
$logoUrl = $page->brand('logo_url');
$font = $page->brand('font_family', 'Inter');
$customerConnected = (int) Session::get('site_customer_id', 0) > 0;
$successMessage = Session::getFlash('success');
$errorMessage = Session::getFlash('error');
$nav = [
    'home' => ['Accueil', 'site'],
    'tracking' => ['Tracking', 'site/tracking'],
    'shop' => ['Marketplace', 'site/shop'],
    'agencies' => ['Agences', 'site/agences'],
    'forum' => ['Communauté', 'site/forum'],
    'blog' => ['Actualités', 'site/blog'],
    'quote' => ['Devis', 'site/devis'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="<?= View::e($page->brand('tagline', 'Transit, fret et logistique internationale')) ?>">
    <title><?= View::e($pageTitle ?? 'Site') ?> - <?= View::e($brandName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Manrope:wght@400;500;600;700;800&family=Montserrat:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link href="<?= View::asset('css/app.css') ?>" rel="stylesheet">
    <link href="<?= View::asset('css/components.css') ?>" rel="stylesheet">
    <link href="<?= View::asset('css/finea-ui.css') ?>" rel="stylesheet">
    <link href="<?= View::asset('css/site.css') ?>" rel="stylesheet">
    <link href="<?= View::asset('css/site-chat.css') ?>" rel="stylesheet">
</head>
<body class="site-body" style="--site-primary:<?= View::e($page->brand('primary_color', '#111c44')) ?>;--site-secondary:<?= View::e($page->brand('secondary_color', '#ffcc00')) ?>;--site-accent:<?= View::e($page->brand('accent_color', '#d40511')) ?>;--site-surface:<?= View::e($page->brand('surface_color', '#f5f7fb')) ?>;--site-font:'<?= View::e($font) ?>',sans-serif">
<?php if ($page->announcements !== []): ?>
    <div class="site-announcement" data-announcements>
        <?php foreach ($page->announcements as $index => $announcement): ?>
            <div class="<?= $index === 0 ? 'is-active' : '' ?>" data-announcement>
                <span><?= View::e((string) ($announcement['badge'] ?? 'Nouveau')) ?></span>
                <?= View::e((string) $announcement['title']) ?>
                <?php if (($announcement['link_url'] ?? '') !== ''): ?>
                    <a href="<?= View::url(ltrim((string) $announcement['link_url'], '/')) ?>"><?= View::e((string) ($announcement['link_label'] ?? 'En savoir plus')) ?> →</a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<header class="site-header">
    <a class="site-brand" href="<?= View::url('site') ?>" aria-label="Accueil <?= View::e($brandName) ?>">
        <?php if ($logoUrl !== ''): ?>
            <img src="<?= View::e(preg_match('#^https?://#', $logoUrl) ? $logoUrl : View::asset(ltrim($logoUrl, '/'))) ?>" alt="<?= View::e($brandName) ?>">
        <?php else: ?>
            <span class="site-brand__mark"><?= View::e($logoText) ?></span>
        <?php endif; ?>
        <span><strong><?= View::e($brandName) ?></strong><small><?= View::e($page->brand('tagline', 'Global trade, simply delivered')) ?></small></span>
    </a>
    <button class="site-menu-button" type="button" data-site-menu aria-label="Ouvrir le menu" aria-expanded="false"><span></span><span></span><span></span></button>
    <nav class="site-nav" data-site-nav>
        <?php foreach ($nav as $key => [$label, $url]): ?>
            <a class="<?= $page->activePage === $key ? 'is-active' : '' ?>" href="<?= View::url($url) ?>"><?= View::e($label) ?></a>
        <?php endforeach; ?>
        <a class="site-mobile-account" href="<?= View::url('site/account') ?>"><?= $customerConnected ? 'Mon dashboard' : 'Connexion / inscription' ?></a>
    </nav>
    <div class="site-header__actions">
        <a href="<?= View::url('site/contact') ?>">Parler à un expert</a>
        <a class="site-account-link" href="<?= View::url('site/account') ?>"><?= $customerConnected ? 'Mon dashboard' : 'Espace client' ?> <span>→</span></a>
    </div>
</header>
<?php if ($successMessage): ?><div class="site-flash site-flash--success"><?= View::e($successMessage) ?></div><?php endif; ?>
<?php if ($errorMessage): ?><div class="site-flash site-flash--error"><?= View::e($errorMessage) ?></div><?php endif; ?>
<main class="site-main"><?= $content ?? '' ?></main>
<footer class="site-footer">
    <div class="site-footer__brand"><span class="site-brand__mark"><?= View::e($logoText) ?></span><div><strong><?= View::e($brandName) ?></strong><p><?= View::e($page->brand('tagline')) ?></p></div></div>
    <div><strong>Solutions</strong><a href="<?= View::url('site/tracking') ?>">Tracking</a><a href="<?= View::url('site/shop') ?>">Marketplace</a><a href="<?= View::url('site/devis') ?>">Devis transit</a></div>
    <div><strong>Entreprise</strong><a href="<?= View::url('site/agences') ?>">Nos agences</a><a href="<?= View::url('site/contact') ?>">Contact</a><a href="<?= View::url('site/forum') ?>">Communauté</a></div>
    <div><strong>Professionnels</strong><a href="<?= View::url('site/account') ?>">Espace client</a><a href="<?= View::url('login') ?>">Accès ERP</a><a href="<?= View::url('site-admin/dashboard') ?>">Administration</a></div>
    <small>© <?= date('Y') ?> <?= View::e($brandName) ?>. Site public connecté à <?= View::e($appConfig['name']) ?>.</small>
</footer>
<div class="site-cart" data-cart hidden><header><strong>Votre sélection</strong><button type="button" data-cart-close>×</button></header><div data-cart-items></div><footer><span>Total estimé</span><strong data-cart-total>0 XOF</strong><a href="<?= View::url('site/devis') ?>">Finaliser avec un conseiller</a></footer></div>
<button class="site-cart-fab" type="button" data-cart-open aria-label="Ouvrir la sélection">Panier <span data-cart-count>0</span></button>
<script src="<?= View::asset('js/components.js') ?>" defer></script>
<script>window.LBP_SITE_ANALYTICS={endpoint:<?= json_encode(View::url('site/analytics'), JSON_UNESCAPED_SLASHES) ?>};</script>
<script src="<?= View::asset('js/site.js') ?>" defer></script>
<script src="<?= View::asset('js/site-chat.js') ?>" defer></script>
</body>
</html>
