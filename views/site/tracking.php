<?php
use App\Helpers\View;
use App\View\Components\Form;
use App\View\Components\Ui;
use App\View\Components\Site;
use App\View\Pages\Site\SitePage;

/** @var SitePage $page */
ob_start();
$current = $page->currentShipment;
?>
<div class="site-content">
<section class="site-page-hero site-page-hero--tracking">
    <p class="finea-eyebrow">Suivi colis</p>
    <h1>Consultez l’état d’un colis, BL ou dossier transit.</h1>
    <form class="site-searchbar" method="get" action="<?= View::url('site/tracking') ?>">
        <?= Form::input('ref', ['label' => 'Référence', 'value' => $current['reference'] ?? '', 'placeholder' => 'Référence colis / BL / dossier']) ?>
        <?= Site::button('Suivre', ['variant' => 'accent', 'type' => 'submit']) ?>
    </form>
</section>
<section class="site-tracking-result">
    <article class="site-result-card">
        <span class="site-card__badge"><?= View::e($current['status']) ?></span>
        <h2><?= View::e($current['reference']) ?></h2>
        <p><?= View::e($current['origin']) ?> → <?= View::e($current['destination']) ?></p>
        <div class="site-progress"><span style="width:<?= (int)$current['progress'] ?>%"></span></div>
        <div class="site-result-meta"><span>Client<br><strong><?= View::e($current['client']) ?></strong></span><span>Position<br><strong><?= View::e($current['lastLocation'] ?? '-') ?></strong></span><span>ETA<br><strong><?= View::e($current['eta']) ?></strong></span><span>Progression<br><strong><?= (int)$current['progress'] ?>%</strong></span></div>
    </article>
    <article class="site-timeline">
        <?php foreach (($current['steps'] ?? []) as $step): ?>
            <div><time><?= View::e($step['date']) ?></time><strong><?= View::e($step['title']) ?></strong><p><?= View::e($step['detail']) ?></p></div>
        <?php endforeach; ?>
    </article>
</section>
<section class="site-section-head"><p class="finea-eyebrow">Données test</p><h2>Références disponibles pour tester le rendu.</h2></section>
<div class="site-reference-list">
    <?php foreach ($page->shipments as $shipment): ?>
        <a href="<?= View::url('site/tracking') ?>?ref=<?= urlencode($shipment['reference']) ?>"><?= View::e($shipment['reference']) ?><span><?= View::e($shipment['status']) ?></span></a>
    <?php endforeach; ?>
</div>
</div>
<?php $content = ob_get_clean(); require BASE_PATH . '/views/layouts/site.php';
