<?php
use App\Helpers\Csrf;
use App\Helpers\View;
use App\View\Components\Ui;
use App\View\Components\Form;

require BASE_PATH . '/views/rh/_navigation.php';

ob_start();
?>

<?= Ui::pageHeader(
    'Pointage & Présences',
    'Suivi des heures de présence et des heures supplémentaires',
    ['actions' => '
        <a href="' . View::url('rh/pointage/nouveau') . '" class="finea-action-btn finea-action-btn--accent" style="margin-right: 8px;">
            <i class="finea-icon">add</i> Saisie manuelle
        </a>
        <a href="' . View::url('rh/pointage/import') . '" class="finea-action-btn finea-action-btn--secondary">
            <i class="finea-icon">upload_file</i> Importer CSV
        </a>
    ']
) ?>

<div class="finea-section-card">
    <div style="display: flex; gap: 1rem; align-items: flex-end; margin-bottom: 2rem;">
        <form method="get" action="<?= View::url('rh/pointage') ?>" style="display: flex; gap: 1rem; align-items: flex-end;">
            <?= Form::input('month', [
                'label' => 'Mois',
                'type' => 'number',
                'value' => (string)$month,
                'min' => '1',
                'max' => '12'
            ]) ?>
            <?= Form::input('year', [
                'label' => 'Année',
                'type' => 'number',
                'value' => (string)$year,
                'min' => '2020'
            ]) ?>
            <button type="submit" class="finea-action-btn finea-action-btn--primary">Filtrer</button>
        </form>
    </div>

    <?php if (empty($attendances)): ?>
        <?= Ui::emptyState('Aucun pointage trouvé', 'Aucune donnée de présence pour cette période.', 'event_busy') ?>
    <?php else: ?>
        <table class="finea-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Matricule</th>
                    <th>Employé</th>
                    <th>Entrée</th>
                    <th>Sortie</th>
                    <th>Total H.</th>
                    <th>H. Sup.</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($attendances as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['date']) ?></td>
                    <td><?= htmlspecialchars($row['employee_number']) ?></td>
                    <td><?= htmlspecialchars($row['full_name']) ?></td>
                    <td><?= htmlspecialchars($row['check_in'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['check_out'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['total_hours']) ?> h</td>
                    <td><?= htmlspecialchars($row['overtime_hours']) ?> h</td>
                    <td>
                        <span class="finea-badge finea-badge-<?= $row['status'] === 'present' ? 'success' : 'warning' ?>">
                            <?= htmlspecialchars(ucfirst($row['status'])) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
