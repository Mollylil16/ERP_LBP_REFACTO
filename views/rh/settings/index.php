<?php
/** @var \App\Support\ViewBag $viewData */

use App\Helpers\Csrf;
use App\Helpers\View;
use App\View\Components\Form;
use App\View\Components\Geolocation;
use App\View\Components\Tabs;
use App\View\Components\Ui;

$catalogs = isset($viewData) ? $viewData->array('catalogs') : ($catalogs ?? []);
$activeCatalog = isset($viewData) ? $viewData->string('activeCatalog') : ($activeCatalog ?? '');
$catalog = $catalogs[$activeCatalog] ?? reset($catalogs) ?: [];

$tabDescriptions = [
    'services' => 'Organisation',
    'functions' => 'Postes',
    'statuses' => 'Contrats',
    'exit_reasons' => 'Départs',
    'document_types' => 'Dossiers',
    'sites' => 'Implantations',
];
$tabs = [];
foreach ($catalogs as $key => $item) {
    $tabs[] = [
        'key' => (string) $key,
        'label' => (string) ($item['title'] ?? ''),
        'description' => $tabDescriptions[$key] ?? '',
        'count' => count($item['rows'] ?? []),
        'href' => 'rh/parametrage?catalog=' . rawurlencode((string) $key),
    ];
}

ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Référentiels',
            'Paramétrage RH',
            'Services, fonctions, statuts, sites/points de vente, motifs de sortie et types de documents utilisés par les formulaires RH.',
            Ui::button('Intégrer un collaborateur', ['href' => 'rh/personnel/nouveau', 'variant' => 'secondary']),
            ['class' => 'rh-hero']
        ) ?>

        <?= Tabs::render($tabs, $activeCatalog, [
            'class' => 'rh-settings-tabs',
            'aria-label' => 'Catégories de paramétrage RH',
        ]) ?>

        <section class="finea-section-card rh-settings-card">
            <div class="finea-section-heading">
                <div>
                    <p class="rh-eyebrow">Référentiel actif</p>
                    <h2 class="finea-section-title"><?= View::e($catalog['title'] ?? '') ?></h2>
                </div>
                <?= Ui::badge(count($catalog['rows'] ?? []) . ' élément(s)', 'neutral') ?>
            </div>

            <form method="post" action="<?= View::url('rh/parametrage') ?>" class="rh-inline-form rh-settings-create-form">
                <?= Csrf::input() ?>
                <?= Form::hidden('catalog', (string) ($catalog['key'] ?? '')) ?>
                <?= Form::input('name', [
                    'label' => 'Libellé',
                    'placeholder' => 'Nouveau libellé',
                    'required' => true,
                    'id' => 'new_catalog_name',
                ]) ?>
                <?php if (!empty($catalog['has_code'])): ?>
                    <?= Form::input('code', [
                        'label' => 'Code',
                        'placeholder' => 'Généré automatiquement si vide',
                        'id' => 'new_catalog_code',
                    ]) ?>
                <?php endif; ?>
                <?php if (($catalog['key'] ?? '') === 'sites'): ?>
                    <?= Geolocation::fields(['idPrefix' => 'new_site']) ?>
                <?php endif; ?>
                <?= Ui::button('Ajouter', ['variant' => 'primary', 'type' => 'submit']) ?>
            </form>

            <div class="rh-settings-list">
                <?php if (($catalog['rows'] ?? []) === []): ?>
                    <?= Ui::emptyState('Aucun élément', 'Ajoutez le premier élément de ce référentiel.') ?>
                <?php endif; ?>

                <?php foreach (($catalog['rows'] ?? []) as $row): ?>
                    <?php $rowId = (int) ($row['id'] ?? 0); ?>
                    <article class="rh-settings-row <?= (int) ($row['is_active'] ?? 0) === 1 ? '' : 'is-muted' ?>">
                        <form method="post" action="<?= View::url('rh/parametrage') ?>" class="rh-inline-form rh-settings-edit-form">
                            <?= Csrf::input() ?>
                            <?= Form::hidden('catalog', (string) ($catalog['key'] ?? '')) ?>
                            <?= Form::hidden('id', $rowId) ?>
                            <?= Form::input('name', [
                                'label' => 'Libellé',
                                'value' => (string) ($row['name'] ?? ''),
                                'required' => true,
                                'id' => 'catalog_name_' . $rowId,
                            ]) ?>
                            <?php if (!empty($catalog['has_code'])): ?>
                                <?= Form::input('code', [
                                    'label' => 'Code',
                                    'value' => (string) ($row['code'] ?? ''),
                                    'placeholder' => 'Code',
                                    'id' => 'catalog_code_' . $rowId,
                                ]) ?>
                            <?php endif; ?>
                            <?php if (($catalog['key'] ?? '') === 'sites'): ?>
                                <?= Geolocation::fields([
                                    'latitude' => $row['latitude'] ?? '',
                                    'longitude' => $row['longitude'] ?? '',
                                    'address' => $row['address'] ?? '',
                                    'idPrefix' => 'site_' . $rowId,
                                    'compact' => true,
                                ]) ?>
                            <?php endif; ?>
                            <?= Ui::button('Sauver', ['variant' => 'secondary', 'type' => 'submit']) ?>
                        </form>
                        <form method="post" action="<?= View::url('rh/parametrage/toggle') ?>">
                            <?= Csrf::input() ?>
                            <?= Form::hidden('catalog', (string) ($catalog['key'] ?? '')) ?>
                            <?= Form::hidden('id', $rowId) ?>
                            <?= Ui::button(
                                (int) ($row['is_active'] ?? 0) === 1 ? 'Actif' : 'Inactif',
                                ['variant' => 'plain', 'type' => 'submit']
                            ) ?>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</div>
<?php $content = ob_get_clean(); require BASE_PATH . '/views/layouts/module.php';
