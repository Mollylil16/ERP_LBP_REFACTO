<?php
use App\Helpers\View;

/** @var array<int, array<string, mixed>> $rows */
/** @var array<string, array<string, mixed>> $credMap */
/** @var string $date */
/** @var int|null $agenceId */
/** @var string $agenceLabel */

$totalColis = 0;
$totalPoids = 0.0;
$totalCaXof = 0.0;
$totalCaEur = 0.0;
$totalRetires = 0;
$totalHorsDelai = 0;
$totalCreditsNonRegle = 0.0;
$totalCreditsRegle = 0.0;

foreach ($rows as $r) {
    $totalColis += (int) $r['nb_colis'];
    $totalPoids += (float) $r['poids_total'];
    $totalCaXof += (float) $r['ca_xof'];
    $totalCaEur += (float) $r['ca_eur'];
    $totalRetires += (int) $r['nb_retires'];
    $totalHorsDelai += (int) $r['nb_hors_delai'];
    $cr = $credMap[$r['agence']] ?? [];
    $totalCreditsNonRegle += (float) ($cr['credits_non_regle_xof'] ?? 0);
    $totalCreditsRegle += (float) ($cr['credits_regle_xof'] ?? 0);
}

$dateFormatted = date('d/m/Y', strtotime($date));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport Journalier par Agence — <?= htmlspecialchars($dateFormatted) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 11px; color: #1e293b; margin: 20px; background: #fff; }

        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #0f172a; padding-bottom: 12px; margin-bottom: 16px; }
        .logo { font-size: 18px; font-weight: bold; color: #0f172a; letter-spacing: 0.5px; }
        .logo small { font-size: 11px; font-weight: normal; color: #64748b; display: block; margin-top: 2px; }
        .title { font-size: 14px; font-weight: bold; text-align: right; color: #334155; }
        .title small { font-size: 10px; font-weight: normal; color: #64748b; display: block; margin-top: 2px; }

        .meta-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 14px; margin-bottom: 16px; display: flex; justify-content: space-between; }
        .meta-box strong { color: #0f172a; }

        .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 16px; }
        .summary-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 12px; text-align: center; }
        .summary-card .value { font-size: 16px; font-weight: bold; color: #0f172a; }
        .summary-card .label { font-size: 9px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px; }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #0f172a; color: #ffffff; padding: 7px 8px; font-weight: bold; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.3px; }
        td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; font-size: 10px; }
        tr:nth-child(even) { background-color: #f8fafc; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .total-row { font-weight: bold; background: #e2e8f0 !important; font-size: 11px; }
        .text-danger { color: #ef4444; }
        .text-success { color: #22c55e; }
        .text-blue { color: #0369a1; }

        .footer { margin-top: 20px; padding-top: 10px; border-top: 1px solid #e2e8f0; font-size: 9px; color: #94a3b8; display: flex; justify-content: space-between; }

        @media print {
            body { margin: 0; }
            .no-print { display: none !important; }
            .summary-grid { grid-template-columns: repeat(4, 1fr); }
        }
    </style>
</head>
<body>

    <div class="no-print" style="margin-bottom: 15px; text-align: right; display: flex; gap: 8px; justify-content: flex-end;">
        <button onclick="window.history.back()" style="padding: 8px 16px; background: #f1f5f9; color: #374151; border: 1px solid #e2e8f0; border-radius: 4px; font-weight: 600; cursor: pointer; font-size: 12px;">
            ← Retour
        </button>
        <button onclick="window.print()" style="padding: 8px 16px; background: #0f172a; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; font-size: 12px;">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            Imprimer / Enregistrer en PDF
        </button>
    </div>

    <div class="header">
        <div class="logo">
            LABELLEPORTE ERP — FACTURATION
            <small>Rapport Journalier par Agence</small>
        </div>
        <div class="title">
            Journée du <?= htmlspecialchars($dateFormatted) ?>
            <small>Édité le <?= date('d/m/Y à H:i') ?></small>
        </div>
    </div>

    <div class="meta-box">
        <div>
            <strong>Date :</strong> <?= htmlspecialchars($dateFormatted) ?><br>
            <strong>Agence :</strong> <?= htmlspecialchars($agenceLabel) ?>
        </div>
        <div style="text-align: right;">
            <strong>Colis reçus :</strong> <?= number_format($totalColis) ?><br>
            <strong>Poids total :</strong> <?= number_format($totalPoids, 1, ',', ' ') ?> kg<br>
            <strong>CA total :</strong> <?= number_format($totalCaXof, 0, ',', ' ') ?> XOF
        </div>
    </div>

    <div class="summary-grid">
        <div class="summary-card">
            <div class="value"><?= number_format($totalColis) ?></div>
            <div class="label">Colis reçus</div>
        </div>
        <div class="summary-card">
            <div class="value"><?= number_format($totalPoids, 1, ',', ' ') ?> kg</div>
            <div class="label">Poids total</div>
        </div>
        <div class="summary-card">
            <div class="value"><?= number_format($totalCaXof, 0, ',', ' ') ?></div>
            <div class="label">CA XOF</div>
        </div>
        <div class="summary-card">
            <div class="value" style="color:<?= $totalHorsDelai > 0 ? '#ef4444' : '#22c55e' ?>"><?= number_format($totalHorsDelai) ?></div>
            <div class="label">Hors délai</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Agence</th>
                <th class="text-right">Colis reçus</th>
                <th class="text-right">Retirés</th>
                <th class="text-right">Poids (kg)</th>
                <th class="text-right">CA XOF</th>
                <th class="text-right">CA EUR</th>
                <th class="text-right">Hors délai</th>
                <th class="text-right">Crédits non réglés</th>
                <th class="text-right">Crédits réglés (jour)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="9" class="text-center" style="padding: 20px; color: #94a3b8;">Aucune donnée pour cette date.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $r):
                    $cr = $credMap[$r['agence']] ?? [];
                    $nonRegle = (float) ($cr['credits_non_regle_xof'] ?? 0);
                    $regleJour = (float) ($cr['credits_regle_xof'] ?? 0);
                    $horsDelai = (int) $r['nb_hors_delai'];
                ?>
                    <tr>
                        <td><strong><?= htmlspecialchars((string) $r['agence']) ?></strong></td>
                        <td class="text-right text-blue"><?= number_format((int) $r['nb_colis']) ?></td>
                        <td class="text-right text-success"><?= number_format((int) $r['nb_retires']) ?></td>
                        <td class="text-right"><?= number_format((float) $r['poids_total'], 1, ',', ' ') ?></td>
                        <td class="text-right text-blue" style="font-weight:600;"><?= number_format((float) $r['ca_xof'], 0, ',', ' ') ?></td>
                        <td class="text-right"><?= (float) $r['ca_eur'] > 0 ? number_format((float) $r['ca_eur'], 2, ',', ' ') . ' €' : '—' ?></td>
                        <td class="text-right <?= $horsDelai > 0 ? 'text-danger' : 'text-success' ?>" style="font-weight:600;"><?= $horsDelai ?></td>
                        <td class="text-right <?= $nonRegle > 0 ? 'text-danger' : '' ?>"><?= $nonRegle > 0 ? number_format($nonRegle, 0, ',', ' ') . ' XOF' : '✓ 0' ?></td>
                        <td class="text-right <?= $regleJour > 0 ? 'text-success' : '' ?>"><?= $regleJour > 0 ? '+' . number_format($regleJour, 0, ',', ' ') . ' XOF' : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <?php if (!empty($rows)): ?>
        <tfoot>
            <tr class="total-row">
                <td>TOTAL GÉNÉRAL</td>
                <td class="text-right"><?= number_format($totalColis) ?></td>
                <td class="text-right"><?= number_format($totalRetires) ?></td>
                <td class="text-right"><?= number_format($totalPoids, 1, ',', ' ') ?> kg</td>
                <td class="text-right"><?= number_format($totalCaXof, 0, ',', ' ') ?> XOF</td>
                <td class="text-right"><?= $totalCaEur > 0 ? number_format($totalCaEur, 2, ',', ' ') . ' €' : '—' ?></td>
                <td class="text-right"><?= number_format($totalHorsDelai) ?></td>
                <td class="text-right"><?= number_format($totalCreditsNonRegle, 0, ',', ' ') ?> XOF</td>
                <td class="text-right"><?= number_format($totalCreditsRegle, 0, ',', ' ') ?> XOF</td>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>

    <div class="footer">
        <div>LABELLEPORTE — Système ERP — Document généré automatiquement</div>
        <div><?= date('d/m/Y H:i:s') ?></div>
    </div>

</body>
</html>
