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
    <?php if (!Auth::isAdmin() && !Auth::hasRole('dg')): ?>
    <div id="lbp-gps-blocker" style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: #0f172a; color: #ffffff; z-index: 999999; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2rem; text-align: center; font-family: 'Inter', sans-serif;">
        <div style="background: #1e293b; padding: 2.5rem; border-radius: 16px; border: 1px solid rgba(255, 255, 255, 0.1); max-width: 500px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);">
            <div style="font-size: 3.5rem; margin-bottom: 1.5rem;">📡</div>
            <h2 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 1rem; color: #ffffff;">Géolocalisation Obligatoire</h2>
            <p id="lbp-gps-status" style="color: #94a3b8; font-size: 0.95rem; line-height: 1.6; margin-bottom: 1.5rem;">
                Recherche de votre position géographique en cours...<br>
                Veuillez autoriser l'accès à la localisation si votre navigateur vous le demande.
            </p>
            <div id="lbp-gps-loader" style="width: 40px; height: 40px; border: 4px solid rgba(255,255,255,0.1); border-top-color: #3b82f6; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 1.5rem auto;"></div>
            <button onclick="window.location.reload()" id="lbp-gps-retry-btn" style="display: none; background: #2563eb; color: #ffffff; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: background 0.2s;">
                Réessayer la localisation
            </button>
        </div>
    </div>
    <style>@keyframes spin { to { transform: rotate(360deg); } }</style>
    <?php endif; ?>

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
        const blocker = document.getElementById('lbp-gps-blocker');
        const status = document.getElementById('lbp-gps-status');
        const loader = document.getElementById('lbp-gps-loader');
        const retryBtn = document.getElementById('lbp-gps-retry-btn');

        if (!blocker) return; // Admins and DG are exempted

        if (!("geolocation" in navigator)) {
            status.innerHTML = "⚠️ Erreur : Votre navigateur ne supporte pas la géolocalisation. Veuillez utiliser un navigateur moderne (Chrome, Safari, Firefox).";
            loader.style.display = 'none';
            return;
        }

        function requestLocation() {
            loader.style.display = 'block';
            retryBtn.style.display = 'none';
            
            navigator.geolocation.getCurrentPosition(function(pos) {
                status.innerHTML = "Position obtenue. Enregistrement de votre présence...";
                
                fetch('<?= View::url('api/presence/ping-gps') ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        lat: pos.coords.latitude,
                        lng: pos.coords.longitude,
                        accuracy: pos.coords.accuracy
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        blocker.style.display = 'none';
                    } else {
                        status.innerHTML = "⚠️ Erreur de validation : " + (data.message || "Erreur serveur");
                        loader.style.display = 'none';
                        retryBtn.style.display = 'inline-block';
                    }
                })
                .catch(err => {
                    status.innerHTML = "⚠️ Erreur réseau lors de l'enregistrement de votre position.";
                    loader.style.display = 'none';
                    retryBtn.style.display = 'inline-block';
                });
            }, function(err) {
                let msg = "Veuillez autoriser l'accès à la localisation dans votre navigateur.";
                if (err.code === err.PERMISSION_DENIED) {
                    msg = "L'accès à la localisation a été refusé. Vous devez l'autoriser dans les paramètres de votre navigateur/appareil pour accéder au logiciel.";
                } else if (err.code === err.POSITION_UNAVAILABLE) {
                    msg = "Votre position géographique est indisponible. Assurez-vous d'être connecté à internet et d'avoir activé le GPS de votre appareil.";
                } else if (err.code === err.TIMEOUT) {
                    msg = "La recherche de votre position a expiré. Veuillez réessayer.";
                }
                status.innerHTML = "⚠️ Accès refusé ou impossible :<br><br>" + msg;
                loader.style.display = 'none';
                retryBtn.style.display = 'inline-block';
            }, { enableHighAccuracy: false, timeout: 15000, maximumAge: 0 });
        }

        requestLocation();
    })();
    </script>
</body>
</html>
