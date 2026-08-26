<?php
use App\Helpers\View;

/** @var array<string, mixed> $summary */
/** @var array<int, array<string, mixed>> $agenceRows */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bilan Consolidé Réseau — Clôture de Caisse - LBP Transit</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 11px; color: #0f172a; margin: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #0f172a; padding-bottom: 12px; margin-bottom: 16px; }
        .logo { font-size: 20px; font-weight: bold; color: #0f172a; letter-spacing: -0.5px; }
        .sub-logo { font-size: 11px; color: #64748b; font-weight: normal; margin-top: 2px; }
        .title { font-size: 14px; font-weight: bold; text-align: right; color: #0f172a; }
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 20px; }
        .kpi-card { background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px 12px; }
        .kpi-title { font-size: 9px; text-transform: uppercase; font-weight: 700; color: #64748b; }
        .kpi-value { font-size: 14px; font-weight: 800; color: #0f172a; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #0f172a; color: #ffffff; padding: 8px 10px; font-weight: bold; text-align: left; font-size: 10px; text-transform: uppercase; }
        td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; font-size: 10px; }
        tr:nth-child(even) { background-color: #f8fafc; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .total-row { font-weight: bold; background: #e2e8f0 !important; }
        .signatures { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-top: 40px; }
        .sig-box { border: 1px solid #cbd5e1; border-radius: 6px; padding: 12px; height: 90px; }
        .sig-title { font-weight: bold; font-size: 10px; text-transform: uppercase; color: #475569; margin-bottom: 40px; }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="margin-bottom: 15px; text-align: right;">
        <button onclick="window.print()" style="padding: 9px 18px; background: #0f172a; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            Imprimer / Enregistrer le Bilan Global (PDF)
        </button>
    </div>

    <div class="header">
        <div>
            <div class="logo">LA BELLE PORTE TRANSIT</div>
            <div class="sub-logo">Réseau International & Direction Générale — Service Caisse</div>
        </div>
        <div class="title">
            BILAN GLOBAL CONSOLIDÉ DES CAISSES<br>
            <span style="font-size:11px; font-weight:normal; color:#64748b;">Journée du <?= date('d/m/Y') ?></span>
        </div>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-title">Total Encaissements Réseau</div>
            <div class="kpi-value" style="color:#16a34a;"><?= number_format((float)($summary['total_encaisse'] ?? 0), 0, ',', ' ') ?> XOF</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-title">Total Facturé Aujourd'hui</div>
            <div class="kpi-value"><?= number_format((float)($summary['total_facture'] ?? 0), 0, ',', ' ') ?> XOF</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-title">Reste à Recouvrir (Créance)</div>
            <div class="kpi-value" style="color:#dc2626;"><?= number_format((float)($summary['total_restant'] ?? 0), 0, ',', ' ') ?> XOF</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-title">Agences Clôturées / Total</div>
            <div class="kpi-value"><?= (int)($summary['agences_cloturees'] ?? 0) ?> / <?= (int)($summary['total_agences'] ?? 0) ?></div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Agence</th>
                <th class="text-center">Colis / Factures</th>
                <th class="text-right">Total Facturé (XOF)</th>
                <th class="text-right">Encaissements Live (XOF)</th>
                <th class="text-center">Écart Déclaré</th>
                <th class="text-center">Heure Clôture</th>
                <th class="text-center">Statut</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($agenceRows as $row): ?>
                <tr>
                    <td><strong><?= View::e($row['agence_name']) ?></strong></td>
                    <td class="text-center"><?= (int)$row['nb_colis'] ?> colis / <?= (int)$row['nb_factures'] ?> fac.</td>
                    <td class="text-right"><?= number_format((float)$row['total_facture'], 0, ',', ' ') ?> XOF</td>
                    <td class="text-right" style="font-weight:bold; color:#16a34a;"><?= number_format((float)$row['total_encaisse'], 0, ',', ' ') ?> XOF</td>
                    <td class="text-center">
                        <?php if (abs((float)$row['ecart']) < 0.01): ?>
                            <span style="color:#16a34a; font-weight:bold;">0 FCFA</span>
                        <?php else: ?>
                            <span style="color:#dc2626; font-weight:bold;"><?= ((float)$row['ecart'] > 0 ? '+' : '') . number_format((float)$row['ecart'], 0, ',', ' ') ?> XOF</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center"><?= View::e($row['heure_soumission'] ?? 'En attente') ?></td>
                    <td class="text-center">
                        <span style="font-weight:bold; padding:2px 6px; border-radius:4px; font-size:9px; background:<?= $row['statut'] === 'consolide' ? '#dcfce7; color:#15803d' : ($row['statut'] === 'soumis' ? '#dbeafe; color:#1e40af' : '#fef3c7; color:#92400e') ?>;">
                            <?= strtoupper($row['statut']) ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td>TOTAL RÉSEAU LBP TRANSIT</td>
                <td class="text-center"><?= (int)($summary['total_colis'] ?? 0) ?> colis / <?= (int)($summary['total_factures_cnt'] ?? 0) ?> fac.</td>
                <td class="text-right"><?= number_format((float)($summary['total_facture'] ?? 0), 0, ',', ' ') ?> XOF</td>
                <td class="text-right" style="color:#16a34a; font-size:12px;"><?= number_format((float)($summary['total_encaisse'] ?? 0), 0, ',', ' ') ?> XOF</td>
                <td class="text-center"><?= number_format((float)($summary['total_ecart'] ?? 0), 0, ',', ' ') ?> XOF</td>
                <td class="text-center">—</td>
                <td class="text-center">SYNTHÈSE</td>
            </tr>
        </tbody>
    </table>

    <div class="signatures">
        <div class="sig-box">
            <div class="sig-title">La Caissière Principale</div>
            <div>Visa & Horodateur</div>
        </div>
        <div class="sig-box">
            <div class="sig-title">Le Directeur Financier / Comptable</div>
            <div>Visa & Horodateur</div>
        </div>
        <div class="sig-box">
            <div class="sig-title">Le Directeur Général</div>
            <div>Approbation & Consolidation</div>
        </div>
    </div>

</body>
</html>
