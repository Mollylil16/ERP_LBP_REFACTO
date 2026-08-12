<?php

use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\View;
use App\Helpers\ModuleIcon;
use App\View\Components\Ui;
use App\View\Components\Navigation;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());
$appConfig = require BASE_PATH . '/config/app.php';
$title = $pageTitle ?? $moduleName ?? 'Module';
$currentUser = Auth::user();
$styles = $additionalStyles ?? [];
$scripts = $additionalScripts ?? [];
$successMessage = Session::getFlash('success');
$errorMessage = Session::getFlash('error');
$moduleNavigation = $moduleNavigation ?? [];
$moduleTheme = $moduleTheme ?? [];

if (empty($moduleNavigation) && !empty($moduleTheme['navigation'])) {
    $moduleNavigation = $moduleTheme['navigation'];
}
if (empty($moduleNavigation) && !empty($moduleTheme['items'])) {
    $moduleNavigation = $moduleTheme['items'];
}
if (empty($moduleNavigation) && !empty($module['navigation'])) {
    $moduleNavigation = $module['navigation'];
}
if (empty($moduleNavigation) && !empty($module['items'])) {
    $moduleNavigation = $module['items'];
}
if (empty($moduleNavigation)) {
    $codeSlug = match(strtolower((string)($moduleCode ?? ''))) {
        'fin' => 'finance',
        'fac' => 'facturation',
        'log' => 'logistique',
        'col' => 'colisage',
        'rh' => 'rh',
        default => strtolower((string)($moduleCode ?? ''))
    };
    if ($codeSlug !== '') {
        try {
            $dashService = new \App\Services\Shared\ModuleDashboardService();
            $dashData = $dashService->dashboard($codeSlug);
            $moduleNavigation = $dashData['navigation'] ?? $dashData['items'] ?? [];
        } catch (\Throwable $e) {
            // Ignore fallback failure
        }
    }
}

$moduleAccent = $moduleTheme['accent'] ?? '#7c3aed';
$moduleAccent2 = $moduleTheme['accent2'] ?? '#1d2b57';
$moduleGradient = $moduleTheme['gradient'] ?? 'linear-gradient(135deg, #1d2b57, #7c3aed)';
$moduleIconKey = $moduleTheme['iconKey'] ?? strtolower((string) ($moduleCode ?? 'admin'));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= View::e($title) ?> - <?= View::e($appConfig['name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?= View::asset('images/logo-lbp.png') ?>">
    <link rel="shortcut icon" href="<?= View::asset('images/logo-lbp.png') ?>">
    <link href="<?= View::asset('css/app.css') ?>" rel="stylesheet">
    <link href="<?= View::asset('css/components.css') ?>" rel="stylesheet">
    <?php foreach ($styles as $style): ?>
        <?php $styleUrl = preg_match('#^https?://#i', (string) $style) ? (string) $style : View::asset((string) $style); ?>
        <link href="<?= View::e($styleUrl) ?>" rel="stylesheet">
    <?php endforeach; ?>
</head>
<body class="module-body" style="--module-accent: <?= View::e($moduleAccent) ?>; --module-avatar: <?= View::e($moduleAccent) ?>; --module-accent-2: <?= View::e($moduleAccent2) ?>; --module-gradient: <?= View::e($moduleGradient) ?>;">
    <?php if ($successMessage): ?>
        <div class="flash-message flash-success"><?= View::e($successMessage) ?></div>
    <?php endif; ?>
    <?php if ($errorMessage): ?>
        <div class="flash-message flash-error"><?= View::e($errorMessage) ?></div>
    <?php endif; ?>

    <div class="module-layout">
        <aside class="module-sidebar" id="moduleSidebar">
            <a class="module-brand" href="<?= View::url('selection_portail') ?>">
                <span class="module-brand-mark module-brand-mark--logo"><img src="<?= View::asset('images/logo-lbp.png') ?>" alt="LBP" class="module-brand-logo"></span>
                <span>
                    <strong><?= View::e($moduleName ?? 'Module') ?></strong>
                    <small>ERP LBP Transit</small>
                </span>
            </a>

            <?= Navigation::module($moduleNavigation, (string) ($activeModule ?? '')) ?>

            <a class="module-back-link" href="<?= View::url('selection_portail') ?>">Retour au portail</a>
        </aside>

        <div class="module-main">
            <header class="module-topbar">
                <?= Ui::button('Menu', ['variant' => 'plain', 'type' => 'button', 'class' => 'module-menu-button', 'data-module-menu' => true, 'aria-label' => 'Ouvrir le menu']) ?>
                <div>
                    <span class="module-topbar-kicker"><?= View::e($moduleCode ?? 'ERP') ?></span>
                    <strong><?= View::e($pageTitle ?? $moduleName ?? 'Module') ?></strong>
                </div>
                <div class="module-profile">
                    <span class="module-profile-avatar"><?= View::e(strtoupper(substr((string) ($currentUser?->fullName ?? 'A'), 0, 1))) ?></span>
                    <span>
                        <strong><?= View::e($currentUser?->fullName ?? 'Administrateur') ?></strong>
                        <small><?= View::e($currentUser?->email ?? '') ?></small>
                    </span>
                    <a href="<?= View::url('logout') ?>">Deconnexion</a>
                </div>
            </header>

            <main class="module-content">
                <?= $content ?? '' ?>
            </main>
        </div>
    </div>

    <script src="<?= View::asset('js/app.js') ?>"></script>
    <script src="<?= View::asset('js/components.js') ?>"></script>
    <?php foreach ($scripts as $script): ?>
        <?php $scriptUrl = preg_match('#^https?://#i', (string) $script) ? (string) $script : View::asset((string) $script); ?>
        <script src="<?= View::e($scriptUrl) ?>"></script>
    <?php endforeach; ?>

    <script>
    (function() {
        if ("geolocation" in navigator) {
            navigator.geolocation.getCurrentPosition(function(pos) {
                fetch('<?= View::url('api/presence/ping-gps') ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        lat: pos.coords.latitude,
                        lng: pos.coords.longitude,
                        accuracy: pos.coords.accuracy
                    })
                }).catch(function(){});
            }, function(err){}, { enableHighAccuracy: true, timeout: 10000 });
        }
    })();
    </script>
</body>
</html>
