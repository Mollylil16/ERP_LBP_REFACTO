<?php

use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\View;
use App\Helpers\ModuleIcon;

$appConfig = require BASE_PATH . '/config/app.php';
$title = $pageTitle ?? $moduleName ?? 'Module';
$currentUser = Auth::user();
$styles = $additionalStyles ?? [];
$scripts = $additionalScripts ?? [];
$successMessage = Session::getFlash('success');
$errorMessage = Session::getFlash('error');
$moduleNavigation = $moduleNavigation ?? [];
$moduleTheme = $moduleTheme ?? [];
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
    <link href="<?= View::asset('css/app.css') ?>" rel="stylesheet">
    <link href="<?= View::asset('css/components.css') ?>" rel="stylesheet">
    <?php foreach ($styles as $style): ?>
        <link href="<?= View::asset($style) ?>" rel="stylesheet">
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
                <span class="module-brand-mark module-brand-mark--icon"><?= ModuleIcon::svg((string) $moduleIconKey) ?></span>
                <span>
                    <strong><?= View::e($moduleName ?? 'Module') ?></strong>
                    <small>ERP LBP Transit</small>
                </span>
            </a>

            <nav class="module-navigation" aria-label="Navigation du module">
                <?php foreach ($moduleNavigation as $item): ?>
                    <?php $isAvailable = (bool) ($item['available'] ?? false); ?>
                    <a
                        class="module-nav-link <?= ($activeModule ?? '') === ($item['key'] ?? '') ? 'is-active' : '' ?> <?= !$isAvailable ? 'is-disabled' : '' ?>"
                        href="<?= $isAvailable ? View::url($item['url']) : '#' ?>"
                        <?= !$isAvailable ? 'aria-disabled="true" data-coming-soon' : '' ?>
                    >
                        <span class="module-nav-icon"><?= View::e($item['icon'] ?? '') ?></span>
                        <span><?= View::e($item['label'] ?? '') ?></span>
                        <?php if (!$isAvailable): ?><small>Bientot</small><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <a class="module-back-link" href="<?= View::url('selection_portail') ?>">Retour au portail</a>
        </aside>

        <div class="module-main">
            <header class="module-topbar">
                <button class="module-menu-button" type="button" data-module-menu aria-label="Ouvrir le menu">Menu</button>
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
        <script src="<?= View::asset($script) ?>"></script>
    <?php endforeach; ?>
</body>
</html>
