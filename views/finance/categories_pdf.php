<?php
/**
 * Vue PDF d'Impression — Synthèse et Recouvrement par Catégorie de Code (13 Codes)
 */
use App\Helpers\View;

$dateGen = date('d/m/Y H:i');
$totalVolume = 0;
$totalMontantGlobal = 0.0;
$totalPayeGlobal = 0.0;
$totalNonPayeGlobal = 0.0;

foreach ($stats as $st) {
    $totalVolume += (int)$st['total_count'];
    $totalMontantGlobal += (float)$st['total_montant'];
    $totalPayeGlobal += (float)$st['montant_paye'];
    $totalNonPayeGlobal += (float)$st['montant_non_paye'];
}

$tauxGlobal = $totalMontantGlobal > 0 ? round(($totalPayeGlobal / $totalMontantGlobal) * 100, 1) : 0.0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Synthèse Recouvrement par Catégorie — LBP</title>
    <style>
        body { font-family: 'Segoe UI', Helvetica, Arial, sans-serif; color: #0f172a; margin: 0; padding: 25px; background: #fff; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #2563eb; padding-bottom: 15px; margin-bottom: 25px; }
        .logo { font-size: 24px; font-weight: 900; color: #2563eb; letter-spacing: -0.5px; }
        .subtitle { font-size: 13px; color: #64748b; margin-top: 3px; }
        .badge-pdf { background: #0f172a; color: #fff; padding: 6px 12px; border-radius: 6px; font-weight: 800; font-size: 12px; }
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 25px; }
        .kpi-card { background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px 15px; }
        .kpi-label { font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 700; }
        .kpi-val { font-size: 18px; font-weight: 900; margin-top: 4px; color: #0f172a; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 12px; }
        th { background: #0f172a; color: #fff; font-weight: 800; text-align: left; padding: 10px 12px; }
        td { padding: 9px 12px; border-bottom: 1px solid #e2e8f0; }
        tr:nth-child(even) { background: #f8fafc; }
        tfoot tr { background: #f1f5f9; font-weight: 900; border-top: 2px solid #0f172a; }
        .progress-bar { background: #e2e8f0; height: 6px; border-radius: 3px; overflow: hidden; margin-top: 3px; width: 80px; display: inline-block; vertical-align: middle; }
        .progress-fill { height: 100%; }
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px; text-align: right;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #2563eb; color: #fff; border: none; border-radius: 6px; font-weight: 800; cursor: pointer;">
            🖨️ Imprimer / Sauvegarder en PDF
        </button>
    </div>

    <div class="header">
        <div>
            <div class="logo">LA BELLE PORTE (LBP)</div>
            <div class="subtitle">RAPPORT ANALYTIQUE DE RECOUVREMENT PAR CATÉGORIE DE CODE (13 CODES)</div>
        </div>
        <div>
            <span class="badge-pdf">DOCUMENT OFFICIEL</span>
            <div style="font-size: 11px; color: #64748b; text-align: right; margin-top: 5px;">Émis le <?= $dateGen ?></div>
        </div>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-label">Volume Total Factures</div>
            <div class="kpi-val"><?= number_format($totalVolume, 0, ',', ' ') ?> factures</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Total Chiffre d'Affaires</div>
            <div class="kpi-val"><?= number_format($totalMontantGlobal, 0, ',', ' ') ?> XOF</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Encaissements Effectués</div>
            <div class="kpi-val" style="color: #16a34a;"><?= number_format($totalPayeGlobal, 0, ',', ' ') ?> XOF</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Créances Restant Dues</div>
            <div class="kpi-val" style="color: #dc2626;"><?= number_format($totalNonPayeGlobal, 0, ',', ' ') ?> XOF</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Code Catégorie</th>
                <th>Volume</th>
                <th style="text-align: right;">Montant Total</th>
                <th style="text-align: right;">Encaissé (Payé)</th>
                <th style="text-align: right;">Reste à Payer (Créance)</th>
                <th style="text-align: right;">Taux Recouvrement</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($stats as $st): ?>
                <?php
                $taux = (float)$st['taux_recouvrement'];
                $barColor = $taux >= 80 ? '#16a34a' : ($taux >= 50 ? '#d97706' : '#dc2626');
                ?>
                <tr>
                    <td><strong><?= View::e($st['code']) ?></strong></td>
                    <td><?= $st['total_count'] ?> facture(s)</td>
                    <td style="text-align: right; font-weight: 700;"><?= number_format($st['total_montant'], 0, ',', ' ') ?> XOF</td>
                    <td style="text-align: right; color: #16a34a; font-weight: 700;"><?= number_format($st['montant_paye'], 0, ',', ' ') ?> XOF</td>
                    <td style="text-align: right; color: #dc2626; font-weight: 700;"><?= number_format($st['montant_non_paye'], 0, ',', ' ') ?> XOF</td>
                    <td style="text-align: right;">
                        <span style="font-weight: 800; color: <?= $barColor ?>;"><?= $taux ?>%</span>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?= min(100, $taux) ?>%; background: <?= $barColor ?>;"></div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td>TOTAL RÉSEAU GLOBAL</td>
                <td><?= $totalVolume ?> factures</td>
                <td style="text-align: right;"><?= number_format($totalMontantGlobal, 0, ',', ' ') ?> XOF</td>
                <td style="text-align: right; color: #16a34a;"><?= number_format($totalPayeGlobal, 0, ',', ' ') ?> XOF</td>
                <td style="text-align: right; color: #dc2626;"><?= number_format($totalNonPayeGlobal, 0, ',', ' ') ?> XOF</td>
                <td style="text-align: right; color: #2563eb;"><?= $tauxGlobal ?>%</td>
            </tr>
        </tfoot>
    </table>

    <script>
        window.onload = function() {
            if (window.location.search.indexOf('print=1') !== -1) {
                window.print();
            }
        };
    </script>
</body>
</html>
