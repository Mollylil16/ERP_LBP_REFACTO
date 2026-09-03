<?php

declare(strict_types=1);

use App\Helpers\View;

/**
 * @var array<int, array<string, mixed>> $results
 * @var array<string, mixed> $kpis
 * @var string $periodText
 * @var string $agenceText
 * @var string $categorieText
 * @var string $statutText
 */

$dateGen = date('d/m/Y H:i');
$totalCount = (int) ($kpis['totalCount'] ?? 0);
$totalMontant = (float) ($kpis['totalMontantXof'] ?? 0);
$totalEncaisse = (float) ($kpis['totalEncaisse'] ?? 0);
$totalImpaye = (float) ($kpis['totalImpaye'] ?? 0);
$totalPoids = (float) ($kpis['totalPoids'] ?? 0);
$totalColis = (int) ($kpis['totalColis'] ?? 0);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport_Facturation_Impayes_<?= date('Ymd_His') ?></title>
    <style>
        @page { size: A4 landscape; margin: 10mm 12mm; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #0f172a; background: #fff; line-height: 1.4; font-size: 11px; margin: 0; padding: 0; }
        
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #0f172a; padding-bottom: 10px; margin-bottom: 12px; }
        .logo-title { font-size: 20px; font-weight: 900; color: #0f172a; letter-spacing: -0.5px; }
        .logo-sub { font-size: 9px; text-transform: uppercase; color: #64748b; font-weight: 700; margin-top: 2px; }
        
        .doc-badge { background: #0f172a; color: #fff; padding: 5px 12px; font-size: 12px; font-weight: 800; border-radius: 4px; text-transform: uppercase; text-align: right; }
        .doc-sub { font-size: 10px; color: #64748b; font-weight: 700; margin-top: 3px; text-align: right; }

        .filters-strip { display: flex; gap: 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 12px; margin-bottom: 14px; flex-wrap: wrap; }
        .filter-item { display: flex; align-items: center; gap: 6px; font-size: 10.5px; font-weight: 600; color: #334155; }
        .filter-label { color: #64748b; text-transform: uppercase; font-size: 9px; font-weight: 800; }
        .filter-val { color: #0f172a; font-weight: 700; }

        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 14px; }
        .kpi-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 12px; }
        .kpi-card--danger { background: #fef2f2; border-color: #fca5a5; border-left: 4px solid #dc2626; }
        .kpi-card--success { background: #f0fdf4; border-color: #bbf7d0; border-left: 4px solid #16a34a; }
        .kpi-card--primary { background: #f8fafc; border-color: #cbd5e1; border-left: 4px solid #0284c7; }
        
        .kpi-label { font-size: 9px; font-weight: 800; text-transform: uppercase; color: #64748b; }
        .kpi-val { font-size: 16px; font-weight: 900; margin-top: 2px; color: #0f172a; }

        table { width: 100%; border-collapse: collapse; font-size: 10px; margin-top: 5px; }
        th { background: #0f172a; color: #fff; padding: 7px 8px; text-align: left; font-size: 9.5px; text-transform: uppercase; letter-spacing: 0.03em; }
        td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        tr:nth-child(even) { background: #f8fafc; }
        
        .badge-cat { display: inline-flex; align-items: center; gap: 4px; padding: 2px 6px; border-radius: 4px; font-weight: 700; font-size: 8.5px; text-transform: uppercase; }
        .badge-cat--cargo { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
        .badge-cat--rapide { background: #fdf4ff; color: #a21caf; border: 1px solid #f5d0fe; }
        .badge-cat--dhl { background: #fefce8; color: #a16207; border: 1px solid #fef08a; }
        .badge-cat--autres { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

        .badge-status { padding: 2px 6px; border-radius: 9999px; font-size: 8.5px; font-weight: 700; display: inline-block; }
        .badge-status--impaye { background: #fee2e2; color: #dc2626; }
        .badge-status--partiel { background: #fef3c7; color: #d97706; }
        .badge-status--paye { background: #dcfce7; color: #15803d; }

        .total-row { background: #f1f5f9 !important; font-weight: 900; border-top: 2px solid #0f172a; }
        .total-row td { padding: 8px; font-size: 10.5px; }

        .btn-print { position: fixed; top: 12px; right: 12px; background: #0284c7; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 12px; box-shadow: 0 4px 10px rgba(2, 132, 199, 0.3); }
        .btn-print:hover { background: #0369a1; }

        @media print {
            .btn-print { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>

<button class="btn-print" onclick="window.print()">🖨️ Imprimer / Sauvegarder PDF</button>

<!-- Header -->
<div class="header">
    <div>
        <div class="logo-title">LA BELLE PORTE TRANSIT (LBP)</div>
        <div class="logo-sub">Transport International & Logistique • Fret Aérien, Maritime & Express</div>
    </div>
    <div>
        <div class="doc-badge">ÉTAT RÉCAPITULATIF FACTURATION & IMPAYÉS</div>
        <div class="doc-sub">Édité le <?= $dateGen ?></div>
    </div>
</div>

<!-- Filters Strip -->
<div class="filters-strip">
    <div class="filter-item">
        <!-- Calendar SVG Icon -->
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2.2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
        <span class="filter-label">Période :</span>
        <span class="filter-val"><?= View::e($periodText) ?></span>
    </div>
    <div class="filter-item">
        <!-- Agency SVG Icon -->
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2.2"><path d="M3 21h18"></path><path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"></path><path d="M9 9h1"></path><path d="M9 13h1"></path><path d="M9 17h1"></path><path d="M14 9h1"></path><path d="M14 13h1"></path><path d="M14 17h1"></path></svg>
        <span class="filter-label">Agence :</span>
        <span class="filter-val"><?= View::e($agenceText) ?></span>
    </div>
    <div class="filter-item">
        <!-- Category SVG Icon -->
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2.2"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
        <span class="filter-label">Catégorie / Trajet :</span>
        <span class="filter-val"><?= View::e($categorieText) ?></span>
    </div>
    <div class="filter-item">
        <!-- Status SVG Icon -->
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2.2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
        <span class="filter-label">Statut Paiement :</span>
        <span class="filter-val"><?= View::e($statutText) ?></span>
    </div>
</div>

<!-- KPIs -->
<div class="kpi-grid">
    <div class="kpi-card kpi-card--primary">
        <div class="kpi-label">Nombre de Factures / Colis</div>
        <div class="kpi-val"><?= number_format($totalCount, 0, ',', ' ') ?> factures <small style="font-size:11px; font-weight:600; color:#64748b;">(<?= $totalColis ?> colis • <?= number_format($totalPoids, 1, ',', ' ') ?> kg)</small></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Chiffre d'Affaires Facturé</div>
        <div class="kpi-val"><?= number_format($totalMontant, 0, ',', ' ') ?> <small style="font-size:10px; color:#64748b;">FCFA</small></div>
    </div>
    <div class="kpi-card kpi-card--success">
        <div class="kpi-label">Montant Déjà Encaissé</div>
        <div class="kpi-val" style="color:#15803d;"><?= number_format($totalEncaisse, 0, ',', ' ') ?> <small style="font-size:10px;">FCFA</small></div>
    </div>
    <div class="kpi-card kpi-card--danger">
        <div class="kpi-label">TOTAL IMPAYÉS / CRÉANCES À RECOUVRER</div>
        <div class="kpi-val" style="color:#dc2626;"><?= number_format($totalImpaye, 0, ',', ' ') ?> <small style="font-size:10px;">FCFA</small></div>
    </div>
</div>

<!-- Table -->
<table>
    <thead>
        <tr>
            <th style="width: 30px;">N°</th>
            <th>N° Facture</th>
            <th>N° Tracking / Colis</th>
            <th>Catégorie / Trajet</th>
            <th>Client & Contact</th>
            <th>Agence</th>
            <th>Date Émission</th>
            <th style="text-align: right;">Poids / Colis</th>
            <th style="text-align: right;">Montant Total</th>
            <th style="text-align: right;">Encaissé</th>
            <th style="text-align: right; color:#fee2e2;">Reste Impayé</th>
            <th style="text-align: center;">Statut</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($results)): ?>
            <tr>
                <td colspan="12" style="text-align: center; padding: 25px; color: #94a3b8;">
                    Aucune facture ne correspond aux critères sélectionnés.
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($results as $idx => $r): ?>
                <?php
                $code = strtoupper((string) ($r['trajet_code'] ?? $r['col_trajet'] ?? 'AUTRE'));
                $isCargo = in_array($code, ['LB-CI', 'LB-FR', 'S-FR', 'S-CI', 'LB-CA', 'F-SN']) || str_starts_with($code, 'GP-');
                $isRapide = in_array($code, ['CA-CI', 'CA-FR']) || str_starts_with($code, 'CR-');
                $isDhl = str_contains($code, 'DHL') || str_starts_with((string)($r['numero_tracking'] ?? ''), 'DHL');

                $catClass = 'badge-cat--autres';
                $catIcon = '<svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle></svg>';
                if ($isCargo) {
                    $catClass = 'badge-cat--cargo';
                    $catIcon = '<svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17.8 19.2L16 11l3.5-3.5C21 6 21.5 4 21 3.5c-.5-.5-2.5 0-4 1.5L13.5 8.5 5.3 6.7c-.5-.1-1.1.1-1.4.6l-.6.9c-.3.4-.2 1 .2 1.3L8 13l-3 3-2-1c-.4-.2-.9-.1-1.2.2l-.6.6c-.3.3-.3.8 0 1.1l2.5 2.5c.3.3.8.3 1.1 0l.6-.6c.3-.3.4-.8.2-1.2l-1-2 3-3 3.5 4.5c.3.4.9.5 1.3.2l.9-.6c.5-.3.7-.9.6-1.4z"></path></svg>';
                } elseif ($isRapide) {
                    $catClass = 'badge-cat--rapide';
                    $catIcon = '<svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>';
                } elseif ($isDhl) {
                    $catClass = 'badge-cat--dhl';
                    $catIcon = '<svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>';
                }

                $statut = (string) ($r['facture_statut'] ?? 'emise');
                $statutClass = 'badge-status--impaye';
                $statutLabel = 'Impayé';
                if ($statut === 'payee' || (float)($r['montant_restant'] ?? 0) <= 0) {
                    $statutClass = 'badge-status--paye';
                    $statutLabel = 'Payé';
                } elseif ($statut === 'partiellement_payee' || (float)($r['montant_encaisse'] ?? 0) > 0) {
                    $statutClass = 'badge-status--partiel';
                    $statutLabel = 'Partiel';
                }

                $restant = (float) ($r['montant_restant'] ?? 0);
                $dateEmis = !empty($r['date_emission']) ? date('d/m/Y', strtotime((string)$r['date_emission'])) : '—';
                ?>
                <tr>
                    <td style="color: #94a3b8; font-weight: 600;"><?= $idx + 1 ?></td>
                    <td><strong><?= View::e($r['numero_facture']) ?></strong></td>
                    <td style="font-family: monospace; font-weight: 700; color: #0284c7;"><?= View::e($r['numero_tracking'] ?? '—') ?></td>
                    <td>
                        <span class="badge-cat <?= $catClass ?>">
                            <?= $catIcon ?> <?= View::e($code) ?>
                        </span>
                    </td>
                    <td>
                        <strong><?= View::e($r['client_name'] ?? '—') ?></strong>
                        <?php if (!empty($r['client_phone'])): ?>
                            <br><small style="color:#64748b; font-weight:600;"><?= View::e($r['client_phone']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td style="color: #475569;"><?= View::e($r['agence_name'] ?? '—') ?></td>
                    <td style="color: #475569; white-space: nowrap;"><?= View::e($dateEmis) ?></td>
                    <td style="text-align: right; color: #475569;">
                        <?= number_format((float) ($r['poids_total'] ?? 0), 1, ',', ' ') ?> kg
                        <br><small style="color:#94a3b8;"><?= (int) ($r['nombre_colis'] ?? 1) ?> colis</small>
                    </td>
                    <td style="text-align: right; font-weight: 700;">
                        <?= number_format((float) $r['montant_total'], 0, ',', ' ') ?>
                    </td>
                    <td style="text-align: right; color: #15803d; font-weight: 600;">
                        <?= number_format((float) $r['montant_encaisse'], 0, ',', ' ') ?>
                    </td>
                    <td style="text-align: right; font-weight: 900; color: <?= $restant > 0 ? '#dc2626' : '#15803d' ?>;">
                        <?= number_format($restant, 0, ',', ' ') ?>
                    </td>
                    <td style="text-align: center;">
                        <span class="badge-status <?= $statutClass ?>"><?= $statutLabel ?></span>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
    <tfoot>
        <tr class="total-row">
            <td colspan="7" style="text-align: right; text-transform: uppercase;">TOTAL GÉNÉRAL CONSOLIDÉ :</td>
            <td style="text-align: right;"><?= number_format($totalPoids, 1, ',', ' ') ?> kg</td>
            <td style="text-align: right;"><?= number_format($totalMontant, 0, ',', ' ') ?> FCFA</td>
            <td style="text-align: right; color: #15803d;"><?= number_format($totalEncaisse, 0, ',', ' ') ?> FCFA</td>
            <td style="text-align: right; color: #dc2626;"><?= number_format($totalImpaye, 0, ',', ' ') ?> FCFA</td>
            <td></td>
        </tr>
    </tfoot>
</table>

<!-- Signatures -->
<div style="display: flex; justify-content: space-between; margin-top: 30px; page-break-inside: avoid;">
    <div style="width: 30%; border-top: 1px dashed #94a3b8; padding-top: 5px; text-align: center; font-size: 9.5px; color: #475569;">
        <strong>Le Responsable Facturation</strong>
    </div>
    <div style="width: 30%; border-top: 1px dashed #94a3b8; padding-top: 5px; text-align: center; font-size: 9.5px; color: #475569;">
        <strong>Le Service Recouvrement</strong>
    </div>
    <div style="width: 30%; border-top: 1px dashed #94a3b8; padding-top: 5px; text-align: center; font-size: 9.5px; color: #475569;">
        <strong>La Direction Générale</strong>
    </div>
</div>

</body>
</html>
