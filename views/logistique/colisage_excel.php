<?php
use App\Helpers\View;

/** @var string $dateStart */
/** @var string $dateEnd */
/** @var string $agenceName */
/** @var array<int, array<string, mixed>> $parcels */

$dateDisplay = ($dateStart === $dateEnd) 
    ? date('d/m/Y', strtotime($dateStart)) 
    : 'du ' . date('d/m/Y', strtotime($dateStart)) . ' au ' . date('d/m/Y', strtotime($dateEnd));

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        table { border-collapse: collapse; width: 100%; }
        th { background-color: #0f172a; color: #ffffff; font-weight: bold; border: 1px solid #000000; text-align: left; padding: 6px; }
        td { border: 1px solid #cccccc; padding: 6px; vertical-align: top; }
        .header-title { font-size: 16pt; font-weight: bold; color: #0f172a; }
        .meta { font-size: 10pt; color: #475569; }
        .number { text-align: right; }
        .center { text-align: center; }
        .total-row { background-color: #f1f5f9; font-weight: bold; }
    </style>
</head>
<body>

    <table>
        <tr>
            <td colspan="10" class="header-title">ERP LBP - SUIVI COLISAGE LOGISTIQUE (SANS MONTANT)</td>
        </tr>
        <tr>
            <td colspan="10" class="meta">
                <b>Agence :</b> <?= View::e($agenceName) ?> | 
                <b>Période :</b> <?= View::e($dateDisplay) ?> | 
                <b>Généré le :</b> <?= date('d/m/Y H:i') ?>
            </td>
        </tr>
        <tr><td colspan="10"></td></tr>
        <thead>
            <tr>
                <th>N° Tracking</th>
                <th>Heure Saisie</th>
                <th>Expéditeur</th>
                <th>Destinataire</th>
                <th>Téléphone Destinataire</th>
                <th>Nombre Colis</th>
                <th>Poids Total (kg)</th>
                <th>Trajet / Code Transport</th>
                <th>Agence Départ</th>
                <th>Agence Arrivée</th>
                <th>Agent Saisisseur</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $sumColis = 0;
            $sumPoids = 0.0;
            foreach ($parcels as $p): 
                $sumColis += (int) $p['nombre_colis'];
                $sumPoids += (float) $p['poids_total'];
                $trajetDisplay = $p['trajet_code'] 
                    ? ($p['trajet_code'] . ' - ' . $p['trajet_libelle']) 
                    : ($p['col_trajet'] ?: ($p['type_expediteur'] ?? 'Non spécifié'));
            ?>
                <tr>
                    <td>'<?= View::e($p['numero_tracking']) ?></td>
                    <td class="center"><?= date('H:i', strtotime($p['created_at'])) ?></td>
                    <td><?= View::e($p['expediteur_name'] ?: 'Passager / Standard') ?></td>
                    <td><?= View::e($p['destinataire_name'] ?: 'Non renseigné') ?></td>
                    <td>'<?= View::e($p['destinataire_phone'] ?: '-') ?></td>
                    <td class="center"><?= (int) $p['nombre_colis'] ?></td>
                    <td class="number"><?= number_format((float) $p['poids_total'], 2, '.', '') ?></td>
                    <td><?= View::e($trajetDisplay) ?></td>
                    <td><?= View::e($p['agence_depart_name'] ?: '-') ?></td>
                    <td><?= View::e($p['agence_arrivee_name'] ?: '-') ?></td>
                    <td><?= View::e($p['agent_name']) ?></td>
                    <td><?= View::e($p['statut']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="5" style="text-align: right;"><b>TOTAL :</b></td>
                <td class="center"><b><?= $sumColis ?></b></td>
                <td class="number"><b><?= number_format($sumPoids, 2, '.', '') ?></b></td>
                <td colspan="5"></td>
            </tr>
        </tfoot>
    </table>

</body>
</html>
