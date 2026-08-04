<?php
use App\Helpers\View;

/** @var array<int, array<string, mixed>> $results */
/** @var int $startMonth */
/** @var int $startYear */
/** @var int $endMonth */
/** @var int $endYear */
/** @var string $agenceLabel */
/** @var string $selectedTrajet */

$months = View::monthNames();

$periodeStr = $months[$startMonth] . ' ' . $startYear . ' à ' . $months[$endMonth] . ' ' . $endYear;

?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <style>
        th { background-color: #0f172a; color: #ffffff; font-weight: bold; border: 1px solid #000000; }
        td { border: 1px solid #cccccc; }
        .num { mso-number-format:"\#\,\#\#0"; }
        .dec { mso-number-format:"0\.00"; }
    </style>
</head>
<body>
    <table>
        <tr>
            <th colspan="10" style="font-size: 14pt; background-color: #ffffff; color: #000000; text-align: left;">
                EXPLOITATION FACTURATION — RAPPORT DÉTAILLÉ (AVEC MONTANTS)
            </th>
        </tr>
        <tr>
            <td colspan="10" style="background-color: #ffffff;">
                <strong>Période :</strong> <?= View::e($periodeStr) ?> | 
                <strong>Agence :</strong> <?= View::e($agenceLabel) ?> | 
                <strong>Trajet :</strong> <?= View::e($selectedTrajet === 'all' ? 'Tous les trajets' : $selectedTrajet) ?> | 
                <strong>Généré le :</strong> <?= date('d/m/Y H:i:s') ?>
            </td>
        </tr>
        <tr></tr>
        <thead>
            <tr>
                <th>N° Facture</th>
                <th>Date Émission</th>
                <th>Heure Émission</th>
                <th>Agent Créateur</th>
                <th>Agence</th>
                <th>Code Trajet</th>
                <th>Libellé Trajet & Transport</th>
                <th>Client</th>
                <th>Poids (kg)</th>
                <th>Nombre Colis</th>
                <th>Montant Total (XOF)</th>
                <th>Montant Encaissé</th>
                <th>Montant Restant</th>
                <th>Devise</th>
                <th>Statut / Modifications</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $totMontant = 0.0;
            $totPoids = 0.0;
            $totColis = 0;
            foreach ($results as $r):
                $dt = new \DateTime($r['date_emission']);
                $modifsCount = (int) ($r['modifications_count'] ?? 0);
                $totMontant += (float) $r['montant_total'];
                $totPoids += (float) $r['poids_total'];
                $totColis += (int) $r['nombre_colis'];
            ?>
                <tr>
                    <td><?= View::e($r['numero_facture']) ?></td>
                    <td><?= $dt->format('d/m/Y') ?></td>
                    <td><?= $dt->format('H:i:s') ?></td>
                    <td><?= View::e($r['agent_name']) ?></td>
                    <td><?= View::e($r['agence_name']) ?></td>
                    <td><?= View::e($r['trajet_code'] ?? $r['col_trajet'] ?? '') ?></td>
                    <td><?= View::e(($r['trajet_libelle'] ?? '') . ' (' . ($r['trajet_type_transport'] ?? $r['type_expediteur']) . ')') ?></td>
                    <td><?= View::e($r['client_name']) ?> (<?= View::e($r['client_phone'] ?? '') ?>)</td>
                    <td class="dec"><?= (float) $r['poids_total'] ?></td>
                    <td><?= (int) $r['nombre_colis'] ?></td>
                    <td class="num"><?= (float) $r['montant_total'] ?></td>
                    <td class="num"><?= (float) $r['montant_encaisse'] ?></td>
                    <td class="num"><?= (float) $r['montant_restant'] ?></td>
                    <td><?= View::e($r['devise']) ?></td>
                    <td><?= $modifsCount > 0 ? 'Modifiée (' . $modifsCount . ' modifs)' : 'Verrouillée' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight: bold; background-color: #f1f5f9;">
                <td colspan="8">TOTAL GÉNÉRAL</td>
                <td class="dec"><?= $totPoids ?></td>
                <td><?= $totColis ?></td>
                <td class="num"><?= $totMontant ?></td>
                <td colspan="4"></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
