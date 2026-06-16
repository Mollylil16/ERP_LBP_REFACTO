<?php
use App\Helpers\View;
use App\View\Components\Ui;

require BASE_PATH . '/views/rh/_navigation.php';

ob_start();
?>

<?= Ui::pageHeader(
    'Bulletin de Paie',
    'Détails du salaire et impression',
    ['actions' => '
        <a href="<?= View::url('rh/paie/campagnes/' . $payslip['campaign_id'] . '/bulletins') ?>" class="finea-action-btn finea-action-btn--secondary">
            <i class="finea-icon">arrow_back</i> Retour
        </a>
        <button type="button" class="finea-action-btn finea-action-btn--primary" onclick="window.print()">
            <i class="finea-icon">print</i> Imprimer
        </button>
    ']
) ?>

<style>
    .payslip-container {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 2rem;
        max-width: 900px;
        margin: 0 auto;
        color: #1e293b;
    }
    .payslip-header {
        display: flex;
        justify-content: space-between;
        border-bottom: 2px solid #e2e8f0;
        padding-bottom: 1rem;
        margin-bottom: 2rem;
    }
    .payslip-company h2 { margin: 0 0 0.5rem 0; font-size: 1.5rem; }
    .payslip-company p { margin: 0; color: #64748b; font-size: 0.875rem; }
    .payslip-title { text-align: right; }
    .payslip-title h1 { margin: 0; font-size: 1.75rem; color: #0f172a; text-transform: uppercase; }
    .payslip-title p { margin: 0.5rem 0 0 0; font-size: 1rem; font-weight: bold; }
    
    .payslip-emp-info {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
        margin-bottom: 2rem;
        background: #f8fafc;
        padding: 1.5rem;
        border-radius: 6px;
    }
    .emp-info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
        font-size: 0.875rem;
    }
    .emp-info-grid span:nth-child(odd) { font-weight: bold; color: #475569; }

    .payslip-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 2rem;
    }
    .payslip-table th, .payslip-table td {
        padding: 0.75rem;
        border: 1px solid #e2e8f0;
        font-size: 0.875rem;
    }
    .payslip-table th { background: #f1f5f9; text-align: left; font-weight: 600; }
    .payslip-table .num { text-align: right; }
    .payslip-table .gain { color: #16a34a; }
    .payslip-table .deduction { color: #dc2626; }
    
    .payslip-totals {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }
    .totals-box {
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 1.5rem;
    }
    .net-box {
        background: #f0fdf4;
        border-color: #bbf7d0;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-align: center;
    }
    .net-box span { font-size: 1rem; color: #166534; font-weight: bold; text-transform: uppercase; }
    .net-box strong { font-size: 2.5rem; color: #15803d; line-height: 1; margin-top: 0.5rem; }

    @media print {
        body { background: #fff; }
        .finea-sidebar, .finea-header, .finea-page-header .actions { display: none !important; }
        .finea-main { margin: 0 !important; padding: 0 !important; }
        .payslip-container { border: none; padding: 0; box-shadow: none; }
    }
</style>

<div class="payslip-container">
    <div class="payslip-header">
        <div class="payslip-company">
            <h2>ERP LBP</h2>
            <p>123 Avenue de l'Entreprise</p>
            <p>Abidjan, Côte d'Ivoire</p>
        </div>
        <div class="payslip-title">
            <h1>Bulletin de Paie</h1>
            <p>Période : <?= str_pad($payslip['month'], 2, '0', STR_PAD_LEFT) ?> / <?= $payslip['year'] ?></p>
        </div>
    </div>

    <div class="payslip-emp-info">
        <div class="emp-info-grid">
            <span>Matricule</span> <span><?= htmlspecialchars($payslip['employee_number']) ?></span>
            <span>Nom complet</span> <span><?= htmlspecialchars($payslip['full_name']) ?></span>
            <span>Date d'embauche</span> <span><?= htmlspecialchars($payslip['hire_date']) ?></span>
            <span>Ancienneté</span> <span>
                <?php 
                    $d1 = new DateTime($payslip['hire_date']);
                    $d2 = new DateTime($payslip['year'].'-'.$payslip['month'].'-01');
                    echo $d1->diff($d2)->y . ' an(s)';
                ?>
            </span>
        </div>
        <div class="emp-info-grid">
            <span>Département</span> <span><?= htmlspecialchars($payslip['service_name'] ?? '-') ?></span>
            <span>Fonction</span> <span><?= htmlspecialchars($payslip['function_name'] ?? '-') ?></span>
            <span>Statut</span> <span><?= htmlspecialchars($payslip['marital_status'] ?? '-') ?></span>
            <span>Enfant(s)</span> <span><?= htmlspecialchars($payslip['children_count'] ?? '0') ?></span>
        </div>
    </div>

    <table class="payslip-table">
        <thead>
            <tr>
                <th>Désignation</th>
                <th class="num">Base</th>
                <th class="num">Taux</th>
                <th class="num">Gains</th>
                <th class="num">Retenues</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($payslip['lines'] as $line): ?>
            <tr>
                <td><?= htmlspecialchars($line['label']) ?></td>
                <td class="num"><?= number_format($line['base'], 0, ',', ' ') ?></td>
                <td class="num"><?= $line['rate'] ? number_format($line['rate'], 2, ',', ' ') . ' %' : '-' ?></td>
                
                <?php if ($line['type'] === 'gain'): ?>
                    <td class="num gain"><?= number_format($line['amount'], 0, ',', ' ') ?></td>
                    <td class="num">-</td>
                <?php else: ?>
                    <td class="num">-</td>
                    <td class="num deduction"><?= number_format($line['amount'], 0, ',', ' ') ?></td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="payslip-totals">
        <div class="totals-box">
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                <span>Salaire Brut :</span>
                <strong><?= number_format($payslip['gross_salary'], 0, ',', ' ') ?> FCFA</strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                <span>Indemnités Non Imposables :</span>
                <strong>
                    <?php
                    // Non taxable allowances = Total allowances - Taxable allowances
                    // Total allowances includes all allowances. Taxable allowances are already in gross salary.
                    // This is an approximation for display.
                    $taxable = 0;
                    foreach ($payslip['lines'] as $l) {
                        if ($l['type'] === 'gain' && $l['is_taxable'] && $l['label'] !== 'Salaire de Base' && $l['label'] !== 'Heures Supplementaires') {
                            $taxable += $l['amount'];
                        }
                    }
                    $nonTaxable = $payslip['total_allowances'] - $taxable;
                    echo number_format(max(0, $nonTaxable), 0, ',', ' ');
                    ?> FCFA
                </strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                <span>Total Retenues :</span>
                <strong style="color: #dc2626;">- <?= number_format($payslip['cnps_deduction'] + $payslip['cmu_deduction'] + $payslip['its_deduction'], 0, ',', ' ') ?> FCFA</strong>
            </div>
        </div>
        
        <div class="net-box">
            <span>Net à payer</span>
            <strong><?= number_format($payslip['net_salary'], 0, ',', ' ') ?> FCFA</strong>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
