<?php
use App\Helpers\View;

/** @var array<string, mixed> $agingBuckets */
/** @var array<int, array<string, mixed>> $clientDetails */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport Balance Âgée des Créances - LBP Transit</title>
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
        <div class="logo">LA BELLE PORTE TRANSIT — RECOUVREMENT</div>
        <div class="title">Balance Âgée des Créances Clients</div>
    </div>

    <div class="meta-box">
        <div><strong>Date d'émission :</strong> <?= date('d/m/Y H:i') ?></div>
        <div><strong>0 - 30j :</strong> <?= number_format($agingBuckets['b30'] ?? 0.0, 0, ',', ' ') ?> XOF</div>
        <div><strong>31 - 60j :</strong> <?= number_format($agingBuckets['b60'] ?? 0.0, 0, ',', ' ') ?> XOF</div>
        <div><strong>61 - 90j :</strong> <?= number_format($agingBuckets['b90'] ?? 0.0, 0, ',', ' ') ?> XOF</div>
        <div><strong>+90j :</strong> <?= number_format($agingBuckets['bPlus90'] ?? 0.0, 0, ',', ' ') ?> XOF</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Client</th>
                <th class="text-right">0 - 30 jours</th>
                <th class="text-right">31 - 60 jours</th>
                <th class="text-right">61 - 90 jours</th>
                <th class="text-right">+ 90 jours</th>
                <th class="text-right">Total Reste à Payer</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($clientDetails as $c): ?>
                <tr>
                    <td><strong><?= View::e($c['client_name']) ?></strong> <small>(<?= View::e($c['phone']) ?>)</small></td>
                    <td class="text-right"><?= number_format((float) $c['b30'], 0, ',', ' ') ?> XOF</td>
                    <td class="text-right"><?= number_format((float) $c['b60'], 0, ',', ' ') ?> XOF</td>
                    <td class="text-right"><?= number_format((float) $c['b90'], 0, ',', ' ') ?> XOF</td>
                    <td class="text-right" style="color: #dc2626; font-weight: bold;"><?= number_format((float) $c['bPlus90'], 0, ',', ' ') ?> XOF</td>
                    <td class="text-right" style="font-weight: bold;"><?= number_format((float) $c['total'], 0, ',', ' ') ?> XOF</td>
                </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td>TOTAL GÉNÉRAL</td>
                <td class="text-right"><?= number_format($agingBuckets['b30'] ?? 0.0, 0, ',', ' ') ?> XOF</td>
                <td class="text-right"><?= number_format($agingBuckets['b60'] ?? 0.0, 0, ',', ' ') ?> XOF</td>
                <td class="text-right"><?= number_format($agingBuckets['b90'] ?? 0.0, 0, ',', ' ') ?> XOF</td>
                <td class="text-right" style="color: #dc2626;"><?= number_format($agingBuckets['bPlus90'] ?? 0.0, 0, ',', ' ') ?> XOF</td>
                <td class="text-right"><?= number_format($agingBuckets['total'] ?? 0.0, 0, ',', ' ') ?> XOF</td>
            </tr>
        </tbody>
    </table>

</body>
</html>
