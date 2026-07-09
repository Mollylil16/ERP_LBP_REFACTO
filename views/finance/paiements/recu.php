<?php

use App\Helpers\View;

/** @var \App\Support\ViewBag $viewData */
$viewData ??= \App\Support\ViewBag::from(get_defined_vars());
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Reçu de Paiement <?= View::e($recu ? $recu->numeroRecu : 'RE-') ?></title>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #1e293b;
            background: #ffffff;
            margin: 0;
            padding: 20px;
            font-size: 14px;
            line-height: 1.5;
        }
        .receipt-card {
            max-width: 600px;
            margin: 0 auto;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .header {
            text-align: center;
            border-bottom: 2px dashed #e2e8f0;
            padding-bottom: 20px;
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
        .subtitle {
            font-size: 0.85rem;
            color: #64748b;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .receipt-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #0f172a;
            margin: 15px 0 5px 0;
        }
        .receipt-number {
            font-family: monospace;
            font-size: 1.1rem;
            color: #2563eb;
            font-weight: 600;
        }
        .section {
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 0.8rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .info-item p {
            margin: 4px 0;
        }
        .info-item strong {
            color: #334155;
        }
        .amount-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .amount-table th {
            text-align: left;
            font-weight: 600;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
            padding: 8px 0;
            font-size: 0.8rem;
            text-transform: uppercase;
        }
        .amount-table td {
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .amount-row-total {
            background-color: #f8fafc;
            font-weight: 700;
        }
        .amount-row-total td {
            border-bottom: none;
            padding: 12px 10px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            color: #64748b;
            font-size: 0.8rem;
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
            .receipt-card {
                border: none;
                box-shadow: none;
                padding: 0;
            }
        }
    </style>
</head>
<body>

    <div class="no-print-zone">
        <button class="btn-print" onclick="window.print()">Imprimer le Reçu</button>
        <a href="<?= View::url('finance/factures/' . $facture->id) ?>" style="margin-left:15px; color:#1d2b57; text-decoration:none; font-weight:600;">← Retour à la facture</a>
    </div>

    <div class="receipt-card">
        <div class="header">
            <h1 class="logo">LBP<span> TRANSIT</span></h1>
            <p class="subtitle">La Belle Porte Transit</p>
            <div class="receipt-title">REÇU DE PAIEMENT</div>
            <div class="receipt-number"><?= View::e($recu ? $recu->numeroRecu : 'RE-PROVISOIRE') ?></div>
        </div>

        <!-- Section Infos Générales -->
        <div class="section">
            <div class="grid">
                <div class="info-item">
                    <div class="section-title">Émetteur / Agence</div>
                    <p><strong>Agence :</strong> <?= View::e($agence['name'] ?? 'Siège Abidjan') ?></p>
                    <p><strong>Caissier(e) :</strong> <?= View::e($caissiere['full_name'] ?? 'Système') ?></p>
                    <p><strong>Date d'émission :</strong> <?= $recu && $recu->dateEmission ? date('d/m/Y H:i', strtotime($recu->dateEmission)) : date('d/m/Y H:i') ?></p>
                </div>
                <div class="info-item">
                    <div class="section-title">Client & Colis</div>
                    <p><strong>Client :</strong> <?= View::e($client['name'] ?? 'Client divers') ?></p>
                    <p><strong>Téléphone :</strong> <?= View::e($client['phone'] ?? '—') ?></p>
                    <p><strong>N° Tracking Colis :</strong> <?= View::e($colis['numero_tracking'] ?? '—') ?></p>
                </div>
            </div>
        </div>

        <!-- Section Renseignements Financiers -->
        <div class="section">
            <div class="section-title">Détails du règlement</div>
            <table class="amount-table">
                <thead>
                    <tr>
                        <th>Désignation</th>
                        <th>Référence</th>
                        <th style="text-align:right;">Montant</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Règlement Facture (Mode: <?= View::e(strtoupper($paiement->mode)) ?>)</td>
                        <td>Facture N° <?= View::e($facture->numeroFacture) ?></td>
                        <td style="text-align:right; font-weight:600;">
                            <?= number_format($paiement->montant, 2, ',', ' ') ?> <?= View::e($paiement->devise) ?>
                        </td>
                    </tr>
                    
                    <!-- Montant Encaissé sur ce reçu -->
                    <tr class="amount-row-total">
                        <td colspan="2">Montant Encaissé (Règlement actuel) :</td>
                        <td style="text-align:right; color:#16a34a; font-size:1.1rem;">
                            <?= number_format($paiement->montant, 2, ',', ' ') ?> <?= View::e($paiement->devise) ?>
                        </td>
                    </tr>

                    <!-- Crédit / Solde restant à payer -->
                    <tr style="font-weight: 600;">
                        <td colspan="2" style="color:#64748b;">Reste à payer (Crédit restant) :</td>
                        <td style="text-align:right; color:#dc2626;">
                            <?= number_format($facture->montantRestant, 2, ',', ' ') ?> <?= View::e($facture->devise) ?>
                        </td>
                    </tr>

                    <!-- Cumul Total Facture pour rappel -->
                    <tr style="font-size:0.85rem; color:#64748b;">
                        <td colspan="2">Rappel Montant Total Facture :</td>
                        <td style="text-align:right;">
                            <?= number_format($facture->montantTotal, 2, ',', ' ') ?> <?= View::e($facture->devise) ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="footer">
            <p>Merci pour votre confiance !</p>
            <p style="font-size:0.75rem; color:#94a3b8; margin-top:10px;">LBP Transit • Solution de Gestion Logistique & Financière</p>
        </div>
    </div>

</body>
</html>
