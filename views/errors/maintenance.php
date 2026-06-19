<?php

use App\Helpers\View;

$reason = (string) ($maintenance['reason'] ?? 'Une intervention technique est en cours.');
$module = (string) ($maintenance['slug'] ?? 'module');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Module en maintenance</title>
    <link href="<?= View::asset('css/app.css') ?>" rel="stylesheet">
</head>
<body>
    <main class="finea-shell">
        <section class="finea-section-card" style="max-width:720px;margin:10vh auto;padding:32px">
            <p class="finea-eyebrow">Maintenance temporaire</p>
            <h1><?= View::e($module) ?> est momentanément indisponible</h1>
            <p><?= View::e($reason) ?></p>
            <a class="finea-action-btn finea-action-btn--primary" href="<?= View::url('selection_portail') ?>">Retour au portail</a>
        </section>
    </main>
</body>
</html>
