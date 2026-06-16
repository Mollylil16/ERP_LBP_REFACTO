<?php
/** @var \App\Support\ViewBag $viewData */

use App\Helpers\View;
use App\View\Components\Ui;
use App\View\Components\Form;

$data = isset($viewData) ? $viewData->array('data') : ($data ?? []);
$total = isset($viewData) ? $viewData->int('total') : ($total ?? 0);
$page = isset($viewData) ? $viewData->int('page') : ($page ?? 1);

ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Contrats',
            'Gestion des contrats & Rémunérations',
            'Suivi du cycle de vie des contrats, de la rémunération de base et des indemnités fixes.',
            Ui::button('Nouveau Contrat', ['href' => 'rh/contrats/nouveau', 'variant' => 'secondary']),
            ['class' => 'rh-hero']
        ) ?>

        <section class="finea-filter-card">
            <form method="get" action="<?= View::url('rh/contrats') ?>" class="finea-filter-grid">
                <?= Form::input('search', [
                    'label' => 'Recherche',
                    'placeholder' => 'Nom ou matricule',
                    'value' => (string)($_GET['search'] ?? ''),
                ]) ?>
                <?= Form::select('status', [
                    '' => 'Tous les statuts',
                    'active' => 'En cours',
                    'terminated' => 'Terminé',
                    'renewed' => 'Renouvelé',
                ], [
                    'label' => 'Statut',
                    'value' => (string)($_GET['status'] ?? ''),
                ]) ?>
                <div class="finea-actions">
                    <?= Ui::button('Filtrer', ['variant' => 'primary', 'type' => 'submit']) ?>
                    <?php if (!empty($_GET['search']) || !empty($_GET['status'])): ?>
                        <?= Ui::button('Effacer', ['href' => 'rh/contrats', 'variant' => 'plain']) ?>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="finea-table-wrap">
            <table class="finea-table">
                <thead>
                    <tr>
                        <th>Employé</th>
                        <th>Type</th>
                        <th>Période</th>
                        <th>Salaire Base</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data)): ?>
                        <tr>
                            <td colspan="6">
                                <?= Ui::emptyState('Aucun contrat trouvé', 'Essayez de modifier vos filtres ou créez un nouveau contrat.') ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($data as $row): ?>
                            <tr>
                                <td>
                                    <strong><?= View::e($row['employee_name'] ?? '') ?></strong><br>
                                    <small><?= View::e($row['employee_number'] ?? '') ?></small>
                                </td>
                                <td><?= View::e($row['contract_type'] ?? '') ?></td>
                                <td>
                                    Du <?= View::date($row['start_date'] ?? '') ?><br>
                                    <small>Au <?= $row['end_date'] ? View::date($row['end_date']) : 'Indéterminé' ?></small>
                                </td>
                                <td><strong><?= number_format((float)($row['base_salary'] ?? 0), 0, ',', ' ') ?> FCFA</strong></td>
                                <td>
                                    <?php
                                    $status = $row['status'] ?? '';
                                    $badge = match ($status) {
                                        'active' => ['En cours', 'success'],
                                        'terminated' => ['Terminé', 'danger'],
                                        'renewed' => ['Renouvelé', 'info'],
                                        default => [$status, 'neutral']
                                    };
                                    echo Ui::badge($badge[0], $badge[1]);
                                    ?>
                                </td>
                                <td>
                                    <div class="finea-actions">
                                        <?= Ui::button('Détails', ['href' => "rh/contrats/{$row['id']}", 'variant' => 'secondary', 'size' => 'sm']) ?>
                                        <?= Ui::button('Modifier', ['href' => "rh/contrats/{$row['id']}/modifier", 'variant' => 'plain', 'size' => 'sm']) ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
        
        <?php if ($total > 20): ?>
            <!-- TODO: Pagination component -->
            <p style="text-align: center; margin-top: 15px; color: var(--finea-muted);">Pagination - Total: <?= $total ?> résultats</p>
        <?php endif; ?>
    </div>
</div>
<?php $content = ob_get_clean(); require BASE_PATH . '/views/layouts/module.php'; ?>
