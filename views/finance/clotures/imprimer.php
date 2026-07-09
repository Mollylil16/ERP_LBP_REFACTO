<?php

use App\Helpers\View;

/** @var \App\Support\ViewBag $viewData */
$viewData ??= \App\Support\ViewBag::from(get_defined_vars());
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>État de Caisse - Agence <?= View::e($agence['name'] ?? '—') ?> - <?= View::e(date('d/m/Y', strtotime($report->dateJour))) ?></title>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #1e293b;
            background: #ffffff;
            margin: 0;
            padding: 20px;
            font-size: 13px;
            line-height: 1.4;
        }
        .report-wrapper {
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .header {
            text-align: center;
            border-bottom: 2px dashed #cbd5e1;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .logo {
            font-size: 1.6rem;
            font-weight: 850;
            color: #1d2b57;
            text-transform: uppercase;
            letter-spacing: -0.02em;
            margin: 0 0 5px 0;
        }
        .logo span {
            color: #fabd02;
        }
        .report-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #0f172a;
            margin: 10px 0 5px 0;
            text-transform: uppercase;
        }
        .report-subtitle {
            font-size: 0.95rem;
            color: #475569;
            font-weight: 600;
        }
        .report-meta {
            font-family: monospace;
            font-size: 0.85rem;
            color: #64748b;
        }
        .section {
            margin-bottom: 25px;
        }
        .section-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: #334155;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 4px;
            margin-bottom: 12px;
        }
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        .kpi-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 15px;
            background: #f8fafc;
            text-align: center;
        }
        .kpi-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
        }
        .kpi-value {
            font-size: 1.15rem;
            font-weight: 700;
            color: #0f172a;
            margin-top: 5px;
        }
        .table-data {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }
        .table-data th {
            text-align: left;
            font-weight: 700;
            color: #475569;
            background-color: #f1f5f9;
            border: 1px solid #e2e8f0;
            padding: 8px 10px;
            font-size: 0.75rem;
            text-transform: uppercase;
        }
        .table-data td {
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-success { background-color: #dcfce7; color: #15803d; }
        .badge-warning { background-color: #fef9c3; color: #a16207; }
        .badge-primary { background-color: #dbeafe; color: #1d4ed8; }
        .badge-secondary { background-color: #f1f5f9; color: #475569; }
        
        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
            margin-top: 50px;
            padding-top: 20px;
        }
        .signature-box {
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 15px;
            min-height: 100px;
            text-align: center;
        }
        .signature-title {
            font-weight: 700;
            font-size: 0.8rem;
            text-transform: uppercase;
            color: #475569;
            margin-bottom: 40px;
        }
        .no-print-zone {
            text-align: center;
            margin-bottom: 20px;
        }
        .btn-print {
            background-color: #1d2b57;
            color: #ffffff;
            border: none;
            padding: 10px 20px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.15s ease;
        }
        .btn-print:hover {
            background-color: #2563eb;
        }
        @media print {
            .no-print-zone {
                display: none;
            }
            body {
                padding: 0;
            }
            .report-wrapper {
                border: none;
                box-shadow: none;
                padding: 0;
            }
        }
    </style>
</head>
<body>

    <div class="no-print-zone">
        <button class="btn-print" onclick="window.print()">Imprimer le Point de Caisse</button>
        <a href="<?= View::url('finance/clotures') ?>" style="margin-left:15px; color:#1d2b57; text-decoration:none; font-weight:600;">← Retour aux points de caisse</a>
    </div>

    <div class="report-wrapper">
        <div class="header">
            <h1 class="logo">LBP<span> TRANSIT</span></h1>
            <p class="subtitle">La Belle Porte Transit</p>
            <div class="report-title">État Journalier de Caisse</div>
            <div class="report-subtitle">Agence: <?= View::e($agence['name'] ?? '—') ?></div>
            <div class="report-meta">
                Date : <?= View::e(date('d/m/Y', strtotime($report->dateJour))) ?> | 
                Statut : <span class="badge badge-<?= $report->statut === 'consolide' ? 'success' : ($report->statut === 'soumis' ? 'primary' : 'warning') ?>"><?= View::e($report->statut) ?></span>
            </div>
        </div>

        <!-- Section 1 : Indicateurs Synthétiques -->
        <div class="section">
            <div class="section-title">Indicateurs d'Activité</div>
            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-label">Activité & Enregistrements</div>
                    <div class="kpi-value"><?= View::e($report->nbColisEnregistres) ?> Colis / <?= View::e($report->nbFacturesEmises) ?> Fac.</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Solde Caisse Encaissé (XOF)</div>
                    <div class="kpi-value" style="color:#16a34a;"><?= number_format($report->totalEncaisseXof, 2, ',', ' ') ?> XOF</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Solde Caisse Encaissé (EUR)</div>
                    <div class="kpi-value" style="color:#16a34a;"><?= number_format($report->totalEncaisseEur, 2, ',', ' ') ?> EUR</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Total Facturé (XOF)</div>
                    <div class="kpi-value"><?= number_format($report->totalFactureXof, 2, ',', ' ') ?> XOF</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Total Facturé (EUR)</div>
                    <div class="kpi-value"><?= number_format($report->totalFactureEur, 2, ',', ' ') ?> EUR</div>
                </div>
                <div class="kpi-card" style="border-color:#fca5a5;">
                    <div class="kpi-label" style="color:#ef4444;">Crédits accordés / Reste dû</div>
                    <div class="kpi-value" style="color:#ef4444;"><?= number_format($report->totalRestantDuXof, 2, ',', ' ') ?> XOF</div>
                </div>
            </div>
        </div>

        <!-- Section 2 : Règlements Encaissés -->
        <div class="section">
            <div class="section-title">Règlements & Encaissements reçus</div>
            <table class="table-data">
                <thead>
                    <tr>
                        <th>N° Facture</th>
                        <th>Client</th>
                        <th>Mode</th>
                        <th>Type</th>
                        <th style="text-align:right;">Montant Encaissé</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($paiements === []): ?>
                        <tr>
                            <td colspan="5" style="text-align:center; color:#64748b;">Aucun encaissement sur cette journée.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($paiements as $p): ?>
                            <tr>
                                <td><code><?= View::e($p['numero_facture']) ?></code></td>
                                <td><?= View::e($p['client_name'] ?? '—') ?></td>
                                <td><span class="badge badge-secondary"><?= View::e(strtoupper($p['mode'])) ?></span></td>
                                <td><?= View::e(ucfirst($p['type'])) ?></td>
                                <td style="text-align:right; font-weight:600; color:#16a34a;">
                                    <?= number_format($p['montant'], 2, ',', ' ') ?> <?= View::e($p['devise']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Section 3 : Factures Émises -->
        <div class="section">
            <div class="section-title">Facturations Émises</div>
            <table class="table-data">
                <thead>
                    <tr>
                        <th>N° Facture</th>
                        <th>Client</th>
                        <th style="text-align:right;">Montant Total</th>
                        <th style="text-align:right;">Montant Encaissé</th>
                        <th style="text-align:right;">Crédit Restant</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($factures === []): ?>
                        <tr>
                            <td colspan="6" style="text-align:center; color:#64748b;">Aucune facture émise sur cette journée.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($factures as $f): ?>
                            <tr>
                                <td><code><?= View::e($f['numero_facture']) ?></code></td>
                                <td><?= View::e($f['client_name'] ?? '—') ?></td>
                                <td style="text-align:right; font-weight:600;"><?= number_format($f['montant_total'], 2, ',', ' ') ?> <?= View::e($f['devise']) ?></td>
                                <td style="text-align:right; color:#16a34a;"><?= number_format($f['montant_encaisse'], 2, ',', ' ') ?> <?= View::e($f['devise']) ?></td>
                                <td style="text-align:right; color:#ef4444;"><?= number_format($f['montant_restant'], 2, ',', ' ') ?> <?= View::e($f['devise']) ?></td>
                                <td>
                                    <?php
                                    $tone = match($f['statut']) {
                                        'payee' => 'success',
                                        'partiellement_payee' => 'warning',
                                        'emise' => 'primary',
                                        default => 'secondary'
                                    };
                                    ?>
                                    <span class="badge badge-<?= $tone ?>"><?= View::e(str_replace('_', ' ', $f['statut'])) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Section 4 : Zone de validation et signatures -->
        <div class="signatures">
            <div class="signature-box">
                <div class="signature-title">Signature Caissier(e) d'Agence</div>
                <p style="font-size:0.75rem; color:#64748b; margin-top:30px;">Nom: ________________________</p>
            </div>
            <div class="signature-box">
                <div class="signature-title">Signature Chef d'Agence / Direction</div>
                <p style="font-size:0.75rem; color:#64748b; margin-top:30px;">Nom: ________________________</p>
            </div>
        </div>

        <div class="footer" style="margin-top:40px; border-top:1px solid #cbd5e1; padding-top:15px; text-align:center; font-size:0.75rem; color:#64748b;">
            <p>Ce point de caisse est édité par le système et verrouillé après consolidation.</p>
            <p>La Belle Porte Transit • Solution ERP de Gestion Multi-Agences</p>
        </div>
    </div>

</body>
</html>
