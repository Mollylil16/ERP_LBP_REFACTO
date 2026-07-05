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
            font-size: 12px;
            line-height: 1.4;
            background-color: #fff;
        }
        
        .facture-container {
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #e2e8f0;
            padding: 30px;
            border-radius: 8px;
            position: relative;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .header-logo {
            font-size: 28px;
            font-weight: 800;
            color: #1e3a5f;
            letter-spacing: -1px;
        }
        .header-logo span {
            color: #f97316;
        }

        .imprime-badge {
            background-color: #1e3a5f;
            color: #ffffff;
            text-align: center;
            padding: 6px 12px;
            font-weight: 700;
            font-size: 13px;
            border-radius: 4px;
            text-transform: uppercase;
        }

        .info-bar {
            background-color: #fef3c7;
            border: 1px solid #fde68a;
            color: #b45309;
            padding: 8px;
            text-align: center;
            font-weight: 600;
            font-size: 11px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .banner-blue {
            background-color: #1e3a5f;
            color: #ffffff;
            text-align: center;
            padding: 12px;
            font-size: 16px;
            font-weight: 700;
            border-radius: 6px;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .grid-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }

        .grid-table td {
            padding: 6px 0;
            vertical-align: top;
        }

        .label {
            font-weight: 700;
            color: #1e3a5f;
            width: 35%;
        }

        .value {
            color: #334155;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }

        .items-table th {
            background-color: #1e3a5f;
            color: #ffffff;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 10px;
            padding: 8px 10px;
            text-align: left;
        }

        .items-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
        }

        .items-table tfoot td {
            padding: 8px 10px;
            border-bottom: none;
            font-weight: 700;
        }

        .total-row {
            background-color: #1e3a5f;
            color: #ffffff;
            font-size: 13px;
        }

        .signatures-table {
            width: 100%;
            margin-top: 30px;
            margin-bottom: 40px;
        }

        .signature-box {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 12px;
            height: 90px;
            font-weight: 600;
            color: #1e3a5f;
            background-color: #f8fafc;
        }

        .footer {
            text-align: center;
            color: #64748b;
            font-size: 10px;
            border-top: 1px solid #e2e8f0;
            padding-top: 15px;
            margin-top: 20px;
        }

        .footer strong {
            color: #1e3a5f;
        }

        .btn-print {
            background-color: #f97316;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 700;
            border-radius: 6px;
            cursor: pointer;
            display: inline-block;
            margin-bottom: 20px;
        }

        @media print {
            .btn-print {
                display: none;
            }
            body {
                padding: 0;
            }
            .facture-container {
                border: none;
                padding: 0;
            }
        }
    </style>
</head>
<body>

    <div style="text-align: center;">
        <button class="btn-print" onclick="window.print()">🖨️ Imprimer la Facture</button>
    </div>

    <div class="facture-container">
        
        <!-- Header -->
        <table class="header-table">
            <tr>
                <td style="width: 50%;">
                    <div class="header-logo">LBP<span>-CI</span></div>
                    <div style="font-size: 10px; color: #64748b; font-weight: 600;">LA BELLE PORTE</div>
                </td>
                <td style="text-align: right; width: 50%;">
                    <div class="imprime-badge">Imprimé Spécifique<br>Facture & Colisage</div>
                    <div style="margin-top: 5px; font-size: 10px; color: #64748b;">
                        Suivi colis : <strong><?= View::e($colis['numero_tracking']) ?></strong>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Info Notification Bar -->
        <div class="info-bar">
            VOUS DISPOSEZ DE 3 JOURS POUR RÉCUPÉRER VOTRE COLIS À COMPTER DE LA DATE DE NOTIFICATION. PASSÉ CE DÉLAI, NOUS DÉCLINONS TOUTE RESPONSABILITÉ.
        </div>

        <table style="width: 100%; margin-bottom: 10px;">
            <tr>
                <td><small style="color: #64748b;">Agence :</small> <strong>LBP Logistics — <?= View::e($colis['agence_depart_name'] ?? 'Siège Social') ?></strong></td>
                <td style="text-align: right;"><strong>SERVICE CLIENT : 0503467979 / 0503497979</strong></td>
            </tr>
        </table>

        <!-- Banner -->
        <div class="banner-blue">
            DÉTAILS COLIS &nbsp;&nbsp;<?= View::e($colis['numero_tracking']) ?>
        </div>

        <div style="text-align: center; margin-bottom: 15px; font-weight: 600;">
            Nombre total de colis : <?= View::e((string) ($colis['nombre_colis'] ?? 1)) ?>
        </div>

        <!-- Main Info Grid -->
        <table class="grid-table">
            <tr>
                <td style="width: 50%;">
                    <table style="width: 100%;">
                        <tr>
                            <td class="label">Code Colis :</td>
                            <td class="value"><strong><?= View::e($colis['numero_tracking']) ?></strong></td>
                        </tr>
                        <tr>
                            <td class="label">EXPÉDITEUR :</td>
                            <td class="value"><?= View::e($colis['expediteur_name']) ?></td>
                        </tr>
                        <tr>
                            <td class="label">TÉL EXP. :</td>
                            <td class="value"><?= View::e($colis['expediteur_phone'] ?? '—') ?></td>
                        </tr>
                        <tr>
                            <td class="label">TRAFIC :</td>
                            <td class="value"><strong><?= View::e($traficLabel) ?></strong></td>
                        </tr>
                    </table>
                </td>
                <td style="width: 50%;">
                    <table style="width: 100%;">
                        <tr>
                            <td class="label">Date d'envoi :</td>
                            <td class="value"><?= View::e(date('d/m/Y', strtotime($colis['created_at']))) ?></td>
                        </tr>
                        <tr>
                            <td class="label">DESTINATION :</td>
                            <td class="value"><strong><?= View::e($colis['agence_arrivee_name'] ?? '—') ?></strong></td>
                        </tr>
                        <tr>
                            <td class="label">DESTINATAIRE :</td>
                            <td class="value"><?= View::e($colis['destinataire_name']) ?></td>
                        </tr>
                        <tr>
                            <td class="label">TÉL DEST. :</td>
                            <td class="value"><?= View::e($colis['destinataire_phone'] ?? '—') ?></td>
                        </tr>
                    </table>
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
                        <td colspan="8" style="text-align: center; padding: 15px;">Aucune marchandise répertoriée.</td>
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
                            <td style="text-align: right;"><?= View::e(number_format((float) ($m['prix_kg'] ?? 0), 0, ',', ' ')) ?></td>
                            <td style="text-align: right; font-weight: 600;"><?= number_format((float) ($m['total_ligne'] ?? 0), 0, ',', '.') ?> FCFA</td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="7" style="text-align: right;">SOUS-TOTAL</td>
                    <td style="text-align: right;"><?= number_format($sousTotal, 0, ',', '.') ?> FCFA</td>
                </tr>
                <tr class="total-row">
                    <td colspan="7" style="text-align: right; font-size: 12px; font-weight: 700; border-top: 1px solid #fff;">MONTANT TOTAL</td>
                    <td style="text-align: right; font-weight: 800; font-size: 13px;">
                        <?= number_format($montantTotal, 0, ',', '.') ?> FCFA<br>
                        <span style="font-size: 10px; font-weight: 400; opacity: 0.9;">≈ <?= number_format($montantEur, 2, ',', '.') ?> €</span>
                    </td>
                </tr>
            </tfoot>
        </table>

        <!-- Signatures and QR Mockup -->
        <table class="signatures-table">
            <tr>
                <td style="width: 40%;">
                    <div class="signature-box">
                        CLIENT (date et visa)
                    </div>
                </td>
                <td style="width: 20%; text-align: center; vertical-align: middle;">
                    <!-- Wave / Orange Money SVG QR Code mockup like in the image -->
                    <div style="display: inline-block; padding: 5px; border: 1px solid #cbd5e1; border-radius: 4px; background: white;">
                        <svg width="60" height="60" viewBox="0 0 29 29" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M0 0h9v9H0zm1 1v7h7V1zm1 1v5h5V2zm1 1v3h3V3zm6-3h11v2H9zm11 0h9v9h-9zm1 1v7h7V1zm1 1v5h5V2zm1 1v3h3V3zm-14 8h2v2H6zm3 0h11v2H9zm11 0h3v4h-3zm3 2h6v2h-6zm-17 4h9v9H0zm1 1v7h7V17zm1 1v5h5v-5zm1 1v3h3v-3zm14-1h3v2h-3zm-6 2h4v2h-4zm4 0h2v4h-2zm-2 2h2v2h-2zm4 0h3v2h-3zm-3 2h4v2h-4zm4 0h5v2h-5z" fill="#1e3a5f"/>
                        </svg>
                        <div style="font-size: 7px; font-weight: 700; color: #1e3a5f; margin-top: 3px; text-transform: uppercase;">SCANNEZ POUR PAYER</div>
                    </div>
                </td>
                <td style="width: 40%;">
                    <div class="signature-box">
                        SOCIÉTÉ (date et visa)
                    </div>
                </td>
            </tr>
        </table>

        <!-- Footer -->
        <div class="footer">
            <div style="display:flex; justify-content:space-between; margin-bottom:10px; font-size:10px; border-bottom:1px solid #cbd5e1; padding-bottom:5px; color:#64748b;">
                <?php
                $operatorName = \App\Helpers\Auth::user() ? \App\Helpers\Auth::user()->fullName : 'Service Transit';
                ?>
                <div>Édité par <?= View::e($operatorName) ?> le <?= date('d/m/Y', strtotime($colis['created_at'])) ?> à <?= date('H:i', strtotime($colis['created_at'])) ?></div>
                <div>Réf. FCO-<?= View::e(date('my', strtotime($colis['created_at']))) ?>-<?= View::e(substr($colis['numero_tracking'], -3)) ?></div>
            </div>
            <p><strong>ADRESSE : PARIS 17 CHEMIN DES VIGNES 93000 BOBIGNY</strong></p>
            <p>Tél : +33 7 75 73 27 97 / +33 7 51 19 83 82 / +33 7 45 93 56 92</p>
            <table style="width: 100%; margin-top: 10px; font-size: 9px; color: #64748b;">
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
            <p style="margin-top: 15px; font-size: 9px; border-top: 1px dashed #cbd5e1; padding-top: 10px;">
                www.labelleporte.net | contact@labelleporte.net | +2252721580978 | +2250101222195
            </p>
        </div>

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
