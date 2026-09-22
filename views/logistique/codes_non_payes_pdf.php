<?php
/** @var array<string, array<int, array<string, mixed>>> $groupes */
/** @var array<int, array<string, mixed>> $colisExport */
/** @var array<string, array<string, mixed>> $sousTotauxGroupes */
/** @var array<string, mixed> $totaux */
/** @var array<string, mixed> $totauxExport */
/** @var string $dateDebut */
/** @var string $dateFin */
/** @var string $nomAgence */
/** @var string $typeTransport */

$titrePeriode = ($dateDebut === $dateFin)
    ? "DU " . date('d/m/Y', strtotime($dateDebut))
    : "DU " . date('d/m/Y', strtotime($dateDebut)) . " AU " . date('d/m/Y', strtotime($dateFin));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>CODES NON PAYES <?= $titrePeriode ?> — S.T.T-CI</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm 12mm 12mm 12mm;
        }

        body {
            font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
            font-size: 10px;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 10px;
        }

        .no-print {
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f1f5f9;
            padding: 8px 14px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
        }

        .btn-print {
            padding: 8px 18px;
            background: #0f172a;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-print:hover { background: #1e293b; }

        /* En-tête officiel S.T.T-CI */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .header-table td {
            vertical-align: middle;
            border: none;
            padding: 0;
        }
        .company-name {
            font-size: 16px;
            font-weight: 900;
            color: #0f172a;
            letter-spacing: 1px;
        }
        .company-sub {
            font-size: 10px;
            font-weight: bold;
            color: #475569;
            text-transform: uppercase;
        }
        .doc-title-box {
            text-align: right;
        }
        .doc-title {
            font-size: 15px;
            font-weight: 900;
            color: #b91c1c;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .doc-subtitle {
            font-size: 10px;
            font-weight: bold;
            color: #334155;
            margin-top: 2px;
        }

        /* Cartouche d'informations */
        .meta-strip {
            display: flex;
            justify-content: space-between;
            background: #f8fafc;
            border: 1.5px solid #0f172a;
            padding: 6px 10px;
            margin-bottom: 10px;
            font-weight: bold;
            font-size: 10px;
        }

        /* Table principale format Excel */
        .excel-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .excel-table th, .excel-table td {
            border: 1px solid #000;
            padding: 5px 6px;
            font-size: 9.5px;
            vertical-align: middle;
        }
        .excel-table th {
            background-color: #e2e8f0;
            color: #000;
            font-weight: 900;
            text-align: center;
            font-size: 9.5px;
            text-transform: uppercase;
        }

        .excel-table tr.group-row td {
            background-color: #cbd5e1;
            font-weight: 900;
            font-size: 10.5px;
            text-transform: uppercase;
            padding: 6px;
            border-top: 2px solid #000;
        }

        .excel-table tr.subtotal-row td {
            background-color: #f1f5f9;
            font-weight: 900;
            font-size: 10px;
            border-top: 1.5px solid #000;
            border-bottom: 1.5px solid #000;
        }

        .excel-table tr.export-banner-row td {
            background-color: #1e3a8a;
            color: #fff;
            font-weight: 900;
            font-size: 10px;
            text-align: center;
            letter-spacing: 1px;
            border-top: 2px solid #000;
        }

        .excel-table tr.export-subtotal-row td {
            background-color: #dbeafe;
            font-weight: 900;
            font-size: 10px;
            border-top: 1.5px solid #000;
            border-bottom: 1.5px solid #000;
        }

        .excel-table tr.grand-total-row td {
            background-color: #0f172a;
            color: #fff;
            font-weight: 900;
            font-size: 11px;
            padding: 8px 6px;
            border: 2px solid #000;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-bold { font-weight: bold; }

        .due-amount {
            color: #b91c1c;
            font-weight: 900;
        }

        /* Signatures */
        .signatures-grid {
            display: flex;
            justify-content: space-between;
            margin-top: 25px;
            page-break-inside: avoid;
        }
        .signature-box {
            width: 30%;
            border-top: 1.5px solid #000;
            padding-top: 6px;
            text-align: center;
            font-size: 10px;
            font-weight: bold;
        }
        .signature-space {
            height: 45px;
        }

        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
            .excel-table th { background-color: #e2e8f0 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .excel-table tr.group-row td { background-color: #cbd5e1 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .excel-table tr.subtotal-row td { background-color: #f1f5f9 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .excel-table tr.export-banner-row td { background-color: #1e3a8a !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .excel-table tr.export-subtotal-row td { background-color: #dbeafe !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .excel-table tr.grand-total-row td { background-color: #0f172a !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

    <!-- Barre d'action hors impression -->
    <div class="no-print">
        <div>
            <strong>Document Officiel S.T.T-CI</strong> — Prêt pour impression directe ou Enregistrement en PDF (Format Paysage conseillé).
        </div>
        <div>
            <button onclick="window.print()" class="btn-print">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                    <rect x="6" y="14" width="12" height="8"></rect>
                </svg>
                Imprimer le Document / Enregistrer en PDF
            </button>
        </div>
    </div>

    <!-- En-tête -->
    <table class="header-table">
        <tr>
            <td>
                <div class="company-name">S.T.T-CI / LA BELLE PORTE TRANSIT</div>
                <div class="company-sub">Direction de l'Exploitation — Département Logistique & Groupages</div>
            </td>
            <td class="doc-title-box">
                <div class="doc-title">CODES NON PAYES <?= $titrePeriode ?></div>
                <div class="doc-subtitle">Édition du <?= date('d/m/Y à H:i') ?> | Réf : CNP-GRP-<?= date('Ymd') ?></div>
            </td>
        </tr>
    </table>

    <!-- Bandeau métadonnées -->
    <div class="meta-strip">
        <div><strong>Agence / Périmètre :</strong> <?= htmlspecialchars($nomAgence) ?></div>
        <div><strong>Type de flux :</strong> <?= htmlspecialchars($typeTransport === 'export' ? 'Colis Export Uniquement' : ($typeTransport === 'cargo' ? 'Cargo Uniquement' : 'Tous types de transport')) ?></div>
        <div><strong>Total Colis Impayés :</strong> <?= (int)$totaux['count'] ?> colis</div>
        <div><strong>Reste à Recouvrer :</strong> <span class="due-amount"><?= number_format($totaux['montant_restant'], 0, ',', ' ') ?> FCFA</span></div>
    </div>

    <!-- Tableau -->
    <table class="excel-table">
        <thead>
            <tr>
                <th style="width: 70px;">DATE</th>
                <th style="width: 75px;">GROUPE</th>
                <th style="width: 110px;">CODE / TRACKING</th>
                <th style="width: 100px;">N° FACTURE</th>
                <th style="width: 90px;">AGENCE</th>
                <th>EXPÉDITEUR & DESTINATAIRE</th>
                <th style="width: 85px;" class="text-right">MONTANT EURO</th>
                <th style="width: 95px;" class="text-right">MONTANT À CRÉDIT</th>
                <th style="width: 90px;" class="text-right">MONTANT PAYÉ</th>
                <th style="width: 95px;" class="text-right">MONTANT RESTANT</th>
                <th style="width: 75px;" class="text-center">STATUT</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($groupes)): ?>
                <tr>
                    <td colspan="11" class="text-center" style="padding: 25px; font-weight: bold;">
                        Aucun code non payé enregistré pour la période et les agences sélectionnées.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($groupes as $groupeNom => $lignes): ?>
                    <!-- Ligne Titre Groupe -->
                    <tr class="group-row">
                        <td colspan="11">
                            GROUPE : <?= htmlspecialchars($groupeNom) ?> (<?= count($lignes) ?> colis)
                        </td>
                    </tr>

                    <!-- Détail colis du groupe -->
                    <?php foreach ($lignes as $row): ?>
                        <tr>
                            <td class="text-center"><?= htmlspecialchars($row['date_formatted']) ?></td>
                            <td class="text-center text-bold"><?= htmlspecialchars($row['groupe_display']) ?></td>
                            <td class="text-bold"><?= htmlspecialchars($row['code']) ?></td>
                            <td><?= htmlspecialchars($row['numero_facture']) ?></td>
                            <td><?= htmlspecialchars($row['agence_nom']) ?></td>
                            <td>
                                <strong>Exp:</strong> <?= htmlspecialchars($row['expediteur_nom'] ?? 'Client') ?><br>
                                <span style="color: #475569;"><strong>Dest:</strong> <?= htmlspecialchars($row['destinataire_nom'] ?? '-') ?></span>
                            </td>
                            <td class="text-right">
                                <?= $row['montant_eur'] > 0 ? number_format($row['montant_eur'], 2, ',', ' ') . ' €' : '-' ?>
                            </td>
                            <td class="text-right text-bold">
                                <?= number_format($row['montant_total'], 0, ',', ' ') ?>
                            </td>
                            <td class="text-right" style="color: #15803d;">
                                <?= number_format($row['montant_encaisse'], 0, ',', ' ') ?>
                            </td>
                            <td class="text-right due-amount">
                                <?= number_format($row['montant_restant'], 0, ',', ' ') ?>
                            </td>
                            <td class="text-center text-bold">
                                <?= htmlspecialchars($row['facture_statut_label']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <!-- Sous-total du groupe -->
                    <?php if (!empty($sousTotauxGroupes[$groupeNom])): ?>
                        <?php $sg = $sousTotauxGroupes[$groupeNom]; ?>
                        <tr class="subtotal-row">
                            <td colspan="6" class="text-right">
                                SOUS-TOTAL GROUPE <?= htmlspecialchars($groupeNom) ?> (<?= $sg['count'] ?> COLIS) :
                            </td>
                            <td class="text-right">
                                <?= $sg['montant_eur'] > 0 ? number_format($sg['montant_eur'], 2, ',', ' ') . ' €' : '-' ?>
                            </td>
                            <td class="text-right">
                                <?= number_format($sg['montant_total'], 0, ',', ' ') ?>
                            </td>
                            <td class="text-right">
                                <?= number_format($sg['montant_encaisse'], 0, ',', ' ') ?>
                            </td>
                            <td class="text-right due-amount">
                                <?= number_format($sg['montant_restant'], 0, ',', ' ') ?>
                            </td>
                            <td></td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>

                <!-- Section COLIS EXPORT Spécifique -->
                <?php if (!empty($colisExport) && count($colisExport) > 0): ?>
                    <tr class="export-banner-row">
                        <td colspan="11">
                            --- RECAPITULATIF SECTION COLIS EXPORT (<?= $totauxExport['count'] ?> COLIS) ---
                        </td>
                    </tr>
                    <tr class="export-subtotal-row">
                        <td colspan="6" class="text-right">
                            SOUS-TOTAL GÉNÉRAL COLIS EXPORT :
                        </td>
                        <td class="text-right">
                            <?= $totauxExport['montant_eur'] > 0 ? number_format($totauxExport['montant_eur'], 2, ',', ' ') . ' €' : '-' ?>
                        </td>
                        <td class="text-right">
                            <?= number_format($totauxExport['montant_total'], 0, ',', ' ') ?>
                        </td>
                        <td class="text-right">
                            <?= number_format($totauxExport['montant_encaisse'], 0, ',', ' ') ?>
                        </td>
                        <td class="text-right due-amount">
                            <?= number_format($totauxExport['montant_restant'], 0, ',', ' ') ?>
                        </td>
                        <td></td>
                    </tr>
                <?php endif; ?>

                <!-- TOTAL GÉNÉRAL -->
                <tr class="grand-total-row">
                    <td colspan="6" class="text-right" style="color: #fff;">
                        TOTAL GÉNÉRAL DES CODES NON PAYÉS (<?= $totaux['count'] ?> COLIS) :
                    </td>
                    <td class="text-right" style="color: #fff;">
                        <?= $totaux['montant_eur'] > 0 ? number_format($totaux['montant_eur'], 2, ',', ' ') . ' €' : '-' ?>
                    </td>
                    <td class="text-right" style="color: #fff;">
                        <?= number_format($totaux['montant_total'], 0, ',', ' ') ?> FCFA
                    </td>
                    <td class="text-right" style="color: #4ade80;">
                        <?= number_format($totaux['montant_encaisse'], 0, ',', ' ') ?> FCFA
                    </td>
                    <td class="text-right" style="color: #f87171; font-weight: 900;">
                        <?= number_format($totaux['montant_restant'], 0, ',', ' ') ?> FCFA
                    </td>
                    <td></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Cartouche Signatures -->
    <div class="signatures-grid">
        <div class="signature-box">
            Le Responsable Groupage Général<br>
            <strong>KOUAKOU SALES</strong>
            <div class="signature-space"></div>
            (Date et Visa)
        </div>
        <div class="signature-box">
            La Caisse Principale / Comptabilité<br>
            <strong>CARINE ABOU</strong>
            <div class="signature-space"></div>
            (Date et Visa)
        </div>
        <div class="signature-box">
            La Direction Générale<br>
            <strong>DIRECTION GÉNÉRALE</strong>
            <div class="signature-space"></div>
            (Date et Visa)
        </div>
    </div>

</body>
</html>
