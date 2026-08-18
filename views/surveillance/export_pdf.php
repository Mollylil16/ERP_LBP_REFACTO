<?php

declare(strict_types=1);

use App\Helpers\View;

/** @var array $stats */
/** @var array $alerts */
/** @var array $employees */
/** @var array $filters */

$dateRapport = date('d/m/Y H:i');
$debut = date('d/m/Y', strtotime($filters['start_date']));
$fin = date('d/m/Y', strtotime($filters['end_date']));

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport_Integrite_LBP_<?= date('Y_m') ?></title>
    <style>
        @page { size: A4 portrait; margin: 20mm 15mm; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #1e293b; background: #fff; line-height: 1.5; font-size: 12px; }
        .header { display: flex; justify-content: space-between; border-bottom: 3px solid #0f172a; padding-bottom: 12px; margin-bottom: 20px; }
        .logo-title { font-size: 22px; font-weight: 900; color: #0f172a; letter-spacing: 0.5px; }
        .logo-sub { font-size: 10px; text-transform: uppercase; color: #64748b; font-weight: 700; }
        .report-badge { background: #b91c1c; color: #fff; padding: 6px 14px; font-size: 12px; font-weight: 800; border-radius: 4px; text-transform: uppercase; display: inline-block; }
        
        .section-title { font-size: 14px; font-weight: 800; border-bottom: 1px solid #cbd5e1; padding-bottom: 6px; margin-top: 25px; margin-bottom: 12px; color: #0f172a; text-transform: uppercase; }
        
        .kpi-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px; }
        .kpi-card { background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 6px; text-align: center; }
        .kpi-val { font-size: 18px; font-weight: 800; color: #0f172a; }
        .kpi-label { font-size: 9px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-top: 4px; }
        
        .table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .table th { background: #0f172a; color: #fff; padding: 8px 10px; text-align: left; font-size: 10px; text-transform: uppercase; }
        .table td { border-bottom: 1px solid #e2e8f0; padding: 8px 10px; font-size: 11px; }
        
        .btn-print { position: fixed; top: 15px; right: 15px; background: #0f172a; color: #fff; border: none; padding: 10px 18px; border-radius: 6px; font-weight: 700; cursor: pointer; z-index: 9999; }
        @media print { .btn-print { display: none; } }
        
        .confidential { color: #b91c1c; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; font-size: 11px; margin-bottom: 15px; }
        .footer-note { margin-top: 45px; text-align: center; font-size: 10px; color: #64748b; border-top: 1px dashed #cbd5e1; padding-top: 15px; }
    </style>
</head>
<body>

<button class="btn-print" onclick="window.print()">🖨️ Imprimer / Sauvegarder en PDF</button>

<div class="header">
    <div>
        <div class="logo-title">LA BELLE PORTE TRANSIT</div>
        <div class="logo-sub">Sécurité interne & Intégrité des données • ERP Centrale</div>
    </div>
    <div style="text-align: right;">
        <div class="report-badge">Rapport Mensuel d'Intégrité</div>
        <div style="font-size: 11px; margin-top: 4px; font-weight: 700; color: #64748b;">Période : <?= View::e($debut) ?> au <?= View::e($fin) ?></div>
    </div>
</div>

<div class="confidential">⚠️ CONFIDENTIEL — DOCUMENT STRICTEMENT RÉSERVÉ À LA DIRECTION GÉNÉRALE</div>

<div class="kpi-row">
    <div class="kpi-card">
        <div class="kpi-val" style="color: #b91c1c;"><?= count($alerts) ?></div>
        <div class="kpi-label">Alertes détectées</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-val"><?= $stats['tres_grave'] ?></div>
        <div class="kpi-label">Très Graves</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-val"><?= $stats['grave'] ?></div>
        <div class="kpi-label">Graves</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-val"><?= $stats['moyen'] ?></div>
        <div class="kpi-label">Moyennes</div>
    </div>
</div>

<div class="section-title">Synthèse des Alertes de Sécurité</div>
<table class="table">
    <thead>
        <tr>
            <th>Gravité</th>
            <th>Collaborateur</th>
            <th>Règle violée</th>
            <th>Statut actuel</th>
            <th>Date détection</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($alerts)): ?>
            <tr>
                <td colspan="5" style="text-align: center; color: #64748b;">Aucune alerte suspecte détectée sur cette période.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($alerts as $a): ?>
                <tr>
                    <td><strong><?= strtoupper(View::e($a['gravite'])) ?></strong></td>
                    <td><?= View::e($a['user_name'] ?? 'Inconnu') ?></td>
                    <td><?= View::e($a['regle_titre'] ?? $a['regle_code']) ?></td>
                    <td><?= strtoupper(View::e($a['statut'])) ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($a['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<div class="section-title">Index de Fiabilité & Classement des Employés</div>
<table class="table">
    <thead>
        <tr>
            <th>Rang</th>
            <th>Collaborateur</th>
            <th>Score d'Intégrité</th>
            <th>Alertes Très Graves</th>
            <th>Alertes Graves</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($employees as $idx => $emp): ?>
            <tr>
                <td><strong>#<?= $idx + 1 ?></strong></td>
                <td><strong><?= View::e($emp['full_name']) ?></strong></td>
                <td style="font-weight: 700; color: <?= $emp['score_global'] >= 80 ? '#16a34a' : ($emp['score_global'] >= 60 ? '#d97706' : '#dc2626') ?>;">
                    <?= number_format((float) $emp['score_global'], 1) ?> / 100 PTS
                </td>
                <td style="color: #b91c1c; font-weight: 700;"><?= $emp['nb_tres_grave'] ?></td>
                <td style="color: #d97706; font-weight: 700;"><?= $emp['nb_grave'] ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="footer-note">
    Rapport généré automatiquement par l'ERP La Belle Porte le <?= View::e($dateRapport) ?>.<br>
    Toutes les transactions sensibles et consultations de ce rapport sont consignées dans l'audit trail sécurisé de l'ERP.
</div>

</body>
</html>
