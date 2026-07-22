<?php

declare(strict_types=1);

use App\Helpers\View;

/** @var array<string, mixed> $colis */

$montantTotal = (float) ($colis['montant_total'] ?? $colis['valeur_declaree'] ?? 0);
$montantEur = (float) ($colis['montant_total_eur'] ?? 0);
$devise = $colis['devise'] ?? 'XOF';
$traficLabel = $colis['trafic'] ?? 'Groupage Aérien';

$sousTotal = 0.0;
if (!empty($colis['marchandises'])) {
    foreach ($colis['marchandises'] as $m) {
        $sousTotal += (float) ($m['total_ligne'] ?? 0);
    }
}

// Fetch the associated invoice to get the payment link
$db = \App\Models\Database::getConnection();
$factureRepo = new \App\Repositories\Finance\FactureRepository($db);
$facture = $factureRepo->findByColisId((int) $colis['id']);

$trackingUrl = View::url('site/tracking?ref=' . urlencode($colis['numero_tracking']));
$trackingQrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=' . urlencode($trackingUrl);

$paymentQrUrl = null;
if ($facture) {
    $paymentUrl = View::url('api/paiements/pay/' . $facture->id);
    $paymentQrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=' . urlencode($paymentUrl);
} else {
    // Fallback to tracking link if no invoice exists
    $paymentQrUrl = $trackingQrUrl;
}

$operatorName = \App\Helpers\Auth::user() ? \App\Helpers\Auth::user()->fullName : 'Service Transit';

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Facture_<?= View::e($colis['numero_tracking']) ?></title>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #1e293b;
            margin: 0;
            padding: 20px;
            font-size: 11px;
            line-height: 1.35;
            background-color: #f1f5f9;
        }
        
        .facture-container {
            max-width: 820px;
            margin: 0 auto;
            border: 1px solid #e2e8f0;
            padding: 25px;
            border-radius: 8px;
            background-color: #ffffff;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        /* Header Overlay Styling */
        .facture-header {
            position: relative;
            width: 100%;
            margin-bottom: 8px;
        }
        .header-bg-img {
            width: 100%;
            height: auto;
            display: block;
        }
        .header-overlay-center {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            background-color: #1e3a5f;
            border-radius: 6px;
            padding: 5px 25px;
            text-align: center;
            border: 1px solid #d97706;
            box-shadow: 0 2px 4px rgba(0,0,0,0.15);
        }
        .imprime-badge-specific {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .imprime-title {
            color: #f59e0b; /* Gold */
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 2px;
        }
        .facture-colisage-title {
            color: #ffffff;
            font-size: 15px;
            font-weight: 800;
            white-space: nowrap;
        }
        .header-overlay-right {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            text-align: center;
        }
        .qr-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            background: #ffffff;
            padding: 3px;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .qr-code-img {
            width: 52px;
            height: 52px;
            display: block;
        }
        .qr-label {
            font-size: 7px;
            font-weight: 700;
            color: #1e3a5f;
            margin-top: 2px;
        }

        /* Yellow Notification Bar */
        .info-bar {
            background-color: #fef3c7;
            border: 1px solid #fde68a;
            color: #b45309;
            padding: 6px 12px;
            text-align: center;
            font-weight: 700;
            font-size: 9.5px;
            margin-bottom: 12px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }

        /* Agency and Customer Service Row */
        .agency-service-table {
            width: 100%;
            margin-bottom: 12px;
            font-size: 11px;
            color: #0f172a;
        }
        .agency-service-table td {
            padding: 2px 0;
        }

        /* Blue Details Banner */
        .banner-blue {
            background-color: #1e3a5f;
            color: #ffffff;
            text-align: center;
            padding: 10px;
            font-size: 18px;
            font-weight: 800;
            border-radius: 4px;
            margin-bottom: 6px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .colis-count-bar {
            text-align: center;
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 12px;
            color: #475569;
        }

        /* Main Info Grid */
        .grid-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            overflow: hidden;
            font-size: 11px;
        }
        .grid-table td {
            padding: 7px 12px;
            vertical-align: middle;
            border-bottom: 1px solid #e2e8f0;
        }
        .grid-table tr:last-child td {
            border-bottom: none;
        }
        .grid-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .label {
            font-weight: 700;
            color: #1e3a5f;
            display: inline-block;
            width: 110px;
        }
        .value {
            color: #334155;
        }

        /* Merchandise Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 10.5px;
        }
        .items-table th {
            background-color: #1e3a5f;
            color: #ffffff;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 9.5px;
            padding: 7px 8px;
            text-align: left;
            border: 1px solid #1e3a5f;
        }
        .items-table td {
            padding: 7px 8px;
            border: 1px solid #e2e8f0;
        }
        .items-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .items-table tfoot td {
            padding: 6px 8px;
            font-weight: 700;
        }
        .total-row {
            background-color: #f1f5f9;
            color: #0f172a;
        }

        /* Payment Notice & Signatures */
        .payment-signature-section {
            width: 100%;
            margin-top: 10px;
            margin-bottom: 15px;
        }
        .payment-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 10px;
        }
        .payment-notice {
            font-size: 10px;
            color: #475569;
            font-weight: 600;
            font-style: italic;
        }
        .payment-qr-card {
            text-align: center;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 5px;
            width: 115px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .payment-qr-img {
            width: 70px;
            height: 70px;
            display: block;
            margin: 0 auto 3px auto;
        }
        .payment-qr-label {
            font-size: 7.5px;
            font-weight: 700;
            color: #1e3a5f;
            text-transform: uppercase;
            margin-bottom: 1px;
            line-height: 1;
        }
        .payment-qr-sublabel {
            font-size: 6.5px;
            color: #64748b;
            line-height: 1;
        }
        .signatures-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        .signature-box {
            flex: 1;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 8px 10px;
            height: 60px;
            font-size: 10px;
            font-weight: 700;
            color: #1e3a5f;
            background-color: #f8fafc;
        }

        /* Address and Footer Info */
        .footer-address {
            text-align: center;
            color: #0f172a;
            font-size: 10px;
            margin-top: 10px;
            margin-bottom: 5px;
        }
        .footer-address strong {
            color: #1e3a5f;
            font-size: 11px;
        }
        
        .agencies-grid-table {
            width: 100%;
            font-size: 8.5px;
            color: #475569;
            margin-bottom: 10px;
            border-top: 1px solid #cbd5e1;
            padding-top: 5px;
        }

        .meta-footer-row {
            display: flex;
            justify-content: space-between;
            font-size: 8.5px;
            color: #64748b;
            margin-bottom: 8px;
        }

        .footer-img {
            width: 100%;
            height: auto;
            display: block;
            margin-top: 5px;
        }

        /* Print Controls */
        .btn-print-container {
            text-align: center;
            margin-bottom: 15px;
        }
        .btn-print {
            background-color: #f97316;
            color: white;
            border: none;
            padding: 8px 18px;
            font-size: 13px;
            font-weight: 700;
            border-radius: 6px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.2s;
        }
        .btn-print:hover {
            background-color: #ea580c;
        }

        @media print {
            body {
                padding: 0;
                background-color: #ffffff;
            }
            .facture-container {
                border: none;
                padding: 0;
                box-shadow: none;
                max-width: 100%;
            }
            .btn-print-container {
                display: none;
            }
            @page {
                size: A4;
                margin: 8mm;
            }
        }
    </style>
</head>
<body>

    <div class="btn-print-container">
        <button class="btn-print" onclick="window.print()">🖨️ Imprimer la Facture</button>
    </div>

    <div class="facture-container">
        
        <!-- Header Overlay -->
        <div class="facture-header">
            <img src="<?= View::asset('images/entete_lbp.png') ?>" alt="LBP Header" class="header-bg-img">
            <div class="header-overlay-center">
                <div class="imprime-badge-specific">
                    <span class="imprime-title">IMPRIMÉ SPÉCIFIQUE</span>
                    <span class="facture-colisage-title">Facture & Colisage</span>
                </div>
            </div>
            <div class="header-overlay-right">
                <div class="qr-container">
                    <img src="<?= $trackingQrUrl ?>" class="qr-code-img" alt="QR Code Tracking">
                    <div class="qr-label">Suivi colis</div>
                </div>
            </div>
        </div>

        <!-- Info Notification Bar -->
        <div class="info-bar">
            VOUS DISPOSEZ DE 3 JOURS POUR RÉCUPÉRER VOTRE COLIS À COMPTER DE LA DATE DE NOTIFICATION. PASSÉ CE DÉLAI, NOUS DÉCLINONS TOUTE RESPONSABILITÉ.
        </div>

        <!-- Agency and Customer Service -->
        <table class="agency-service-table">
            <tr>
                <td><strong>Agence:</strong> LBP Logistics — <?= View::e($colis['agence_depart_name'] ?? 'Siège Social') ?></td>
                <td style="text-align: right;"><strong>SERVICE CLIENT :</strong> 0503497979 / 0509467979</td>
            </tr>
        </table>

        <!-- Banner -->
        <div class="banner-blue">
            DÉTAILS COLIS &nbsp;&nbsp;<?= View::e($colis['numero_tracking']) ?>
        </div>

        <div class="colis-count-bar">
            Nombre total de colis : <?= View::e((string) ($colis['nombre_colis'] ?? 1)) ?>
        </div>

        <!-- Main Info Grid -->
        <table class="grid-table">
            <tr>
                <td style="width: 50%; border-right: 1px solid #e2e8f0;">
                    <span class="label">Code Colis :</span>
                    <span class="value" style="font-weight: 700; color: #0f172a;"><?= View::e($colis['numero_tracking']) ?></span>
                </td>
                <td style="width: 50%;">
                    <span class="label">Date d'envoi :</span>
                    <span class="value"><?= View::e(date('d/m/Y', strtotime($colis['created_at']))) ?></span>
                </td>
            </tr>
            <tr>
                <td style="width: 50%; border-right: 1px solid #e2e8f0;">
                    <span class="label">EXPÉDITEUR :</span>
                    <span class="value" style="font-weight: 600;"><?= View::e($colis['expediteur_name']) ?></span>
                </td>
                <td style="width: 50%;">
                    <span class="label">DESTINATION :</span>
                    <span class="value" style="font-weight: 700; color: #1e3a5f;"><?= View::e($colis['agence_arrivee_name'] ?? '—') ?></span>
                </td>
            </tr>
            <tr>
                <td style="width: 50%; border-right: 1px solid #e2e8f0;">
                    <span class="label">TÉL EXP. :</span>
                    <span class="value"><?= View::e($colis['expediteur_phone'] ?? '—') ?></span>
                </td>
                <td style="width: 50%;">
                    <span class="label">DESTINATAIRE :</span>
                    <span class="value" style="font-weight: 600;"><?= View::e($colis['destinataire_name']) ?></span>
                </td>
            </tr>
            <tr>
                <td style="width: 50%; border-right: 1px solid #e2e8f0;">
                    <span class="label">TRAFIC :</span>
                    <span class="value" style="font-weight: 600;"><?= View::e($traficLabel) ?></span>
                </td>
                <td style="width: 50%;">
                    <span class="label">TÉL DEST. :</span>
                    <span class="value"><?= View::e($colis['destinataire_phone'] ?? '—') ?></span>
                </td>
            </tr>
        </table>

        <!-- Merchandise Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 5%; text-align: center;">N°</th>
                    <th style="width: 8%; text-align: center;">Nbre Colis</th>
                    <th>Description</th>
                    <th style="width: 15%;">Emballage</th>
                    <th style="width: 10%; text-align: center;">Qté Emb.</th>
                    <th style="width: 12%; text-align: right;">Poids (kg)</th>
                    <th style="width: 12%; text-align: right;">Prix / Kg</th>
                    <th style="width: 15%; text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($colis['marchandises'])): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 15px; color: #64748b;">Aucune marchandise répertoriée.</td>
                    </tr>
                <?php else: ?>
                    <?php $idx = 0; foreach ($colis['marchandises'] as $m): $idx++; ?>
                        <tr>
                            <td style="text-align: center; font-weight: 600;"><?= $idx ?></td>
                            <td style="text-align: center;"><?= View::e((string) ($m['nbre_colis'] ?? 1)) ?></td>
                            <td><strong><?= View::e($m['description']) ?></strong></td>
                            <td><?= View::e($m['emballage'] ?? '—') ?></td>
                            <td style="text-align: center;"><?= View::e((string) ($m['qte_emballage'] ?? 1)) ?></td>
                            <td style="text-align: right;"><?= View::e(number_format((float) $m['poids_unitaire'], 2, ',', ' ')) ?></td>
                            <td style="text-align: right;"><?= View::e(number_format((float) ($m['prix_kg'] ?? 0), 0, ',', ' ')) ?> F</td>
                            <td style="text-align: right; font-weight: 600;"><?= number_format((float) ($m['total_ligne'] ?? 0), 0, ',', '.') ?> F</td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="6" style="border: none;"></td>
                    <td style="text-align: right; border: 1px solid #e2e8f0; background: #f8fafc; font-weight: 600;">SOUS-TOTAL</td>
                    <td style="text-align: right; border: 1px solid #e2e8f0; background: #f8fafc; font-weight: 600;"><?= number_format($sousTotal, 0, ',', '.') ?> FCFA</td>
                </tr>
                <?php
                $fraisEmballage = $montantTotal - $sousTotal;
                if ($fraisEmballage > 0):
                ?>
                <tr>
                    <td colspan="6" style="border: none;"></td>
                    <td style="text-align: right; border: 1px solid #e2e8f0; background: #f8fafc; font-weight: 600;">EMBALLAGE</td>
                    <td style="text-align: right; border: 1px solid #e2e8f0; background: #f8fafc; font-weight: 600;"><?= number_format($fraisEmballage, 0, ',', '.') ?> FCFA</td>
                </tr>
                <?php endif; ?>
                <tr class="total-row">
                    <td colspan="6" style="border: none;"></td>
                    <td style="text-align: right; font-size: 11px; font-weight: 800; background: #1e3a5f; color: #ffffff; border: 1px solid #1e3a5f;">MONTANT TOTAL</td>
                    <td style="text-align: right; font-weight: 900; font-size: 13px; background: #e0f2fe; color: #0369a1; border: 1px solid #0369a1;">
                        <?= number_format($montantTotal, 0, ',', '.') ?> FCFA<br>
                        <span style="font-size: 9.5px; font-weight: 600; color: #0284c7;">≈ <?= number_format($montantEur, 2, ',', '.') ?> €</span>
                    </td>
                </tr>
            </tfoot>
        </table>

        <!-- Signatures and QR Code -->
        <div class="payment-signature-section">
            <div class="payment-row">
                <div class="payment-notice">
                    Les frais de transaction sont à la charge du client.
                </div>
                <div class="payment-qr-card">
                    <img src="<?= $paymentQrUrl ?>" class="payment-qr-img" alt="QR Code Payment">
                    <div class="payment-qr-label">SCANNEZ POUR PAYER</div>
                    <div class="payment-qr-sublabel">(Wave / Orange Money)</div>
                </div>
            </div>
            
            <div class="signatures-row">
                <div class="signature-box">
                    CLIENT (date et visa)
                </div>
                <div class="signature-box">
                    SOCIÉTÉ (date et visa)
                </div>
            </div>
        </div>

        <!-- System Meta & Bottom Layout -->
        <div class="footer-address">
            <strong>ADRESSE : PARIS 17 CHEMIN DES VIGNES 93000 BOBIGNY</strong><br>
            Tél : +33 7 75 73 27 97 / +33 7 51 19 83 82 / +33 7 45 93 56 92
        </div>

        <table class="agencies-grid-table">
            <tr>
                <td style="width: 50%; text-align: left;">
                    <strong>ABIDJAN</strong><br>
                    Lun–Ven : 08h–17h | Sam–Dim : 08h–14h30
                </td>
                <td style="width: 50%; text-align: right;">
                    <strong>PARIS</strong><br>
                    Lun–Sam : 10h30–18h | Dim : 10h–14h
                </td>
            </tr>
        </table>

        <div class="meta-footer-row">
            <div>Édité par <?= View::e($operatorName) ?> le <?= date('d/m/Y', strtotime($colis['created_at'])) ?> à <?= date('H:i', strtotime($colis['created_at'])) ?></div>
            <div>Réf. FCO-<?= View::e(date('my', strtotime($colis['created_at']))) ?>-<?= View::e(substr($colis['numero_tracking'], -3)) ?></div>
        </div>

        <!-- Footer Banner Image -->
        <img src="<?= View::asset('images/footer_lbp.png') ?>" alt="LBP Footer" class="footer-img">

    </div>

    <script>
        // Auto-print on load if query contains 'autoprint'
        if (window.location.search.indexOf('autoprint') !== -1) {
            window.addEventListener('load', function() {
                window.print();
            });
        }
    </script>
</body>
</html>
