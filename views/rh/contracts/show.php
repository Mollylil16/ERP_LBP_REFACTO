<?php
/** @var \App\Support\ViewBag $viewData */

use App\Helpers\View;
use App\View\Components\Ui;

$contract = isset($viewData) ? $viewData->array('contract') : [];
$allowances = $contract['allowances'] ?? [];

ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Détail du contrat',
            View::e($contract['employee_name'] ?? 'Inconnu'),
            'Type : ' . View::e($contract['contract_type'] ?? '') . ' | Statut : ' . View::e($contract['status'] ?? ''),
            Ui::button('Retour', ['href' => 'rh/contrats', 'variant' => 'secondary']),
            ['class' => 'rh-hero']
        ) ?>

        <div class="finea-grid" style="grid-template-columns: 1fr 1fr;">
            <section class="finea-section-card">
                <h3 class="finea-section-title">Informations Principales</h3>
                <table class="finea-table" style="min-width: 100%;">
                    <tr>
                        <th style="width: 40%;">Employé</th>
                        <td><strong><?= View::e($contract['employee_name'] ?? '') ?></strong></td>
                    </tr>
                    <tr>
                        <th>Type de contrat</th>
                        <td><?= View::e($contract['contract_type'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <th>Date de début</th>
                        <td><?= View::date($contract['start_date'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <th>Date de fin</th>
                        <td><?= $contract['end_date'] ? View::date($contract['end_date']) : 'N/A' ?></td>
                    </tr>
                    <tr>
                        <th>Fin période d'essai</th>
                        <td><?= $contract['trial_end_date'] ? View::date($contract['trial_end_date']) : 'N/A' ?></td>
                    </tr>
                    <tr>
                        <th>Statut</th>
                        <td>
                            <?php
                            $status = $contract['status'] ?? '';
                            $badge = match ($status) {
                                'active' => ['En cours', 'success'],
                                'terminated' => ['Terminé', 'danger'],
                                'renewed' => ['Renouvelé', 'info'],
                                default => [$status, 'neutral']
                            };
                            echo Ui::badge($badge[0], $badge[1]);
                            ?>
                        </td>
                    </tr>
                </table>
            </section>

            <section class="finea-section-card">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                    <h3 class="finea-section-title">Rémunération</h3>
                    <?= Ui::button('Modifier', ['href' => "rh/contrats/{$contract['id']}/modifier", 'variant' => 'plain', 'size' => 'sm']) ?>
                </div>

                <div style="padding: 20px; background: #f8fafc; border-radius: 12px; margin-bottom: 20px; border: 1px solid var(--finea-border);">
                    <p style="margin: 0; color: var(--finea-muted); font-size: 0.85rem; text-transform: uppercase; font-weight: 800;">Salaire de base</p>
                    <p style="margin: 5px 0 0; font-size: 1.8rem; font-weight: 900; color: var(--finea-navy);">
                        <?= number_format((float)($contract['base_salary'] ?? 0), 0, ',', ' ') ?> <small style="font-size: 1rem;">FCFA</small>
                    </p>
                </div>

                <h4 style="margin: 0 0 10px; color: var(--finea-navy);">Indemnités Fixes</h4>
                <?php if (empty($allowances)): ?>
                    <?= Ui::emptyState('Aucune indemnité', 'Ce contrat n\'a aucune indemnité paramétrée.') ?>
                <?php else: ?>
                    <table class="finea-table" style="min-width: 100%;">
                        <thead>
                            <tr>
                                <th>Libellé</th>
                                <th>Montant</th>
                                <th>Imposable (ITS)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totalAllowances = 0;
                            foreach ($allowances as $a): 
                                $totalAllowances += (float)$a['amount'];
                            ?>
                                <tr>
                                    <td><?= View::e($a['name']) ?></td>
                                    <td><strong><?= number_format((float)$a['amount'], 0, ',', ' ') ?> FCFA</strong></td>
                                    <td><?= !empty($a['is_taxable']) ? Ui::badge('Oui', 'warning') : Ui::badge('Non', 'success') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>TOTAL INDEMNITÉS</th>
                                <th colspan="2"><?= number_format($totalAllowances, 0, ',', ' ') ?> FCFA</th>
                            </tr>
                        </tfoot>
                    </table>
                <?php endif; ?>
            </section>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); require BASE_PATH . '/views/layouts/module.php'; ?>
