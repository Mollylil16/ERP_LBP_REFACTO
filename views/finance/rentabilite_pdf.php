<?php
use App\Helpers\View;

/** @var array<int, array<string, mixed>> $trajets */
/** @var array<string, mixed> $summary */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport Rentabilité (P&L Logistique) - LBP Transit</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 11px; color: #1e293b; margin: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #0f172a; padding-bottom: 12px; margin-bottom: 16px; }
        .logo { font-size: 18px; font-weight: bold; color: #0f172a; }
        .title { font-size: 14px; font-weight: bold; text-align: right; color: #334155; }
        .meta-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 14px; margin-bottom: 16px; display: flex; justify-content: space-between; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #0f172a; color: #ffffff; padding: 7px 8px; font-weight: bold; text-align: left; font-size: 10px; text-transform: uppercase; }
        td { padding: 8px; border-bottom: 1px solid #e2e8f0; font-size: 10px; }
        tr:nth-child(even) { background-color: #f8fafc; }
        .text-right { text-align: right; }
        .total-row { font-weight: bold; background: #e2e8f0 !important; }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="margin-bottom: 15px; text-align: right;">
        <button onclick="window.print()" style="padding: 8px 16px; background: #0f172a; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            Imprimer / Enregistrer en PDF
        </button>
    </div>

    <div class="header">
        <div class="logo">LA BELLE PORTE TRANSIT — FINANCE</div>
        <div class="title">Rapport de Rentabilité par Trajet (P&L)</div>
    </div>

    <div class="meta-box">
        <div><strong>Généré le :</strong> <?= date('d/m/Y H:i') ?></div>
        <div><strong>Recettes Totales :</strong> <?= number_format($summary['total_recettes'] ?? 0.0, 0, ',', ' ') ?> XOF</div>
        <div><strong>Débours Prestataires :</strong> <?= number_format($summary['total_depenses'] ?? 0.0, 0, ',', ' ') ?> XOF</div>
        <div><strong>Marge Nette Globale :</strong> <strong><?= number_format($summary['marge_nette'] ?? 0.0, 0, ',', ' ') ?> XOF</strong></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Code Lot</th>
                <th>Trajet & Type Transport</th>
                <th class="text-right">Recettes Factures</th>
                <th class="text-right">Débours Prestataires</th>
                <th class="text-right">Marge Nette</th>
                <th class="text-right">Taux Marge</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($trajets as $t): ?>
                <tr>
                    <td><strong><?= View::e($t['code']) ?></strong></td>
                    <td><?= View::e($t['libelle']) ?> (<?= View::e($t['type_transport']) ?>)</td>
                    <td class="text-right" style="color: #15803d; font-weight: bold;"><?= number_format((float) $t['total_recettes'], 0, ',', ' ') ?> XOF</td>
                    <td class="text-right" style="color: #b91c1c; font-weight: bold;"><?= number_format((float) $t['total_depenses'], 0, ',', ' ') ?> XOF</td>
                    <td class="text-right" style="font-weight: bold;"><?= number_format((float) $t['marge_nette'], 0, ',', ' ') ?> XOF</td>
                    <td class="text-right" style="font-weight: bold;"><?= number_format((float) $t['taux_marge'], 1, ',', ' ') ?> %</td>
                </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="2">TOTAL GLOBAL</td>
                <td class="text-right"><?= number_format($summary['total_recettes'] ?? 0.0, 0, ',', ' ') ?> XOF</td>
                <td class="text-right"><?= number_format($summary['total_depenses'] ?? 0.0, 0, ',', ' ') ?> XOF</td>
                <td class="text-right"><?= number_format($summary['marge_nette'] ?? 0.0, 0, ',', ' ') ?> XOF</td>
                <td class="text-right"><?= number_format($summary['taux_marge'] ?? 0.0, 1, ',', ' ') ?> %</td>
            </tr>
        </tbody>
    </table>

</body>
</html>
