<?php

use App\Helpers\Csrf;
use App\Helpers\View;

require BASE_PATH . '/views/rh/_navigation.php';
ob_start();
?>
<div class="finea-shell"><div class="finea-container">
    <section class="finea-page-header rh-hero">
        <div><p class="rh-eyebrow">Mouvement du personnel</p><h1>Sortie / reintegration</h1><p><?= View::e($employee['full_name']) ?> - <?= View::e($employee['employee_number']) ?></p></div>
        <a class="finea-action-btn finea-action-btn--secondary" href="<?= View::url('rh/personnel/' . (int) $employee['id']) ?>">Retour au dossier</a>
    </section>

    <?php if ((int) $employee['is_active'] === 1): ?>
        <form method="post" action="<?= View::url('rh/personnel/' . (int) $employee['id'] . '/sortie') ?>" class="finea-section-card rh-operation-form">
            <?= Csrf::input() ?>
            <h2 class="finea-section-title">Declarer une sortie</h2>
            <div class="rh-form-grid">
                <div class="finea-field"><label>Date de sortie *</label><input class="finea-input" required type="date" name="exit_date" value="<?= date('Y-m-d') ?>"></div>
                <div class="finea-field"><label>Motif</label><select class="finea-select" name="exit_reason_id"><option value="">Non renseigne</option><?php foreach ($options['exitReasons'] as $reason): ?><option value="<?= (int) $reason['id'] ?>"><?= View::e($reason['name']) ?></option><?php endforeach; ?></select></div>
                <div class="finea-field rh-field-wide"><label>Observations</label><textarea class="finea-input" rows="5" name="exit_notes"></textarea></div>
            </div>
            <div class="rh-form-actions"><button class="finea-action-btn finea-action-btn--danger">Confirmer la sortie</button></div>
        </form>
    <?php else: ?>
        <section class="finea-section-card rh-operation-form">
            <h2 class="finea-section-title">Collaborateur sorti</h2>
            <p>Sortie le <?= View::e($employee['exit_date']) ?>. <?= View::e($employee['exit_reason_name'] ?: '') ?></p>
            <form method="post" action="<?= View::url('rh/personnel/' . (int) $employee['id'] . '/reintegration') ?>" class="rh-compact-form">
                <?= Csrf::input() ?>
                <div class="finea-field"><label>Date de reintegration *</label><input class="finea-input" required type="date" name="start_date" value="<?= date('Y-m-d') ?>"></div>
                <button class="finea-action-btn finea-action-btn--primary">Reintegrer dans les effectifs</button>
            </form>
        </section>
    <?php endif; ?>
</div></div>
<?php $content = ob_get_clean(); require BASE_PATH . '/views/layouts/module.php';
