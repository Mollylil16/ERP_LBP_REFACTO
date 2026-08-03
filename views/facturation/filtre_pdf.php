<?php
use App\Helpers\View;

/** @var array<int, array<string, mixed>> $results */
/** @var int $startMonth */
/** @var int $startYear */
/** @var int $endMonth */
/** @var int $endYear */
/** @var string $agenceLabel */
/** @var string $selectedTrajet */

$months = [
    1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
    5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
    9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
];

$periodeStr = $months[$startMonth] . ' ' . $startYear . ' ➔ ' . $months[$endMonth] . ' ' . $endYear;

$totalFactures = count($results);
$totalMontant = 0.0;
$totalPoids = 0.0;
$totalColis = 0;

foreach ($results as $r) {
    $totalMontant += (float) ($r['montant_total'] ?? 0.0);
    $totalPoids += (float) ($r['poids_total'] ?? 0.0);
    $totalColis += (int) ($r['nombre_colis'] ?? 1);
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport Facturation - <?= View::e($periodeStr) ?></title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 11px; color: #1e293b; margin: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #0f172a; padding-bottom: 12px; margin-bottom: 16px; }
        .logo { font-size: 18px; font-weight: bold; color: #0f172a; }
        .title { font-size: 14px; font-weight: bold; text-align: right; color: #334155; }
        .meta-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 14px; margin-bottom: 16px; display: flex; justify-content: space-between; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #0f172a; color: #ffffff; padding: 7px 8px; font-weight: bold; text-align: left; font-size: 10px; text-transform: uppercase; }
        td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; font-size: 10px; }
        tr:nth-child(even) { background-color: #f8fafc; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .total-row { font-weight: bold; background: #e2e8f0 !important; }
        .badge { padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: bold; }
        .badge-locked { background: #e0f2fe; color: #0369a1; }
        .badge-modified { background: #fef3c7; color: #b45309; }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="margin-bottom: 15px; text-align: right;">
        <button onclick="window.print()" style="padding: 8px 16px; background: #0f172a; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            Imprimer / Enregistrer en PDF
        </button>
    </div>

    <div class="header">
        <div class="logo">LABELLEPORTE ERP — FACTURATION</div>
        <div class="title">Rapport Filtré de Facturation (Avec Montants)</div>
    </div>

    <div class="meta-box">
        <div>
            <strong>Période :</strong> <?= View::e($periodeStr) ?><br>
            <strong>Agence :</strong> <?= View::e($agenceLabel) ?><br>
            <strong>Trajet :</strong> <?= View::e($selectedTrajet === 'all' ? 'Tous les trajets' : $selectedTrajet) ?>
        </div>
        <div style="text-align: right;">
            <strong>Edité le :</strong> <?= date('d/m/Y H:i:s') ?><br>
            <strong>Nombre de factures :</strong> <?= $totalFactures ?><br>
            <strong>Chiffre d'Affaires :</strong> <?= number_format($totalMontant, 0, ',', ' ') ?> XOF
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>N° Facture</th>
                <th>Date & Heure</th>
                <th>Agent Créateur</th>
                <th>Agence</th>
                <th>Trajet</th>
                <th>Type</th>
                <th>Client</th>
                <th class="text-right">Poids / Colis</th>
                <th class="text-right">Montant Total</th>
                <th class="text-center">Statut</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($results)): ?>
                <tr>
                    <td colspan="10" class="text-center" style="padding: 20px;">Aucune facture trouvée.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($results as $r): ?>
                    <?php
                    $dt = new \DateTime($r['date_emission']);
                    $modifsCount = (int) ($r['modifications_count'] ?? 0);
                    ?>
                    <tr>
                        <td><strong><?= View::e($r['numero_facture']) ?></strong></td>
                        <td><?= $dt->format('d/m/Y H:i') ?></td>
                        <td><?= View::e($r['agent_name']) ?></td>
                        <td><?= View::e($r['agence_name']) ?></td>
                        <td><?= View::e(!empty($r['trajet_code']) ? $r['trajet_code'] : ($r['col_trajet'] ?? '-')) ?></td>
                        <td><?= View::e($r['trajet_type_transport'] ?? $r['type_expediteur']) ?></td>
                        <td><?= View::e($r['client_name']) ?></td>
                        <td class="text-right"><?= number_format((float)$r['poids_total'], 2, ',', ' ') ?> kg (<?= (int)$r['nombre_colis'] ?>)</td>
                        <td class="text-right"><strong><?= number_format((float)$r['montant_total'], 0, ',', ' ') ?> <?= View::e($r['devise']) ?></strong></td>
                        <td class="text-center">
                            <?php if ($modifsCount > 0): ?>
                                <span class="badge badge-modified">Modifiée (<?= $modifsCount ?>)</span>
                            <?php else: ?>
                                <span class="badge badge-locked">Verrouillée</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="7">TOTAL GÉNÉRAL</td>
                <td class="text-right"><?= number_format($totalPoids, 2, ',', ' ') ?> kg (<?= $totalColis ?> colis)</td>
                <td class="text-right"><?= number_format($totalMontant, 0, ',', ' ') ?> XOF</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

</body>
</html>
