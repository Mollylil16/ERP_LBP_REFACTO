<?php
use App\Helpers\View;

/** @var array $grouped */
/** @var array $rawColis */
/** @var string $search */
/** @var string $agenceLabel */

$totGrouped = count($grouped);
$totComplets = 0;
$totPartiels = 0;
$totColisPartis = 0;
$totColisRestes = 0;

foreach ($grouped as $g) {
    if ($g['nb_restes'] > 0) {
        $totPartiels++;
    } else {
        $totComplets++;
    }
    $totColisPartis += $g['nb_partis'];
    $totColisRestes += $g['nb_restes'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bilan des Départs & Colis Restés — <?= date('d/m/Y') ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 11px; color: #1e293b; margin: 20px; background: #fff; }

        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #0369a1; padding-bottom: 12px; margin-bottom: 16px; }
        .logo { font-size: 18px; font-weight: bold; color: #0369a1; letter-spacing: 0.5px; }
        .logo small { font-size: 11px; font-weight: normal; color: #64748b; display: block; margin-top: 2px; }
        .title { font-size: 14px; font-weight: bold; text-align: right; color: #334155; }
        .title small { font-size: 10px; font-weight: normal; color: #64748b; display: block; margin-top: 2px; }

        .meta-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 14px; margin-bottom: 16px; display: flex; justify-content: space-between; }

        .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 16px; }
        .summary-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 12px; text-align: center; }
        .summary-card .value { font-size: 16px; font-weight: bold; color: #0f172a; }
        .summary-card .label { font-size: 9px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px; }

        .group-card { background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; margin-bottom: 14px; page-break-inside: avoid; }
        .group-header { background: #f1f5f9; padding: 8px 12px; font-weight: bold; display: flex; justify-content: space-between; border-bottom: 1px solid #cbd5e1; }
        .badge-parti { background: #dcfce7; color: #15803d; padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: bold; }
        .badge-reste { background: #fee2e2; color: #b91c1c; padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: bold; }

        table { width: 100%; border-collapse: collapse; }
        th { background: #0f172a; color: #ffffff; padding: 6px 8px; font-weight: bold; text-align: left; font-size: 9px; text-transform: uppercase; }
        td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; font-size: 10px; }
        tr:nth-child(even) { background-color: #f8fafc; }
        .text-right { text-align: right; }

        .footer { margin-top: 20px; padding-top: 10px; border-top: 1px solid #e2e8f0; font-size: 9px; color: #94a3b8; display: flex; justify-content: space-between; }

        @media print {
            body { margin: 0; }
            .no-print { display: none !important; }
            .group-card { page-break-inside: avoid; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="margin-bottom: 15px; text-align: right; display: flex; gap: 8px; justify-content: flex-end;">
        <button onclick="window.history.back()" style="padding: 8px 16px; background: #f1f5f9; color: #374151; border: 1px solid #e2e8f0; border-radius: 4px; font-weight: 600; cursor: pointer; font-size: 12px;">
            ← Retour
        </button>
        <button onclick="window.print()" style="padding: 8px 16px; background: #0369a1; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; font-size: 12px;">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            Imprimer / Enregistrer en PDF
        </button>
    </div>

    <div class="header">
        <div class="logo">
            LABELLEPORTE ERP — CALL CENTER
            <small>Bilan des Départs & Colis Restés en Agence</small>
        </div>
        <div class="title">
            Édité le <?= date('d/m/Y à H:i') ?>
        </div>
    </div>

    <div class="meta-box">
        <div>
            <strong>Agence :</strong> <?= View::e($agenceLabel) ?><br>
            <strong>Filtre recherche :</strong> <?= View::e($search ?: 'Aucun (Tous)') ?>
        </div>
        <div style="text-align: right;">
            <strong>Envois suivis :</strong> <?= $totGrouped ?><br>
            <strong>Colis partis :</strong> <span style="color:#15803d;font-weight:bold;"><?= $totColisPartis ?></span> | 
            <strong>Colis restés :</strong> <span style="color:#b91c1c;font-weight:bold;"><?= $totColisRestes ?></span>
        </div>
    </div>

    <div class="summary-grid">
        <div class="summary-card">
            <div class="value"><?= $totGrouped ?></div>
            <div class="label">Lots d'Envois</div>
        </div>
        <div class="summary-card">
            <div class="value" style="color:#22c55e;"><?= $totComplets ?></div>
            <div class="label">Envois Complets</div>
        </div>
        <div class="summary-card">
            <div class="value" style="color:#f97316;"><?= $totPartiels ?></div>
            <div class="label">Envois Partiels</div>
        </div>
        <div class="summary-card">
            <div class="value" style="color:#ef4444;"><?= $totColisRestes ?></div>
            <div class="label">Colis Restés</div>
        </div>
    </div>

    <?php if (empty($grouped)): ?>
        <p style="text-align:center; padding: 20px; color:#94a3b8;">Aucun envoi trouvé pour ce rapport.</p>
    <?php else: ?>
        <?php foreach ($grouped as $g): ?>
            <div class="group-card">
                <div class="group-header">
                    <div>
                        CLIENT : <?= View::e($g['expediteur_name']) ?> 
                        (Tél: <?= View::e($g['expediteur_phone'] ?: '—') ?>) 
                        — <span style="color:#0369a1;">SERVICE : <?= View::e($g['type_expediteur']) ?></span>
                    </div>
                    <div>
                        <span class="badge-parti"><?= $g['nb_partis'] ?>/<?= $g['total_colis'] ?> Parti(s)</span>
                        <?php if ($g['nb_restes'] > 0): ?>
                            <span class="badge-reste"><?= $g['nb_restes'] ?> Resté(s)</span>
                        <?php endif; ?>
                    </div>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>N° Tracking</th>
                            <th>Destinataire</th>
                            <th class="text-right">Poids</th>
                            <th>Statut Colis</th>
                            <th>État Départ</th>
                            <th>Motif (si resté)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($g['colis'] as $c):
                            $stDep = $c['statut_depart'] ?? 'NON_SPECIFIE';
                            $isParti = $stDep === 'PARTI' || in_array($c['statut'], ['EN_TRANSIT', 'ARRIVÉ', 'LIVRÉ', 'RETIRÉ'], true);
                            $isReste = $stDep === 'RESTE';
                        ?>
                            <tr>
                                <td><strong><?= View::e($c['numero_tracking']) ?></strong></td>
                                <td><?= View::e($c['destinataire_name']) ?></td>
                                <td class="text-right"><?= number_format((float)$c['poids_total'], 1, ',', ' ') ?> kg</td>
                                <td><?= View::e($c['statut']) ?></td>
                                <td>
                                    <?php if ($isParti): ?>
                                        <span class="badge-parti">PARTI</span>
                                    <?php elseif ($isReste): ?>
                                        <span class="badge-reste">RESTÉ EN AGENCE</span>
                                    <?php else: ?>
                                        <span>En attente</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $isReste && !empty($c['motif_reste']) ? View::e($c['motif_reste']) : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="footer">
        <div>LABELLEPORTE ERP — Call Center & Logistique</div>
        <div>Rapport généré le <?= date('d/m/Y H:i:s') ?></div>
    </div>

</body>
</html>
