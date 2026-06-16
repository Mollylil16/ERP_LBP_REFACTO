<?php
/** @var \App\Support\ViewBag $viewData */

use App\Helpers\View;
use App\View\Components\Ui;

$data = isset($viewData) ? $viewData->array('data') : ($data ?? []);
$total = isset($viewData) ? $viewData->int('total') : ($total ?? 0);
$page = isset($viewData) ? $viewData->int('page') : ($page ?? 1);

ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Légal & Fiscal',
            'Paramètres de Paie',
            'Définissez les variables annuelles pour le calcul de la paie (SMIG, Taux CNPS, CMU, etc.).',
            Ui::button('Nouveau paramétrage', ['href' => 'rh/parametres-paie/nouveau', 'variant' => 'secondary']),
            ['class' => 'rh-hero']
        ) ?>

        <section class="finea-table-wrap" style="margin-top: 24px;">
            <table class="finea-table">
                <thead>
                    <tr>
                        <th>Année</th>
                        <th>SMIG (FCFA)</th>
                        <th>Plafond CNPS</th>
                        <th>Taux CNPS (Salarial / Patronal)</th>
                        <th>Taux CMU</th>
                        <th>Taux CN</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data)): ?>
                        <tr>
                            <td colspan="7">
                                <?= Ui::emptyState('Aucun paramètre défini', 'Commencez par ajouter le paramétrage pour l\'année en cours.') ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($data as $row): ?>
                            <tr>
                                <td><strong><?= View::e($row['year']) ?></strong></td>
                                <td><?= number_format((float)$row['smig'], 0, ',', ' ') ?></td>
                                <td><?= number_format((float)$row['cnps_ceiling'], 0, ',', ' ') ?></td>
                                <td>
                                    Salarial: <?= View::e($row['cnps_employee_rate']) ?>%<br>
                                    <small>Patronal: <?= View::e($row['cnps_employer_rate']) ?>%</small>
                                </td>
                                <td><?= View::e($row['cmu_employee_rate']) ?>%</td>
                                <td><?= View::e($row['cn_rate']) ?>%</td>
                                <td>
                                    <?= Ui::button('Modifier', ['href' => "rh/parametres-paie/{$row['id']}/modifier", 'variant' => 'plain', 'size' => 'sm']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </div>
</div>
<?php $content = ob_get_clean(); require BASE_PATH . '/views/layouts/module.php'; ?>
