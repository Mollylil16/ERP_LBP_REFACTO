<?php

use App\Helpers\Csrf;
use App\Helpers\View;
use App\Security\PermissionEntityRegistry;

require BASE_PATH . '/views/rh/_navigation.php';
$optionsHtml = static function (array $rows, mixed $selected): void {
    foreach ($rows as $row) {
        echo '<option value="' . (int) $row['id'] . '" ' . ((string) $selected === (string) $row['id'] ? 'selected' : '') . '>' . View::e($row['name']) . '</option>';
    }
};
ob_start();
?>
<div class="finea-shell"><div class="finea-container">
    <section class="finea-page-header rh-hero">
        <div><p class="rh-eyebrow">Mobilite interne</p><h1>Mutation de <?= View::e($employee['full_name']) ?></h1><p>Changer l'affectation tout en conservant une trace complete.</p></div>
        <a class="finea-action-btn finea-action-btn--secondary" href="<?= View::url('rh/personnel/' . (int) $employee['id']) ?>">Retour au dossier</a>
    </section>
    <?php require BASE_PATH . '/views/rh/_restricted-data.php'; ?>
    <form method="post" action="<?= View::url('rh/personnel/' . (int) $employee['id'] . '/mutation') ?>" class="finea-section-card rh-operation-form">
        <?= Csrf::input() ?>
        <div class="rh-form-grid">
            <div class="finea-field"><label>Date d'effet *</label><input class="finea-input" required type="date" name="effective_date" value="<?= date('Y-m-d') ?>"></div>
            <div class="finea-field"><label>Titre</label><input class="finea-input" name="title" value="Mutation / affectation RH"></div>
            <?php if (!isset($restrictedTables[PermissionEntityRegistry::RH_SERVICES])): ?><div class="finea-field"><label>Nouveau service</label><select class="finea-select" name="service_id"><option value="">Conserver</option><?php $optionsHtml($options['services'], $employee['service_id']); ?></select></div><?php endif; ?>
            <?php if (!isset($restrictedTables[PermissionEntityRegistry::RH_FUNCTIONS])): ?><div class="finea-field"><label>Nouvelle fonction</label><select class="finea-select" name="function_id"><option value="">Conserver</option><?php $optionsHtml($options['functions'], $employee['function_id']); ?></select></div><?php endif; ?>
            <?php if (!isset($restrictedTables[PermissionEntityRegistry::RH_STATUSES])): ?><div class="finea-field"><label>Nouveau statut</label><select class="finea-select" name="status_id"><option value="">Conserver</option><?php $optionsHtml($options['statuses'], $employee['status_id']); ?></select></div><?php endif; ?>
            <div class="finea-field"><label>Nouveau site</label><input class="finea-input" name="site" value="<?= View::e($employee['site']) ?>"></div>
            <div class="finea-field"><label>Nouvelle prise de service</label><input class="finea-input" type="date" name="start_date" value="<?= View::e($employee['start_date']) ?>"></div>
            <div class="finea-field rh-field-wide"><label>Motif</label><textarea class="finea-input" rows="5" name="reason"></textarea></div>
        </div>
        <div class="rh-form-actions"><button class="finea-action-btn finea-action-btn--primary">Enregistrer la mutation</button></div>
    </form>
</div></div>
<?php $content = ob_get_clean(); require BASE_PATH . '/views/layouts/module.php';
