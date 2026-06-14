<?php
use App\Helpers\Csrf;
use App\Helpers\View;
ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <section class="finea-page-header rh-hero">
            <div>
                <p class="rh-eyebrow">Référentiels</p>
                <h1>Paramétrage RH</h1>
                <p>Services, fonctions, statuts, motifs de sortie et types de documents utilisés par les formulaires RH.</p>
            </div>
            <a class="finea-action-btn finea-action-btn--secondary" href="<?= View::url('rh/personnel/nouveau') ?>">Intégrer un collaborateur</a>
        </section>
        <section class="rh-settings-grid">
            <?php foreach ($catalogs as $catalog): ?>
                <article class="finea-section-card rh-settings-card">
                    <h2 class="finea-section-title"><?= View::e($catalog['title']) ?></h2>
                    <form method="post" action="<?= View::url('rh/parametrage') ?>" class="rh-inline-form">
                        <?= Csrf::input() ?>
                        <input type="hidden" name="catalog" value="<?= View::e($catalog['key']) ?>">
                        <input class="finea-input" name="name" placeholder="Libellé" required>
                        <?php if ($catalog['has_code']): ?><input class="finea-input" name="code" placeholder="Code"><?php endif; ?>
                        <button class="finea-action-btn finea-action-btn--primary">Ajouter</button>
                    </form>
                    <div class="rh-settings-list">
                        <?php foreach ($catalog['rows'] as $row): ?>
                            <div class="rh-settings-row <?= (int)$row['is_active'] === 1 ? '' : 'is-muted' ?>">
                                <form method="post" action="<?= View::url('rh/parametrage') ?>" class="rh-inline-form">
                                    <?= Csrf::input() ?>
                                    <input type="hidden" name="catalog" value="<?= View::e($catalog['key']) ?>">
                                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                    <input class="finea-input" name="name" value="<?= View::e($row['name']) ?>" required>
                                    <?php if ($catalog['has_code']): ?><input class="finea-input" name="code" value="<?= View::e($row['code']) ?>" placeholder="Code"><?php endif; ?>
                                    <button class="finea-action-btn finea-action-btn--secondary">Sauver</button>
                                </form>
                                <form method="post" action="<?= View::url('rh/parametrage/toggle') ?>">
                                    <?= Csrf::input() ?>
                                    <input type="hidden" name="catalog" value="<?= View::e($catalog['key']) ?>">
                                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                    <button class="rh-mini-toggle" type="submit"><?= (int)$row['is_active'] === 1 ? 'Actif' : 'Inactif' ?></button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    </div>
</div>
<?php $content = ob_get_clean(); require BASE_PATH . '/views/layouts/module.php'; ?>
