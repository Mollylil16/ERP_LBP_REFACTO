<?php
use App\Helpers\View;

/** @var array $grouped */
/** @var string $search */
/** @var string $agenceLabel */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bilan Départs Colis</title>
    <style>
        table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 11px; }
        th { background-color: #0f172a; color: #ffffff; font-weight: bold; text-align: left; padding: 6px; }
        td { padding: 5px; border: 1px solid #cbd5e1; }
        .header-title { font-size: 16px; font-weight: bold; color: #0369a1; }
        .meta { background-color: #f8fafc; font-weight: bold; }
        .group-head { background-color: #e0f2fe; font-weight: bold; font-size: 12px; color: #0369a1; }
        .text-right { text-align: right; }
        .parti { color: #15803d; font-weight: bold; }
        .reste { color: #b91c1c; font-weight: bold; }
    </style>
</head>
<body>

    <table>
        <tr>
            <td colspan="7" class="header-title">LABELLEPORTE ERP — BILAN DES DÉPARTS & COLIS RESTÉS EN AGENCE</td>
        </tr>
        <tr>
            <td colspan="7">Généré le : <?= date('d/m/Y H:i:s') ?> | Agence : <?= View::e($agenceLabel) ?> | Filtre : <?= View::e($search ?: 'Aucun') ?></td>
        </tr>
        <tr><td colspan="7"></td></tr>

        <?php if (empty($grouped)): ?>
            <tr><td colspan="7" style="text-align:center;">Aucune donnée disponible.</td></tr>
        <?php else: ?>
            <?php foreach ($grouped as $g): ?>
                <tr class="group-head">
                    <td colspan="4">
                        CLIENT : <?= View::e($g['expediteur_name']) ?> (Tél: <?= View::e($g['expediteur_phone']) ?>) — SERVICE : <?= View::e($g['type_expediteur']) ?>
                    </td>
                    <td colspan="3" class="text-right">
                        Partis : <?= $g['nb_partis'] ?>/<?= $g['total_colis'] ?> | Restés : <?= $g['nb_restes'] ?>
                    </td>
                </tr>
                <tr style="background-color: #f1f5f9;">
                    <th>N° Tracking</th>
                    <th>Destinataire</th>
                    <th>Téléphone Dest.</th>
                    <th class="text-right">Poids (kg)</th>
                    <th>Statut Colis</th>
                    <th>État Départ</th>
                    <th>Motif (si resté)</th>
                </tr>
                <?php foreach ($g['colis'] as $c):
                    $stDep = $c['statut_depart'] ?? 'NON_SPECIFIE';
                    $isParti = $stDep === 'PARTI' || in_array($c['statut'], ['EN_TRANSIT', 'ARRIVÉ', 'LIVRÉ', 'RETIRÉ'], true);
                    $isReste = $stDep === 'RESTE';
                ?>
                    <tr>
                        <td><strong><?= View::e($c['numero_tracking']) ?></strong></td>
                        <td><?= View::e($c['destinataire_name']) ?></td>
                        <td><?= View::e($c['destinataire_phone']) ?></td>
                        <td class="text-right"><?= number_format((float)$c['poids_total'], 1, '.', '') ?></td>
                        <td><?= View::e($c['statut']) ?></td>
                        <td>
                            <?php if ($isParti): ?>
                                <span class="parti">PARTI</span>
                            <?php elseif ($isReste): ?>
                                <span class="reste">RESTÉ EN AGENCE</span>
                            <?php else: ?>
                                <span>EN ATTENTE</span>
                            <?php endif; ?>
                        </td>
                        <td style="color:#b91c1c;"><?= $isReste && !empty($c['motif_reste']) ? View::e($c['motif_reste']) : '' ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr><td colspan="7" style="border:none;"></td></tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>

</body>
</html>
