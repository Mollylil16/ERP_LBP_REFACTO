<?php
use App\Helpers\Csrf;
use App\Helpers\View;
use App\View\Components\Form;
use App\View\Components\Geolocation;
use App\View\Components\Rh;
use App\View\Components\Tabs;
use App\View\Components\Ui;
use App\View\Pages\Rh\SettingsPage;

/** @var SettingsPage $page */

?>
<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Parametrage RH',
            'Services, fonctions, statuts, sites/points de vente, motifs de sortie et types de documents utilises par les formulaires RH.',
            [
                'eyebrow' => 'Referentiels',
                'class' => 'rh-hero',
                'actions' => [Ui::button('Integrer un collaborateur', [
                    'href' => 'rh/personnel/nouveau',
                    'variant' => 'secondary',
                ])],
            ]
        ) ?>

        <?= Tabs::render($page->tabs, $page->activeCatalog, [
            'class' => 'rh-settings-tabs',
            'aria-label' => 'Categories de parametrage RH',
        ]) ?>

        <?php ob_start(); ?>
        <form method="post" action="<?= View::url('rh/parametrage') ?>" class="rh-inline-form rh-settings-create-form">
            <?= Csrf::input() ?>
            <?= Form::hidden('catalog', (string) ($page->catalog['key'] ?? '')) ?>
            <?= Form::input('name', [
                'label' => 'Libelle',
                'placeholder' => 'Nouveau libelle',
                'required' => true,
                'id' => 'new_catalog_name',
            ]) ?>
            <?php if (!empty($page->catalog['has_code'])): ?>
                <?= Form::input('code', [
                    'label' => 'Code',
                    'placeholder' => 'Genere automatiquement si vide',
                    'id' => 'new_catalog_code',
                ]) ?>
            <?php endif; ?>
            <?php if (($page->catalog['key'] ?? '') === 'sites'): ?>
                <?= Geolocation::fields(['idPrefix' => 'new_site']) ?>
            <?php endif; ?>
            <?= Ui::button('Ajouter', ['variant' => 'primary', 'type' => 'submit']) ?>
        </form>

        <div class="rh-settings-list">
            <?php if (($page->catalog['rows'] ?? []) === []): ?>
                <?= Ui::emptyState('Aucun element', 'Ajoutez le premier element de ce referentiel.') ?>
            <?php endif; ?>

            <?php foreach (($page->catalog['rows'] ?? []) as $row): ?>
                <?php $rowId = (int) ($row['id'] ?? 0); ?>
                <article class="rh-settings-row <?= (int) ($row['is_active'] ?? 0) === 1 ? '' : 'is-muted' ?>">
                    <form method="post" action="<?= View::url('rh/parametrage') ?>" class="rh-inline-form rh-settings-edit-form">
                        <?= Csrf::input() ?>
                        <?= Form::hidden('catalog', (string) ($page->catalog['key'] ?? '')) ?>
                        <?= Form::hidden('id', $rowId) ?>
                        <?= Form::input('name', [
                            'label' => 'Libelle',
                            'value' => (string) ($row['name'] ?? ''),
                            'required' => true,
                            'id' => 'catalog_name_' . $rowId,
                        ]) ?>
                        <?php if (!empty($page->catalog['has_code'])): ?>
                            <?= Form::input('code', [
                                'label' => 'Code',
                                'value' => (string) ($row['code'] ?? ''),
                                'placeholder' => 'Code',
                                'id' => 'catalog_code_' . $rowId,
                            ]) ?>
                        <?php endif; ?>
                        <?php if (($page->catalog['key'] ?? '') === 'sites'): ?>
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
                        <?= Form::hidden('catalog', (string) ($page->catalog['key'] ?? '')) ?>
                        <?= Form::hidden('id', $rowId) ?>
                        <?= Ui::button(
                            (int) ($row['is_active'] ?? 0) === 1 ? 'Actif' : 'Inactif',
                            ['variant' => 'plain', 'type' => 'submit']
                        ) ?>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
        <?= Rh::card((string) ob_get_clean(), [
            'class' => 'rh-settings-card',
            'eyebrow' => 'Referentiel actif',
            'title' => (string) ($page->catalog['title'] ?? ''),
            'actions' => [
                Ui::badge(count($page->catalog['rows'] ?? []) . ' element(s)', 'neutral'),
            ],
        ]) ?>
    </div>
</div>
