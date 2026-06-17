<?php
use App\Helpers\View;

$trackingNum = $colis ? $colis['tracking_number'] : $invoice['reference'];
$senderName = $colis ? $colis['sender_name'] : ($invoice['client_name'] ?? '—');
$senderPhone = $colis ? $colis['sender_phone'] : '—';
$receiverName = $colis ? $colis['receiver_name'] : '—';
$receiverPhone = $colis ? $colis['receiver_phone'] : '—';
$trafic = $colis ? ($colis['description'] ?: 'Groupage Aérien') : 'Groupage Aérien';
$dateEnvoi = $colis ? date('d/m/Y', strtotime($colis['created_at'])) : date('d/m/Y', strtotime($invoice['created_at']));
$destination = $colis ? (($colis['arrival_country'] ?? '') . ' ' . ($colis['arrival_city'] ?? '')) : 'PARIS FRANCE';
if (empty(trim($destination))) {
    $destination = 'PARIS FRANCE';
}

$totalXof = (float) $invoice['amount_ttc'];
// Exchange rate XOF to EUR: 655.957
$totalEur = $totalXof / 655.957;

// QR code URLs
$trackingQrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=" . urlencode(View::url("site/tracking?num=" . $trackingNum));
$paymentQrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode("Paiement LBP Transit Facture " . $invoice['reference'] . " Montant: " . $totalXof . " XOF");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimer Facture & Colisage - <?= View::e($invoice['reference']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            color: #1d2b57;
            background-color: #ffffff;
            margin: 0;
            padding: 20px;
            font-size: 13px;
            line-height: 1.4;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .invoice-print-container {
            max-width: 900px;
            margin: 0 auto;
            border: 1px solid #e2e8f0;
            padding: 30px;
            position: relative;
            background: #fff;
        }

        @media print {
            body {
                padding: 0;
                color: #1d2b57;
            }
            .invoice-print-container {
                border: none;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
        }

        /* Toolbar */
        .print-toolbar {
            max-width: 900px;
            margin: 0 auto 20px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8fafc;
            padding: 12px 20px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .btn-print {
            background-color: #1d2b57;
            color: #fff;
            border: none;
            padding: 8px 16px;
            font-weight: 600;
            border-radius: 4px;
            cursor: pointer;
            font-family: inherit;
        }
        .btn-back {
            color: #1d2b57;
            text-decoration: none;
            font-weight: 600;
        }

        /* Top Header */
        .invoice-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .logo-container img {
            height: 75px;
            object-fit: contain;
        }
        .title-badge-container {
            flex-grow: 1;
            text-align: center;
            max-width: 450px;
            background-color: #1d2b57;
            color: #fff;
            padding: 10px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .title-badge-container span {
            display: block;
            font-size: 10px;
            color: #eab308;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 2px;
        }
        .title-badge-container h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 800;
        }
        .qr-top-container {
            text-align: center;
            font-size: 9px;
            font-weight: 600;
            color: #64748b;
        }
        .qr-top-container img {
            width: 80px;
            height: 80px;
            display: block;
            margin-bottom: 2px;
        }

        /* Warning Banner */
        .warning-banner {
            background-color: #fef08a;
            border: 1px solid #fde047;
            color: #854d0e;
            font-size: 10px;
            font-weight: 700;
            text-align: center;
            padding: 8px;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        /* Agency and Service Row */
        .agency-info-row {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            margin-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 8px;
        }
        .agency-info-row strong {
            color: #1d2b57;
        }
        .service-client {
            color: #1d2b57;
            font-weight: 800;
        }

        /* Big Colis Details Banner */
        .colis-banner {
            background-color: #1d2b57;
            color: #fff;
            text-align: center;
            padding: 10px;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 1px;
            margin-bottom: 15px;
            text-transform: uppercase;
        }

        /* Two-Column Meta Info */
        .meta-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .meta-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .meta-item {
            display: flex;
            font-size: 12px;
        }
        .meta-label {
            font-weight: 700;
            width: 140px;
            color: #1d2b57;
            text-transform: uppercase;
        }
        .meta-value {
            color: #334155;
            font-weight: 600;
        }

        /* Total Pack Count */
        .total-packs-label {
            text-align: center;
            font-weight: 700;
            margin-bottom: 15px;
            font-size: 12px;
            color: #64748b;
        }

        /* Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th {
            background-color: #1d2b57;
            color: #fff;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 11px;
            padding: 8px 10px;
            text-align: left;
            border: 1px solid #1d2b57;
        }
        .items-table td {
            padding: 8px 10px;
            border: 1px solid #cbd5e1;
            font-size: 12px;
            font-weight: 600;
            color: #334155;
        }
        .items-table tr td:first-child,
        .items-table tr td:nth-child(2),
        .items-table tr td:nth-child(5),
        .items-table tr td:nth-child(6) {
            text-align: center;
        }
        .items-table tr td:last-child {
            text-align: right;
            font-weight: 700;
            color: #1d2b57;
        }

        /* Totals Block */
        .totals-container {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 30px;
        }
        .totals-table {
            width: 350px;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 6px 10px;
            font-size: 12px;
            font-weight: 700;
        }
        .totals-table td:first-child {
            color: #64748b;
            text-align: left;
        }
        .totals-table td:last-child {
            text-align: right;
            color: #1d2b57;
        }
        .totals-table tr.grand-total {
            background-color: #f1f5f9;
        }
        .totals-table tr.grand-total td {
            font-size: 14px;
            font-weight: 800;
            padding: 10px;
        }
        .exchange-note {
            font-size: 10px;
            color: #64748b;
            display: block;
            margin-top: 2px;
        }

        .transaction-note {
            font-size: 11px;
            color: #64748b;
            font-style: italic;
            margin-bottom: 20px;
        }

        /* Signatures and QR Code Row */
        .signatures-row {
            display: grid;
            grid-template-columns: 1.2fr 1.2fr 1fr;
            gap: 20px;
            margin-bottom: 40px;
            align-items: start;
        }
        .signature-box {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            height: 100px;
            padding: 10px;
            font-weight: 700;
            color: #1d2b57;
            font-size: 11px;
        }
        .qr-pay-container {
            text-align: center;
            font-weight: 700;
            font-size: 9px;
            color: #1d2b57;
        }
        .qr-pay-container img {
            width: 100px;
            height: 100px;
            display: block;
            margin: 0 auto 4px auto;
            border: 1px solid #cbd5e1;
            padding: 3px;
            border-radius: 4px;
        }

        /* Address and Footer Info */
        .footer-info {
            text-align: center;
            font-size: 10px;
            color: #1d2b57;
            border-top: 1px solid #cbd5e1;
            padding-top: 15px;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        .footer-address {
            font-size: 14px;
            font-weight: 800;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        .footer-phones {
            font-weight: 700;
            margin-bottom: 5px;
        }
        .footer-hours {
            display: flex;
            justify-content: center;
            gap: 40px;
            margin-bottom: 10px;
            font-weight: 600;
        }
        .footer-meta-row {
            display: flex;
            justify-content: space-between;
            color: #64748b;
            font-size: 9px;
        }

        /* Bottom Footer Image Band */
        .footer-image-band {
            margin: 0 -30px -30px -30px;
            border-top: 1px solid #e2e8f0;
        }
        .footer-image-band img {
            width: 100%;
            display: block;
        }
    </style>
</head>
<body>

    <div class="print-toolbar no-print">
        <a href="<?= View::url('finance/factures/' . $invoice['id']) ?>" class="btn-back">← Retour à la facture</a>
        <button onclick="window.print()" class="btn-print">Imprimer la facture</button>
    </div>

    <div class="invoice-print-container">
        <!-- Header -->
        <div class="invoice-header-row">
            <div class="logo-container">
                <img src="<?= View::asset('images/site/logo.jpeg') ?>" alt="Logo LBP">
            </div>
            <div class="title-badge-container">
                <span>Imprimé Spécifique</span>
                <h1>Facture & Colisage</h1>
            </div>
            <div class="qr-top-container">
                <img src="<?= $trackingQrUrl ?>" alt="QR Code Tracking">
                <span>Suivi colis</span>
            </div>
        </div>

        <!-- Warning -->
        <div class="warning-banner">
            VOUS DISPOSEZ DE 3 JOURS POUR RÉCUPÉRER VOTRE COLIS À COMPTER DE LA DATE DE NOTIFICATION. PASSÉ CE DÉLAI, NOUS DÉCLINONS TOUTE RESPONSABILITÉ.
        </div>

        <!-- Agency / Service Client -->
        <div class="agency-info-row">
            <div>
                <strong>Agence :</strong> <?= View::e($colis['departure_agency'] ?? 'LBP COTE D\'IVOIRE ABOBO (Abidjan Abobo)') ?> — Tel: <?= View::e($colis['departure_phone'] ?? '—') ?>
            </div>
            <div class="service-client">
                SERVICE CLIENT : 0503497979 / 0509467979
            </div>
        </div>

        <!-- Colis Banner -->
        <div class="colis-banner">
            Détails Colis <?= View::e($trackingNum) ?>
        </div>

        <!-- Meta Grid -->
        <div class="meta-info-grid">
            <div class="meta-group">
                <div class="meta-item">
                    <span class="meta-label">Code Colis :</span>
                    <span class="meta-value"><?= View::e($trackingNum) ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Expéditeur :</span>
                    <span class="meta-value"><?= View::e($senderName) ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Tél Exp. :</span>
                    <span class="meta-value"><?= View::e($senderPhone) ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Trafic :</span>
                    <span class="meta-value"><?= View::e($trafic) ?></span>
                </div>
            </div>
            <div class="meta-group">
                <div class="meta-item">
                    <span class="meta-label">Date d'envoi :</span>
                    <span class="meta-value"><?= View::e($dateEnvoi) ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Destination :</span>
                    <span class="meta-value"><?= View::e($destination) ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Destinataire :</span>
                    <span class="meta-value"><?= View::e($receiverName) ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Tél Dest. :</span>
                    <span class="meta-value"><?= View::e($receiverPhone) ?></span>
                </div>
            </div>
        </div>

        <!-- Pack count label -->
        <div class="total-packs-label">
            Nombre total de colis : <?= $colis ? count($marchandises) : 1; ?>
        </div>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 5%;">N°</th>
                    <th style="width: 10%;">Nbre Colis</th>
                    <th style="width: 30%;">Description</th>
                    <th style="width: 15%;">Emballage</th>
                    <th style="width: 10%;">Qté Emb.</th>
                    <th style="width: 10%;">Poids (kg)</th>
                    <th style="width: 10%;">Prix / Kg</th>
                    <th style="width: 10%;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($marchandises)): ?>
                    <tr>
                        <td>1</td>
                        <td>1</td>
                        <td><?= View::e($colis ? ($colis['description'] ?: 'KARITE') : 'Marchandise Générale') ?></td>
                        <td>petit_car...</td>
                        <td>1</td>
                        <td><?= $colis ? number_format((float)$colis['total_weight'], 0) : '12'; ?></td>
                        <td><?= number_format($totalXof / max(1.0, (float)($colis ? $colis['total_weight'] : 12)), 0, ',', '.') ?> F</td>
                        <td><?= number_format($totalXof, 0, ',', '.') ?> F</td>
                    </tr>
                <?php else: foreach ($marchandises as $idx => $m): 
                    $weight = (float)$m['unit_weight'] * (int)$m['quantity'];
                    $unitPrice = $totalXof / max(1.0, (float)($colis ? $colis['total_weight'] : $weight));
                    $lineTotal = $unitPrice * $weight;
                    ?>
                    <tr>
                        <td><?= $idx + 1 ?></td>
                        <td><?= (int)$m['quantity'] ?></td>
                        <td><?= View::e($m['description']) ?></td>
                        <td>Carton / Sac</td>
                        <td>1</td>
                        <td><?= number_format($weight, 0) ?></td>
                        <td><?= number_format($unitPrice, 0, ',', '.') ?> F</td>
                        <td><?= number_format($lineTotal, 0, ',', '.') ?> F</td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>

        <!-- Totals Block -->
        <div class="totals-container">
            <table class="totals-table">
                <tr>
                    <td>SOUS-TOTAL</td>
                    <td><?= number_format($totalXof, 0, ',', '.') ?> FCFA</td>
                </tr>
                <tr>
                    <td>EMBALLAGE</td>
                    <td>0 FCFA</td>
                </tr>
                <tr class="grand-total">
                    <td>MONTANT TOTAL</td>
                    <td>
                        <?= number_format($totalXof, 0, ',', '.') ?> FCFA
                        <span class="exchange-note">≈ <?= number_format($totalEur, 2, ',', '.') ?> €</span>
                    </td>
                </tr>
            </table>
        </div>

        <div class="transaction-note">
            Les frais de transaction sont à la charge du client.
        </div>

        <!-- Signatures and QR -->
        <div class="signatures-row">
            <div class="signature-box">
                CLIENT (date et visa)
            </div>
            <div class="signature-box">
                SOCIÉTÉ (date et visa)
            </div>
            <div class="qr-pay-container">
                <img src="<?= $paymentQrUrl ?>" alt="QR Code Pay">
                <span>SCANNEZ POUR PAYER</span>
                <span style="color: #64748b; font-weight: normal; font-size: 8px; display: block; margin-top: 2px;">(Wave / Orange Money)</span>
            </div>
        </div>

        <!-- Footer Address info -->
        <div class="footer-info">
            <div class="footer-address">Adresse : Paris 17 Chemin des Vignes 93000 Bobigny</div>
            <div class="footer-phones">Tél : +33 7 75 73 27 97 / +33 7 51 19 83 82 / +33 7 45 93 56 92</div>
            <div class="footer-hours">
                <div><strong>ABIDJAN</strong> Lun-Ven : 08h-17h | Sam-Dim : 08h-14h30</div>
                <div><strong>PARIS</strong> Lun-Sam : 10h30-18h | Dim : 10h-14h</div>
            </div>
            <div class="footer-meta-row">
                <span>Édité par <?= View::e(Auth::user()->fullName ?? 'Système') ?> le <?= date('d/m/Y à H:i') ?></span>
                <span>Réf. FCO-<?= str_pad((string)$invoice['id'], 4, '0', STR_PAD_LEFT) ?></span>
            </div>
        </div>

        <!-- Footer Band Image -->
        <div class="footer-image-band">
            <img src="<?= View::asset('images/site/footer_lbp.png') ?>" alt="LBP Footer Band">
        </div>
    </div>

</body>
</html>
