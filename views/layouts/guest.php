<?php

use App\Helpers\Session;
use App\Helpers\View;

$appConfig = require BASE_PATH . '/config/app.php';
$title = $pageTitle ?? $appConfig['name'];

$successMessage = Session::getFlash('success');
$errorMessage = Session::getFlash('error');
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title><?= View::e($title) ?> - <?= View::e($appConfig['name']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="<?= View::asset('css/app.css') ?>" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>

<body class="app-body">

    <?php if ($successMessage): ?>
        <div class="flash-message flash-success">
            <?= View::e($successMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="flash-message flash-error">
            <?= View::e($errorMessage) ?>
        </div>
    <?php endif; ?>

    <main>
        <?= $content ?? '' ?>
    </main>

    <script src="<?= View::asset('js/app.js') ?>"></script>
</body>

</html>