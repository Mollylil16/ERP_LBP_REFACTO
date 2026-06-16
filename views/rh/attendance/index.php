<?php
/** @var \App\Support\ViewBag $viewData */

use App\Helpers\Csrf;
use App\Helpers\View;
use App\View\Components\AttendanceRow;
use App\View\Components\Form;
use App\View\Components\Ui;

require BASE_PATH . '/views/rh/_navigation.php';

$date = isset($viewData) ? $viewData->string('date', date('Y-m-d')) : date('Y-m-d');
$month = isset($viewData) ? $viewData->int('month', (int) date('n')) : (int) date('n');
$year = isset($viewData) ? $viewData->int('year', (int) date('Y')) : (int) date('Y');
$rows = isset($viewData) ? $viewData->array('attendanceRows') : [];
$attendances = isset($viewData) ? $viewData->array('attendances') : [];

ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Pointage',
            'Présences journalières',
            'Cochez le personnel présent puis renseignez les heures d’arrivée et de sortie.',
            '<div class="finea-header-actions">'
                . Ui::button('Importer CSV', ['href' => 'rh/pointage/import', 'variant' => 'secondary'])
                . '</div>',
            ['class' => 'rh-hero']
        ) ?>

        <section class="finea-filter-card rh-attendance-toolbar">
            <form method="get" action="<?= View::url('rh/pointage') ?>" class="rh-attendance-date-form">
                <?= Form::input('date', [
                    'label' => 'Date de pointage',
                    'type' => 'date',
                    'value' => $date,
                    'required' => true,
                ]) ?>
                <?= Ui::button('Charger', ['variant' => 'primary', 'type' => 'submit']) ?>
            </form>
        </section>

        <form method="post" action="<?= View::url('rh/pointage') ?>" class="rh-attendance-sheet">
            <?= Csrf::input() ?>
            <?= Form::hidden('date', $date) ?>

            <section class="finea-section-card">
                <div class="rh-section-heading">
                    <div>
                        <p class="rh-eyebrow"><?= View::e(date('d/m/Y', strtotime($date) ?: time())) ?></p>
                        <h2 class="finea-section-title">Feuille de pointage</h2>
                    </div>
                    <?= Ui::badge(count($rows) . ' collaborateur(s)', 'info') ?>
                </div>

                <?php if ($rows === []): ?>
                    <?= Ui::emptyState('Aucun collaborateur actif', 'Ajoutez ou réactivez du personnel pour saisir le pointage.') ?>
                <?php else: ?>
                    <div class="rh-attendance-list">
                        <?php foreach ($rows as $row): ?>
                            <?= AttendanceRow::render($row) ?>
                        <?php endforeach; ?>
                    </div>
                    <div class="rh-form-actions">
                        <?= Ui::button('Enregistrer le pointage', ['variant' => 'primary', 'type' => 'submit']) ?>
                    </div>
                <?php endif; ?>
            </section>
        </form>

        <section class="finea-section-card rh-recent-section">
            <div class="rh-section-heading">
                <div>
                    <p class="rh-eyebrow">Historique</p>
                    <h2 class="finea-section-title">Pointages du mois</h2>
                </div>
                <form method="get" action="<?= View::url('rh/pointage') ?>" class="rh-attendance-month-form">
                    <?= Form::hidden('date', $date) ?>
                    <?= Form::input('month', ['label' => 'Mois', 'type' => 'number', 'value' => (string) $month, 'min' => '1', 'max' => '12']) ?>
                    <?= Form::input('year', ['label' => 'Année', 'type' => 'number', 'value' => (string) $year, 'min' => '2020']) ?>
                    <?= Ui::button('Voir', ['variant' => 'secondary', 'type' => 'submit']) ?>
                </form>
            </div>
            <?php if ($attendances === []): ?>
                <?= Ui::emptyState('Aucun pointage trouvé', 'Aucune donnée de présence pour cette période.') ?>
            <?php else: ?>
                <div class="finea-table-wrap">
                    <table class="finea-table">
                        <thead>
                        <tr><th>Date</th><th>Matricule</th><th>Employé</th><th>Arrivée</th><th>Sortie</th><th>Total</th><th>Sup.</th><th>Statut</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($attendances as $row): ?>
                            <tr>
                                <td><?= View::e((string) ($row['date'] ?? '')) ?></td>
                                <td><?= View::e((string) ($row['employee_number'] ?? '')) ?></td>
                                <td><?= View::e((string) ($row['full_name'] ?? '')) ?></td>
                                <td><?= View::e((string) (($row['check_in'] ?? '') ?: '-')) ?></td>
                                <td><?= View::e((string) (($row['check_out'] ?? '') ?: '-')) ?></td>
                                <td><?= number_format((float) ($row['total_hours'] ?? 0), 2, ',', ' ') ?> h</td>
                                <td><?= number_format((float) ($row['overtime_hours'] ?? 0), 2, ',', ' ') ?> h</td>
                                <td><?= Ui::badge((string) ($row['status'] ?? ''), ($row['status'] ?? '') === 'present' ? 'success' : 'warning') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php $content = ob_get_clean(); require BASE_PATH . '/views/layouts/module.php';
