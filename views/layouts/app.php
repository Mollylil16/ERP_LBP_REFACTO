<?php

use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\View;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());
$appConfig = require BASE_PATH . '/config/app.php';
$title = $pageTitle ?? 'Tableau de bord';

$successMessage = Session::getFlash('success');
$errorMessage = Session::getFlash('error');
$user = Auth::user();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title><?= View::e($title) ?> - <?= View::e($appConfig['name']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="<?= View::asset('css/app.css') ?>" rel="stylesheet">
    <link href="<?= View::asset('css/components.css') ?>" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>

<body class="app-dashboard-body">
    <?php if ($successMessage): ?>
        <div class="flash-message flash-success"><?= View::e($successMessage) ?></div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="flash-message flash-error"><?= View::e($errorMessage) ?></div>
    <?php endif; ?>

    <div class="app-shell app-shell--portal">
        <div class="app-main app-main--portal">
            <header class="app-topbar app-topbar--portal">
                <div>
                    <span class="app-topbar-kicker">Portail ERP</span>
                    <h1>Modules de gestion • ERP de transit</h1>
                    <p class="app-topbar-subtitle">Accédez directement aux modules depuis une interface de type Odoo / Finea.</p>
                </div>

                <div class="app-profile">
                    <div class="app-profile-avatar"><?= strtoupper(substr((string) ($user?->fullName ?? 'A'), 0, 1)) ?></div>
                    <div class="app-profile-info">
                        <strong><?= View::e($user?->fullName ?? 'Administrateur') ?></strong>
                        <span><?= View::e($user?->email ?? 'admin@erp-lbp.local') ?></span>
                    </div>
                    <a href="<?= View::url('logout') ?>" class="app-logout-link">Déconnexion</a>
                </div>
            </header>

            <main class="app-content app-content--portal">
                <?= $content ?? '' ?>
            </main>
        </div>
    </div>

    <script src="<?= View::asset('js/app.js') ?>"></script>
    <script src="<?= View::asset('js/components.js') ?>"></script>
</body>

</html>
