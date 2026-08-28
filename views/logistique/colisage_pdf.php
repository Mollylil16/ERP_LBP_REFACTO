<?php
use App\Helpers\View;

/** @var string $dateStart */
/** @var string $dateEnd */
/** @var string $agenceName */
/** @var array<int, array<string, mixed>> $parcels */
/** @var int $totalSaisies */
/** @var int $totalNombreColis */
/** @var float $totalPoids */

$dateDisplay = ($dateStart === $dateEnd)
    ? date('d/m/Y', strtotime($dateStart))
    : 'du ' . date('d/m/Y', strtotime($dateStart)) . ' au ' . date('d/m/Y', strtotime($dateEnd));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Suivi Colisage Agence - <?= View::e($agenceName) ?> - <?= View::e($dateDisplay) ?></title>
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            color: #1e293b;
            margin: 0;
            padding: 15px;
            background: #fff;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .title {
            font-size: 16pt;
            font-weight: bold;
            color: #0f172a;
            margin: 0;
        }
        .subtitle {
            font-size: 9pt;
            color: #64748b;
            margin-top: 4px;
        }
        .meta-info {
            text-align: right;
            font-size: 9pt;
            color: #475569;
        }
        .kpi-bar {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            background: #f8fafc;
            padding: 10px 15px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }
        .kpi-item {
            flex: 1;
        }
        .kpi-label {
            font-size: 7.5pt;
            text-transform: uppercase;
            color: #64748b;
            font-weight: bold;
        }
        .kpi-value {
            font-size: 12pt;
            font-weight: bold;
            color: #0f172a;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }
        th, td {
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
            text-align: left;
            font-size: 8.5pt;
        }
        th {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 7.5pt;
        }
        tr:nth-child(even) {
            background-color: #fafafa;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .footer {
            margin-top: 20px;
            font-size: 8pt;
            color: #94a3b8;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
        }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="margin-bottom: 15px; text-align: right;">
        <button onclick="window.print()" style="padding: 8px 16px; background: #0f172a; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            Imprimer la fiche de suivi
        </button>
        <button onclick="window.close()" style="padding: 8px 16px; background: #64748b; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; margin-left: 8px;">
            Fermer
        </button>
    </div>

    <div class="header">
        <div>
            <div class="title">LABELLEPORTE — FICHE DE SUIVI COLISAGE</div>
            <div class="subtitle">Document Logistique Transversal (Exclusif Suivi Colis - Sans Montant Financier)</div>
        </div>
        <div class="meta-info">
            <div><strong>Agence :</strong> <?= View::e($agenceName) ?></div>
            <div><strong>Période :</strong> <?= View::e($dateDisplay) ?></div>
            <div><strong>Date d'impression :</strong> <?= date('d/m/Y H:i') ?></div>
        </div>
    </div>

    <div class="kpi-bar">
        <div class="kpi-item">
            <div class="kpi-label">Nombre de Saisies</div>
            <div class="kpi-value"><?= number_format($totalSaisies, 0, ',', ' ') ?></div>
        </div>
        <div class="kpi-item">
            <div class="kpi-label">Total Nombre de Colis</div>
            <div class="kpi-value"><?= number_format($totalNombreColis, 0, ',', ' ') ?></div>
        </div>
        <div class="kpi-item">
            <div class="kpi-label">Poids Total (Tonnage)</div>
            <div class="kpi-value"><?= number_format($totalPoids, 2, ',', ' ') ?> kg</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>N° Tracking</th>
                <th>Heure</th>
                <th>Expéditeur</th>
                <th>Destinataire</th>
                <th>Contact Destinataire</th>
                <th class="text-center">Nb Colis</th>
                <th class="text-right">Poids (kg)</th>
                <th>Trajet / Envoi</th>
                <th>Agence Départ / Arrivée</th>
                <th>Agent Saisisseur</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($parcels)): ?>
                <tr>
                    <td colspan="11" class="text-center" style="padding: 20px; color: #64748b;">
                        Aucun colis enregistré <?= View::e($dateDisplay) ?> pour cette sélection.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($parcels as $p): 
                    $trajetDisplay = $p['trajet_code'] 
                        ? ($p['trajet_code'] . ' (' . $p['trajet_libelle'] . ')') 
                        : ($p['col_trajet'] ?: ($p['type_expediteur'] ?? 'N/A'));
                ?>
                    <tr>
                        <td class="font-bold"><?= View::e($p['numero_tracking']) ?></td>
                        <td><?= date('H:i', strtotime($p['created_at'])) ?></td>
                        <td><?= View::e($p['expediteur_name'] ?: 'Passager') ?></td>
                        <td class="font-bold"><?= View::e($p['destinataire_name'] ?: 'N/A') ?></td>
                        <td><?= View::e($p['destinataire_phone'] ?: '-') ?></td>
                        <td class="text-center font-bold"><?= (int) $p['nombre_colis'] ?></td>
                        <td class="text-right font-bold"><?= number_format((float) $p['poids_total'], 2, ',', ' ') ?></td>
                        <td><?= View::e(str_replace('_', ' ➔ ', $trajetDisplay)) ?></td>
                        <td><?= View::e($p['agence_depart_name'] ?: '-') ?> ➔ <?= View::e($p['agence_arrivee_name'] ?: '-') ?></td>
                        <td><?= View::e($p['agent_name']) ?></td>
                        <td><?= View::e($p['statut']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr style="background: #f8fafc; font-weight: bold;">
                <td colspan="5" class="text-right">TOTAL GENERAL :</td>
                <td class="text-center"><?= number_format($totalNombreColis, 0, ',', ' ') ?></td>
                <td class="text-right"><?= number_format($totalPoids, 2, ',', ' ') ?> kg</td>
                <td colspan="4"></td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        ERP LBP - Module Logistique • Document généré automatiquement pour le suivi physique des colisages.
    </div>

    <script>
        window.addEventListener('load', function() {
            setTimeout(function() { window.print(); }, 500);
        });
    </script>

</body>
</html>
