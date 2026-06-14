<?php
/** @var \App\Support\ViewBag $viewData */

use App\Helpers\Csrf;
use App\Helpers\View;
use App\View\Components\Form;
use App\View\Components\Ui;

$catalogs = isset($viewData) ? $viewData->array('catalogs') : ($catalogs ?? []);

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

        <section class="rh-settings-grid">
            <?php foreach ($catalogs as $catalog): ?>
                <article class="finea-section-card rh-settings-card">
                    <h2 class="finea-section-title"><?= View::e($catalog['title'] ?? '') ?></h2>

                    <form method="post" action="<?= View::url('rh/parametrage') ?>" class="rh-inline-form">
                        <?= Csrf::input() ?>
                        <?= Form::hidden('catalog', (string) ($catalog['key'] ?? '')) ?>
                        <?= Form::input('name', ['label' => 'Libellé', 'placeholder' => 'Libellé', 'required' => true]) ?>
                        <?php if (!empty($catalog['has_code'])): ?>
                            <?= Form::input('code', ['label' => 'Code', 'placeholder' => 'Code']) ?>
                        <?php endif; ?>
                        <?= Ui::button('Ajouter', ['variant' => 'primary', 'type' => 'submit']) ?>
                    </form>

                    <div class="rh-settings-list">
                        <?php foreach (($catalog['rows'] ?? []) as $row): ?>
                            <div class="rh-settings-row <?= (int) ($row['is_active'] ?? 0) === 1 ? '' : 'is-muted' ?>">
                                <form method="post" action="<?= View::url('rh/parametrage') ?>" class="rh-inline-form">
                                    <?= Csrf::input() ?>
                                    <?= Form::hidden('catalog', (string) ($catalog['key'] ?? '')) ?>
                                    <?= Form::hidden('id', (int) ($row['id'] ?? 0)) ?>
                                    <?= Form::input('name', ['label' => 'Libellé', 'value' => (string) ($row['name'] ?? ''), 'required' => true]) ?>
                                    <?php if (!empty($catalog['has_code'])): ?>
                                        <?= Form::input('code', ['label' => 'Code', 'value' => (string) ($row['code'] ?? ''), 'placeholder' => 'Code']) ?>
                                    <?php endif; ?>
                                    <?= Ui::button('Sauver', ['variant' => 'secondary', 'type' => 'submit']) ?>
                                </form>
                                <form method="post" action="<?= View::url('rh/parametrage/toggle') ?>">
                                    <?= Csrf::input() ?>
                                    <?= Form::hidden('catalog', (string) ($catalog['key'] ?? '')) ?>
                                    <?= Form::hidden('id', (int) ($row['id'] ?? 0)) ?>
                                    <?= Ui::button((int) ($row['is_active'] ?? 0) === 1 ? 'Actif' : 'Inactif', ['variant' => 'plain', 'type' => 'submit', 'class' => 'rh-mini-toggle']) ?>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    </div>
</div>
<?php $content = ob_get_clean(); require BASE_PATH . '/views/layouts/module.php';
