<?php
use App\Helpers\View;
use App\View\Components\Form;
use App\View\Components\Ui;
/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());
ob_start();
$agenciesJson = json_encode($agencies ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<section class="site-page-hero site-page-hero--locator">
    <p class="finea-eyebrow">Localisateur LBP</p>
    <h1>Trouvez une agence ou un point relais LBP.</h1>
    <p>Fonctionnalité type locator : liste effective, recherche, filtre pays et marqueur positionné pour chaque agence.</p>
</section>
<section class="site-locator" data-agencies='<?= View::e($agenciesJson) ?>'>
    <aside class="site-locator__list">
        <div class="site-locator__filters">
            <?= Form::input('agency_search', ['label' => 'Recherche', 'placeholder' => 'Rechercher ville, pays, service...', 'data-agency-search' => true]) ?>
            <?= Form::selectSearch('country_filter', array_merge(
                [['value' => '', 'label' => 'Tous les pays']],
                array_map(static fn(string $country): array => ['value' => $country, 'label' => $country], array_values(array_unique(array_column($agencies ?? [], 'country'))))
            ), '', ['label' => 'Pays', 'data-country-filter' => true]) ?>
        </div>
        <div class="site-agency-results" data-agency-results>
            <?php foreach (($agencies ?? []) as $agency): ?>
                <article class="site-agency-card" data-agency-card data-code="<?= View::e($agency['code']) ?>" data-search="<?= View::e(strtolower($agency['name'].' '.$agency['city'].' '.$agency['country'].' '.$agency['services'])) ?>" data-country="<?= View::e($agency['country']) ?>">
                    <strong><?= View::e($agency['name']) ?></strong><span><?= View::e($agency['address']) ?></span><small><?= View::e($agency['city']) ?>, <?= View::e($agency['country']) ?> • <?= View::e($agency['hours']) ?></small><div><?= View::e($agency['services']) ?></div><a href="tel:<?= View::e($agency['phone']) ?>"><?= View::e($agency['phone']) ?></a>
                </article>
            <?php endforeach; ?>
        </div>
    </aside>
    <div class="site-map-panel"><div class="site-map-toolbar"><strong>Carte des agences</strong><span data-agency-count><?= count($agencies ?? []) ?> agence(s)</span></div>
        <div class="site-map" data-site-map aria-label="Carte interactive des agences LBP"><div class="site-map__grid"></div>
            <?php foreach (($agencies ?? []) as $index => $agency): ?>
                <?php $left = max(6, min(94, (($agency['lng'] + 10) / 130) * 100)); $top = max(6, min(94, 100 - (($agency['lat'] + 5) / 60) * 100)); ?>
                <?= Ui::button('<span>' . ((int) $index + 1) . '</span>', ['html' => true, 'variant' => 'plain', 'type' => 'button', 'class' => 'site-map-marker', 'data-map-marker' => true, 'data-code' => (string) ($agency['code'] ?? ''), 'style' => 'left:' . $left . '%;top:' . $top . '%;', 'aria-label' => (string) ($agency['name'] ?? '')]) ?>
            <?php endforeach; ?>
        </div>
        <p class="site-map-note">Carte de démonstration sans dépendance externe. Les coordonnées sont prêtes pour un branchement Leaflet/OpenStreetMap ensuite.</p>
    </div>
</section>
<?php $content = ob_get_clean(); require BASE_PATH . '/views/layouts/site.php';
