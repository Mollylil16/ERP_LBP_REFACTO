<?php
use App\Helpers\View;
use App\View\Components\Ui;

require BASE_PATH . '/views/rh/_navigation.php';

ob_start();
?>

<?= Ui::pageHeader(
    'Bulletins de la campagne - ' . str_pad($campaign['month'], 2, '0', STR_PAD_LEFT) . '/' . $campaign['year'],
    'Visualisation de tous les bulletins de paie calculés.',
    ['actions' => '<a href="' . View::url('rh/paie/campagnes') . '" class="finea-action-btn finea-action-btn--secondary">Retour</a>']
) ?>

<div class="finea-section-card">
    <?php if (empty($payslips)): ?>
        <?= Ui::emptyState('Aucun bulletin', 'Il n\'y a aucun bulletin généré pour cette campagne.', 'request_quote') ?>
    <?php else: ?>
        <table class="finea-table">
            <thead>
                <tr>
                    <th>Matricule</th>
                    <th>Employé</th>
                    <th>Salaire Base</th>
                    <th>Indemnités</th>
                    <th>Heures Sup.</th>
                    <th>Retenues</th>
                    <th>Net à Payer</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payslips as $row): 
                    $totalDeductions = $row['cnps_deduction'] + $row['cmu_deduction'] + $row['its_deduction'];
                ?>
                <tr>
                    <td><?= htmlspecialchars($row['employee_number']) ?></td>
                    <td><?= htmlspecialchars($row['full_name']) ?></td>
                    <td><?= number_format($row['base_salary'], 0, ',', ' ') ?> FCFA</td>
                    <td><?= number_format($row['total_allowances'], 0, ',', ' ') ?> FCFA</td>
                    <td><?= number_format($row['overtime_pay'], 0, ',', ' ') ?> FCFA</td>
                    <td style="color: #dc2626;">-<?= number_format($totalDeductions, 0, ',', ' ') ?> FCFA</td>
                    <td style="font-weight: bold; color: #16a34a;"><?= number_format($row['net_salary'], 0, ',', ' ') ?> FCFA</td>
                    <td>
                        <a href="<?= View::url('rh/paie/bulletins/' . $row['id']) ?>" class="finea-action-btn finea-action-btn--secondary" style="min-height: 32px; padding: 4px 8px;">
                            <i class="finea-icon">visibility</i>
                        </a>
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
