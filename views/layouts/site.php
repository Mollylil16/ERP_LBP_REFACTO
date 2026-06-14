<?php
use App\Helpers\View;
$appConfig = require BASE_PATH . '/config/app.php';
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= View::e($pageTitle ?? 'Site') ?> - <?= View::e($appConfig['name']) ?></title><link href="<?= View::asset('css/app.css') ?>" rel="stylesheet"><link href="<?= View::asset('css/finea-ui.css') ?>" rel="stylesheet"><link href="<?= View::asset('css/site.css') ?>" rel="stylesheet"></head><body class="site-body"><header class="site-header"><strong>LBP Transit</strong><nav><a href="#tracking">Suivi colis</a><a href="#contact">Contact</a><a href="<?= View::url('login') ?>">ERP</a></nav></header><main class="site-main"><?= $content ?? '' ?></main><footer class="site-footer">Transit • Import-export • ERP connecté</footer></body></html>
