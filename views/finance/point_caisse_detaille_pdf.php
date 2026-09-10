<?php

use App\Helpers\View;

/** @var array<int, array<string, mixed>> $agencesDetail */
/** @var array<int, array<string, mixed>> $natureBreakdown */
/** @var array<string, mixed> $summary */
/** @var array<string, mixed> $periode */
/** @var string $perimetre */
/** @var string $editePar */
/** @var string $editeLe */

$fmtXof = static fn($v): string => number_format((float) $v, 0, ',', ' ');
$fmtEur = static fn($v): string => number_format((float) $v, 2, ',', ' ');
$fmtKg  = static fn($v): string => number_format((float) $v, 2, ',', ' ');
$fmtDate = static fn(?string $d): string => $d ? date('d/m/Y', strtotime($d)) : '—';
$fmtHeure = static fn(?string $d): string => $d ? date('H:i', strtotime($d)) : '—';

$libelleMode = static function (string $mode): string {
    return match (strtoupper($mode)) {
        'ESPECES' => 'Espèces',
        'WAVE' => 'Wave',
        'ORANGE_MONEY' => 'Orange Money',
        'MTN_MOMO' => 'MTN MoMo',
        'MOBILE_MONEY' => 'Mobile Money',
        'CARTE' => 'Carte bancaire',
        'VIREMENT' => 'Virement',
        'CHEQUE' => 'Chèque',
        default => ucfirst(strtolower(str_replace('_', ' ', $mode))),
    };
};

/**
 * Nature du contenu d'un colis : les marchandises saisies au colisage font foi,
 * `categorie_produit` ne servant que de repli.
 */
$natureColis = static function (array $row, bool $avecEmballage = true): string {
    $valeur = $avecEmballage
        ? (string) ($row['natures_detail'] ?? $row['natures'] ?? '')
        : (string) ($row['natures'] ?? '');

    if (trim($valeur) === '') {
        $valeur = (string) ($row['categorie_produit'] ?? '');
    }

    return trim($valeur) !== '' ? $valeur : '—';
};

$titrePeriode = $periode['est_journee_unique']
    ? 'Journée du ' . $fmtDate($periode['debut'])
    : 'Période du ' . $fmtDate($periode['debut']) . ' au ' . $fmtDate($periode['fin']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Points de Caisse Détaillés — <?= View::e($perimetre) ?> — <?= View::e($titrePeriode) ?></title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        * { box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 10px; color: #0f172a; margin: 16px; }

        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #0f172a; padding-bottom: 10px; margin-bottom: 14px; }
        .logo { font-size: 19px; font-weight: 800; color: #0f172a; letter-spacing: -0.4px; }
        .sub-logo { font-size: 10px; color: #64748b; margin-top: 2px; }
        .title { text-align: right; }
        .title-main { font-size: 14px; font-weight: 800; color: #0f172a; }
        .title-sub { font-size: 11px; color: #334155; margin-top: 3px; font-weight: 600; }

        .meta-box { background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px 14px; margin-bottom: 14px; display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
        .meta-label { font-size: 8px; text-transform: uppercase; font-weight: 700; color: #64748b; letter-spacing: 0.4px; }
        .meta-value { font-size: 11px; font-weight: 700; color: #0f172a; margin-top: 2px; }

        h2.section { font-size: 12px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; margin: 18px 0 8px; padding-bottom: 4px; border-bottom: 2px solid #e2e8f0; }
        h3.sub-section { font-size: 11px; font-weight: 800; color: #334155; margin: 12px 0 6px; }

        .kpi-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; margin-bottom: 6px; }
        .kpi-card { background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px 10px; }
        .kpi-title { font-size: 8px; text-transform: uppercase; font-weight: 700; color: #64748b; letter-spacing: 0.3px; }
        .kpi-value { font-size: 13px; font-weight: 800; color: #0f172a; margin-top: 3px; }
        .kpi-value.pos { color: #15803d; }
        .kpi-value.neg { color: #b91c1c; }

        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th { background: #0f172a; color: #ffffff; padding: 6px 6px; font-weight: 700; text-align: left; font-size: 8px; text-transform: uppercase; letter-spacing: 0.2px; }
        td { padding: 5px 6px; border-bottom: 1px solid #e2e8f0; font-size: 9px; vertical-align: top; }
        tbody tr:nth-child(even) { background-color: #f8fafc; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .nowrap { white-space: nowrap; }
        .muted { color: #64748b; font-size: 8px; }
        .total-row td { font-weight: 800; background: #e2e8f0; border-top: 2px solid #94a3b8; border-bottom: none; }

        .agence-block { page-break-before: always; }
        .agence-block:first-of-type { page-break-before: auto; }
        .agence-header { background: #0f172a; color: #ffffff; padding: 8px 12px; border-radius: 6px; display: flex; justify-content: space-between; align-items: center; margin-top: 8px; }
        .agence-name { font-size: 13px; font-weight: 800; }
        .agence-recap { font-size: 9px; color: #cbd5e1; }

        .jour-block { page-break-inside: avoid; border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px 12px; margin-top: 10px; }
        .jour-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; margin-bottom: 4px; }
        .jour-date { font-size: 12px; font-weight: 800; color: #0f172a; }

        .badge { display: inline-block; padding: 2px 7px; border-radius: 4px; font-weight: 700; font-size: 8px; text-transform: uppercase; }
        .badge-soumis { background: #e0f2fe; color: #0369a1; }
        .badge-consolide { background: #dcfce7; color: #15803d; }
        .badge-brouillon { background: #fef3c7; color: #b45309; }
        .badge-neutre { background: #f1f5f9; color: #475569; }

        .rappro { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-top: 10px; background: #f1f5f9; border-radius: 6px; padding: 8px 10px; }
        .rappro-label { font-size: 8px; text-transform: uppercase; font-weight: 700; color: #64748b; }
        .rappro-value { font-size: 11px; font-weight: 800; color: #0f172a; margin-top: 2px; }
        .note-ecart { margin-top: 6px; background: #fff7ed; border-left: 3px solid #f59e0b; padding: 6px 8px; font-size: 9px; color: #92400e; }

        .empty { padding: 8px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 5px; color: #64748b; font-size: 9px; text-align: center; }

        .signatures { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; margin-top: 28px; page-break-inside: avoid; }
        .sig-box { border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px; height: 85px; }
        .sig-title { font-weight: 700; font-size: 9px; text-transform: uppercase; color: #475569; }

        .footer-note { margin-top: 16px; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 6px; }

        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="margin-bottom: 14px; text-align: right;">
        <button onclick="window.print()" style="padding: 8px 16px; background: #0f172a; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; font-size: 12px;">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            Imprimer / Enregistrer en PDF
        </button>
    </div>

    <div class="header">
        <div>
            <div class="logo">LA BELLE PORTE TRANSIT</div>
            <div class="sub-logo">Direction Financière — Contrôle de caisse</div>
        </div>
        <div class="title">
            <div class="title-main">Rapport Détaillé des Points de Caisse</div>
            <div class="title-sub"><?= View::e($titrePeriode) ?></div>
        </div>
    </div>

    <div class="meta-box">
        <div>
            <div class="meta-label">Périmètre</div>
            <div class="meta-value"><?= View::e($perimetre) ?></div>
        </div>
        <div>
            <div class="meta-label">Plage couverte</div>
            <div class="meta-value"><?= View::e($fmtDate($periode['debut'])) ?> &rarr; <?= View::e($fmtDate($periode['fin'])) ?> <span class="muted">(<?= (int) $periode['nb_jours'] ?> j)</span></div>
        </div>
        <div>
            <div class="meta-label">Édité par</div>
            <div class="meta-value"><?= View::e($editePar) ?></div>
        </div>
        <div>
            <div class="meta-label">Édité le</div>
            <div class="meta-value"><?= View::e($editeLe) ?></div>
        </div>
    </div>

    <?php if ($agencesDetail === []): ?>

        <div class="empty" style="padding: 26px; font-size: 12px;">
            Aucune opération de caisse n'a été enregistrée sur la plage sélectionnée pour ce périmètre.
        </div>

    <?php else: ?>

        <h2 class="section">1. Synthèse de la période</h2>

        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-title">Journées couvertes</div>
                <div class="kpi-value"><?= (int) $summary['nb_jours'] ?> <span class="muted">/ <?= (int) ($summary['nb_agences'] ?? 0) ?> agence(s)</span></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">Factures émises</div>
                <div class="kpi-value"><?= $fmtXof($summary['nb_factures']) ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">Colis enregistrés</div>
                <div class="kpi-value"><?= $fmtXof($summary['nb_colis']) ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">Poids total</div>
                <div class="kpi-value"><?= $fmtKg($summary['poids']) ?> kg</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">Valeur déclarée</div>
                <div class="kpi-value"><?= $fmtXof($summary['valeur_declaree']) ?> XOF</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">Total facturé</div>
                <div class="kpi-value"><?= $fmtXof($summary['facture_xof']) ?> XOF</div>
                <?php if ($summary['facture_eur'] > 0): ?><div class="muted"><?= $fmtEur($summary['facture_eur']) ?> EUR</div><?php endif; ?>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">Total encaissé</div>
                <div class="kpi-value pos"><?= $fmtXof($summary['encaisse_xof']) ?> XOF</div>
                <?php if ($summary['encaisse_eur'] > 0): ?><div class="muted"><?= $fmtEur($summary['encaisse_eur']) ?> EUR</div><?php endif; ?>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">Restant dû</div>
                <div class="kpi-value <?= $summary['restant'] > 0 ? 'neg' : '' ?>"><?= $fmtXof($summary['restant']) ?> XOF</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">Écart de caisse cumulé</div>
                <div class="kpi-value <?= abs((float) $summary['ecart']) < 0.01 ? 'pos' : 'neg' ?>"><?= ($summary['ecart'] > 0 ? '+' : '') . $fmtXof($summary['ecart']) ?> XOF</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">Volume total</div>
                <div class="kpi-value"><?= $fmtKg($summary['volume']) ?> m&sup3;</div>
            </div>
        </div>

        <h3 class="sub-section">Ventilation des encaissements par mode de règlement</h3>
        <?php if (empty($summary['par_mode'])): ?>
            <div class="empty">Aucun encaissement enregistré sur la période.</div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Mode de règlement</th>
                        <th class="text-right">Nombre d'opérations</th>
                        <th class="text-right">Montant encaissé</th>
                        <th class="text-right">Part</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $totalModes = 0.0;
                    foreach ($summary['par_mode'] as $data) {
                        $totalModes += (float) $data['montant'];
                    }
                    ?>
                    <?php foreach ($summary['par_mode'] as $mode => $data): ?>
                        <tr>
                            <td><strong><?= View::e($libelleMode((string) $mode)) ?></strong></td>
                            <td class="text-right"><?= (int) $data['nb'] ?></td>
                            <td class="text-right"><?= $fmtXof($data['montant']) ?> XOF</td>
                            <td class="text-right"><?= $totalModes > 0 ? number_format(((float) $data['montant'] / $totalModes) * 100, 1, ',', ' ') : '0,0' ?> %</td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <td>TOTAL</td>
                        <td class="text-right"><?= array_sum(array_column($summary['par_mode'], 'nb')) ?></td>
                        <td class="text-right"><?= $fmtXof($totalModes) ?> XOF</td>
                        <td class="text-right">100,0 %</td>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>

        <h3 class="sub-section">Répartition par nature de marchandise</h3>
        <?php if (empty($natureBreakdown)): ?>
            <div class="empty">Aucune marchandise détaillée n'a été saisie au colisage sur la période.</div>
        <?php else: ?>
            <?php
            $totalNatureMontant = array_sum(array_map(static fn($n) => (float) $n['montant'], $natureBreakdown));
            $totalNatureColis = array_sum(array_map(static fn($n) => (int) $n['nb_colis'], $natureBreakdown));
            $totalNaturePoids = array_sum(array_map(static fn($n) => (float) $n['poids'], $natureBreakdown));
            ?>
            <table>
                <thead>
                    <tr>
                        <th>Nature de la marchandise</th>
                        <th class="text-right">Expéditions</th>
                        <th class="text-right">Nb colis</th>
                        <th class="text-right">Poids (kg)</th>
                        <th class="text-right">Montant (XOF)</th>
                        <th class="text-right">Part</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($natureBreakdown as $n): ?>
                        <tr>
                            <td><strong><?= View::e($n['nature']) ?></strong></td>
                            <td class="text-right"><?= (int) $n['nb_colis_distincts'] ?></td>
                            <td class="text-right"><?= $fmtXof($n['nb_colis']) ?></td>
                            <td class="text-right"><?= $fmtKg($n['poids']) ?></td>
                            <td class="text-right"><?= $fmtXof($n['montant']) ?></td>
                            <td class="text-right"><?= $totalNatureMontant > 0 ? number_format(((float) $n['montant'] / $totalNatureMontant) * 100, 1, ',', ' ') : '0,0' ?> %</td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <td>TOTAL</td>
                        <td class="text-right"><?= count($natureBreakdown) ?> nature(s)</td>
                        <td class="text-right"><?= $fmtXof($totalNatureColis) ?></td>
                        <td class="text-right"><?= $fmtKg($totalNaturePoids) ?></td>
                        <td class="text-right"><?= $fmtXof($totalNatureMontant) ?></td>
                        <td class="text-right">100,0 %</td>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>

        <h3 class="sub-section">Récapitulatif par agence</h3>
        <table>
            <thead>
                <tr>
                    <th>Agence</th>
                    <th class="text-right">Jours</th>
                    <th class="text-right">Factures</th>
                    <th class="text-right">Colis</th>
                    <th class="text-right">Poids (kg)</th>
                    <th class="text-right">Facturé (XOF)</th>
                    <th class="text-right">Encaissé (XOF)</th>
                    <th class="text-right">Restant dû (XOF)</th>
                    <th class="text-right">Écart (XOF)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($agencesDetail as $ag): $t = $ag['totals']; ?>
                    <tr>
                        <td><strong><?= View::e($ag['agence_name']) ?></strong></td>
                        <td class="text-right"><?= (int) $t['nb_jours'] ?></td>
                        <td class="text-right"><?= (int) $t['nb_factures'] ?></td>
                        <td class="text-right"><?= $fmtXof($t['nb_colis']) ?></td>
                        <td class="text-right"><?= $fmtKg($t['poids']) ?></td>
                        <td class="text-right"><?= $fmtXof($t['facture_xof']) ?></td>
                        <td class="text-right" style="color:#15803d; font-weight:700;"><?= $fmtXof($t['encaisse_xof']) ?></td>
                        <td class="text-right"><?= $fmtXof($t['restant']) ?></td>
                        <td class="text-right" style="color: <?= abs((float) $t['ecart']) < 0.01 ? '#15803d' : '#b91c1c' ?>; font-weight:700;"><?= ($t['ecart'] > 0 ? '+' : '') . $fmtXof($t['ecart']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td>TOTAL RÉSEAU</td>
                    <td class="text-right"><?= (int) $summary['nb_jours'] ?></td>
                    <td class="text-right"><?= (int) $summary['nb_factures'] ?></td>
                    <td class="text-right"><?= $fmtXof($summary['nb_colis']) ?></td>
                    <td class="text-right"><?= $fmtKg($summary['poids']) ?></td>
                    <td class="text-right"><?= $fmtXof($summary['facture_xof']) ?></td>
                    <td class="text-right"><?= $fmtXof($summary['encaisse_xof']) ?></td>
                    <td class="text-right"><?= $fmtXof($summary['restant']) ?></td>
                    <td class="text-right"><?= ($summary['ecart'] > 0 ? '+' : '') . $fmtXof($summary['ecart']) ?></td>
                </tr>
            </tbody>
        </table>

        <?php $numAgence = 0; ?>
        <?php foreach ($agencesDetail as $ag): $numAgence++; $t = $ag['totals']; ?>

            <div class="agence-block">
                <h2 class="section">2.<?= $numAgence ?> Détail — <?= View::e($ag['agence_name']) ?></h2>

                <div class="agence-header">
                    <div class="agence-name"><?= View::e($ag['agence_name']) ?></div>
                    <div class="agence-recap">
                        <?= (int) $t['nb_jours'] ?> journée(s) &nbsp;|&nbsp;
                        <?= (int) $t['nb_factures'] ?> facture(s) &nbsp;|&nbsp;
                        <?= $fmtXof($t['nb_colis']) ?> colis &nbsp;|&nbsp;
                        <?= $fmtKg($t['poids']) ?> kg &nbsp;|&nbsp;
                        Facturé <?= $fmtXof($t['facture_xof']) ?> XOF &nbsp;|&nbsp;
                        Encaissé <?= $fmtXof($t['encaisse_xof']) ?> XOF
                    </div>
                </div>

                <h3 class="sub-section">Récapitulatif jour par jour</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th class="text-right">Factures</th>
                            <th class="text-right">Colis</th>
                            <th class="text-right">Poids (kg)</th>
                            <th class="text-right">Facturé (XOF)</th>
                            <th class="text-right">Encaissé (XOF)</th>
                            <th class="text-right">Restant dû (XOF)</th>
                            <th class="text-right">Solde physique</th>
                            <th class="text-right">Écart</th>
                            <th class="text-center">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ag['jours'] as $jour): $jt = $jour['totals']; ?>
                            <tr>
                                <td class="nowrap"><strong><?= View::e($fmtDate($jour['date'])) ?></strong></td>
                                <td class="text-right"><?= (int) $jt['nb_factures'] ?></td>
                                <td class="text-right"><?= $fmtXof($jt['nb_colis']) ?></td>
                                <td class="text-right"><?= $fmtKg($jt['poids']) ?></td>
                                <td class="text-right"><?= $fmtXof($jt['facture_xof']) ?></td>
                                <td class="text-right" style="color:#15803d; font-weight:700;"><?= $fmtXof($jt['encaisse_xof']) ?></td>
                                <td class="text-right"><?= $fmtXof($jt['restant']) ?></td>
                                <td class="text-right"><?= $jt['solde_physique'] !== null ? $fmtXof($jt['solde_physique']) : '<span class="muted">non déclaré</span>' ?></td>
                                <td class="text-right" style="color: <?= abs((float) $jt['ecart']) < 0.01 ? '#15803d' : '#b91c1c' ?>; font-weight:700;"><?= ($jt['ecart'] > 0 ? '+' : '') . $fmtXof($jt['ecart']) ?></td>
                                <td class="text-center"><span class="badge badge-<?= View::e($jt['statut'] ?? 'neutre') ?>"><?= View::e(strtoupper((string) ($jt['statut'] ?? 'non soumis'))) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="total-row">
                            <td>TOTAL AGENCE</td>
                            <td class="text-right"><?= (int) $t['nb_factures'] ?></td>
                            <td class="text-right"><?= $fmtXof($t['nb_colis']) ?></td>
                            <td class="text-right"><?= $fmtKg($t['poids']) ?></td>
                            <td class="text-right"><?= $fmtXof($t['facture_xof']) ?></td>
                            <td class="text-right"><?= $fmtXof($t['encaisse_xof']) ?></td>
                            <td class="text-right"><?= $fmtXof($t['restant']) ?></td>
                            <td class="text-right">—</td>
                            <td class="text-right"><?= ($t['ecart'] > 0 ? '+' : '') . $fmtXof($t['ecart']) ?></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>

                <?php foreach ($ag['jours'] as $jour): $jt = $jour['totals']; ?>

                    <div class="jour-block">
                        <div class="jour-header">
                            <div class="jour-date"><?= View::e($fmtDate($jour['date'])) ?></div>
                            <div>
                                <span class="badge badge-<?= View::e($jt['statut'] ?? 'neutre') ?>"><?= View::e(strtoupper((string) ($jt['statut'] ?? 'non soumis'))) ?></span>
                                <?php if (!empty($jour['etat']['chef_nom'])): ?>
                                    <span class="muted">&nbsp;Caissier(ère) : <strong><?= View::e($jour['etat']['chef_nom']) ?></strong></span>
                                <?php endif; ?>
                                <?php if (!empty($jour['etat']['date_soumission'])): ?>
                                    <span class="muted">&nbsp;| Soumis le <?= View::e(date('d/m/Y à H:i', strtotime((string) $jour['etat']['date_soumission']))) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <h3 class="sub-section">A. Opérations facturées du jour (<?= (int) $jt['nb_factures'] ?>)</h3>
                        <?php if (empty($jour['operations'])): ?>
                            <div class="empty">Aucune facture émise ce jour.</div>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Heure</th>
                                        <th>N° Facture</th>
                                        <th>N° Tracking</th>
                                        <th>Expéditeur</th>
                                        <th>Destinataire</th>
                                        <th>Nature du contenu</th>
                                        <th>Destination</th>
                                        <th class="text-right">Nb colis</th>
                                        <th class="text-right">Poids (kg)</th>
                                        <th class="text-right">Facturé</th>
                                        <th class="text-right">Encaissé</th>
                                        <th class="text-right">Restant dû</th>
                                        <th>Règlement</th>
                                        <th>Caissier(ère)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($jour['operations'] as $op): ?>
                                        <?php
                                        $devise = strtoupper((string) ($op['devise'] ?? 'XOF'));
                                        $fmtMontant = $devise === 'EUR' ? $fmtEur : $fmtXof;
                                        $destination = trim((string) ($op['destination_ville'] ?? ''));
                                        $pays = trim((string) ($op['destination_pays'] ?? ''));
                                        if ($pays !== '') {
                                            $destination = $destination !== '' ? $destination . ', ' . $pays : $pays;
                                        }
                                        if ($destination === '') {
                                            $destination = trim((string) ($op['trajet'] ?? '')) ?: '—';
                                        }
                                        ?>
                                        <tr>
                                            <td class="nowrap"><?= View::e($fmtHeure($op['date_emission'] ?? null)) ?></td>
                                            <td class="nowrap"><strong><?= View::e($op['numero_facture'] ?? '—') ?></strong></td>
                                            <td class="nowrap"><?= View::e($op['numero_tracking'] ?? '—') ?></td>
                                            <td>
                                                <strong><?= View::e($op['expediteur_nom'] ?? ($op['client_paye_nom'] ?? '—')) ?></strong>
                                                <?php if (!empty($op['expediteur_tel'])): ?><div class="muted"><?= View::e($op['expediteur_tel']) ?></div><?php endif; ?>
                                            </td>
                                            <td>
                                                <?= View::e($op['destinataire_nom'] ?? '—') ?>
                                                <?php if (!empty($op['destinataire_tel'])): ?><div class="muted"><?= View::e($op['destinataire_tel']) ?></div><?php endif; ?>
                                            </td>
                                            <td>
                                                <strong><?= View::e($natureColis($op)) ?></strong>
                                                <?php if ((int) ($op['nb_lignes_marchandise'] ?? 0) > 1): ?>
                                                    <div class="muted"><?= (int) $op['nb_lignes_marchandise'] ?> natures distinctes</div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= View::e($destination) ?></td>
                                            <td class="text-right"><?= (int) ($op['nombre_colis'] ?? 0) ?></td>
                                            <td class="text-right"><?= $fmtKg($op['poids_total'] ?? 0) ?></td>
                                            <td class="text-right"><?= $fmtMontant($op['montant_total'] ?? 0) ?> <?= View::e($devise) ?></td>
                                            <td class="text-right" style="color:#15803d; font-weight:700;"><?= $fmtMontant($op['encaisse_periode'] ?? 0) ?></td>
                                            <td class="text-right" style="color: <?= (float) ($op['montant_restant'] ?? 0) > 0 ? '#b91c1c' : '#64748b' ?>;"><?= $fmtMontant($op['montant_restant'] ?? 0) ?></td>
                                            <td><?= !empty($op['modes_reglement']) ? View::e(implode(', ', array_map($libelleMode, array_map('trim', explode(',', (string) $op['modes_reglement']))))) : '<span class="muted">non réglé</span>' ?></td>
                                            <td><?= View::e($op['caissiere_nom'] ?? '—') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="total-row">
                                        <td colspan="7">TOTAL DU JOUR</td>
                                        <td class="text-right"><?= $fmtXof($jt['nb_colis']) ?></td>
                                        <td class="text-right"><?= $fmtKg($jt['poids']) ?></td>
                                        <td class="text-right"><?= $fmtXof($jt['facture_xof']) ?> XOF</td>
                                        <td class="text-right"><?= $fmtXof($jt['encaisse_xof']) ?></td>
                                        <td class="text-right"><?= $fmtXof($jt['restant']) ?></td>
                                        <td colspan="2"><?php if ($jt['facture_eur'] > 0): ?><span class="muted">dont <?= $fmtEur($jt['facture_eur']) ?> EUR facturés</span><?php endif; ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        <?php endif; ?>

                        <h3 class="sub-section">B. Encaissements passés en caisse ce jour (<?= count($jour['encaissements']) ?>)</h3>
                        <?php if (empty($jour['encaissements'])): ?>
                            <div class="empty">Aucun encaissement enregistré ce jour.</div>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Heure</th>
                                        <th>N° Facture</th>
                                        <th>Émise le</th>
                                        <th>Client</th>
                                        <th>N° Tracking</th>
                                        <th>Nature du contenu</th>
                                        <th class="text-right">Nb colis</th>
                                        <th class="text-right">Poids (kg)</th>
                                        <th>Type</th>
                                        <th>Mode</th>
                                        <th class="text-right">Montant encaissé</th>
                                        <th class="text-right">Solde facture</th>
                                        <th>Caissier(ère)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($jour['encaissements'] as $enc): ?>
                                        <?php
                                        $devise = strtoupper((string) ($enc['devise'] ?? 'XOF'));
                                        $fmtMontant = $devise === 'EUR' ? $fmtEur : $fmtXof;
                                        $estAnterieure = substr((string) ($enc['date_emission'] ?? ''), 0, 10) !== $jour['date'];
                                        ?>
                                        <tr>
                                            <td class="nowrap"><?= View::e($fmtHeure($enc['date_paiement'] ?? null)) ?></td>
                                            <td class="nowrap"><strong><?= View::e($enc['numero_facture'] ?? '—') ?></strong></td>
                                            <td class="nowrap">
                                                <?= View::e($fmtDate($enc['date_emission'] ?? null)) ?>
                                                <?php if ($estAnterieure): ?><div class="muted">recouvrement</div><?php endif; ?>
                                            </td>
                                            <td>
                                                <strong><?= View::e($enc['client_nom'] ?? '—') ?></strong>
                                                <?php if (!empty($enc['client_tel'])): ?><div class="muted"><?= View::e($enc['client_tel']) ?></div><?php endif; ?>
                                            </td>
                                            <td class="nowrap"><?= View::e($enc['numero_tracking'] ?? '—') ?></td>
                                            <td><?= View::e($natureColis($enc, false)) ?></td>
                                            <td class="text-right"><?= (int) ($enc['nombre_colis'] ?? 0) ?></td>
                                            <td class="text-right"><?= $fmtKg($enc['poids_total'] ?? 0) ?></td>
                                            <td><?= View::e(ucfirst((string) ($enc['type_paiement'] ?? 'total'))) ?></td>
                                            <td><?= View::e($libelleMode((string) ($enc['mode_reglement'] ?? 'ESPECES'))) ?></td>
                                            <td class="text-right" style="color:#15803d; font-weight:700;"><?= $fmtMontant($enc['montant'] ?? 0) ?> <?= View::e($devise) ?></td>
                                            <td class="text-right"><?= $fmtMontant($enc['montant_restant'] ?? 0) ?></td>
                                            <td><?= View::e($enc['caissiere_nom'] ?? '—') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="total-row">
                                        <td colspan="10">TOTAL ENCAISSÉ DU JOUR</td>
                                        <td class="text-right"><?= $fmtXof($jt['encaisse_xof']) ?> XOF</td>
                                        <td colspan="2"><?php if ($jt['encaisse_eur'] > 0): ?><span class="muted">dont <?= $fmtEur($jt['encaisse_eur']) ?> EUR</span><?php endif; ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        <?php endif; ?>

                        <?php if (!empty($jt['par_mode'])): ?>
                            <h3 class="sub-section">C. Ventilation du jour par mode de règlement</h3>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Mode de règlement</th>
                                        <th class="text-right">Opérations</th>
                                        <th class="text-right">Montant</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($jt['par_mode'] as $mode => $data): ?>
                                        <tr>
                                            <td><?= View::e($libelleMode((string) $mode)) ?></td>
                                            <td class="text-right"><?= (int) $data['nb'] ?></td>
                                            <td class="text-right"><?= $fmtXof($data['montant']) ?> XOF</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>

                        <div class="rappro">
                            <div>
                                <div class="rappro-label">Solde théorique attendu</div>
                                <div class="rappro-value"><?= $fmtXof($jt['encaisse_xof']) ?> XOF</div>
                            </div>
                            <div>
                                <div class="rappro-label">Solde physique compté</div>
                                <div class="rappro-value"><?= $jt['solde_physique'] !== null ? $fmtXof($jt['solde_physique']) . ' XOF' : 'Non déclaré' ?></div>
                            </div>
                            <div>
                                <div class="rappro-label">Écart de caisse</div>
                                <div class="rappro-value" style="color: <?= abs((float) $jt['ecart']) < 0.01 ? '#15803d' : '#b91c1c' ?>;"><?= ($jt['ecart'] > 0 ? '+' : '') . $fmtXof($jt['ecart']) ?> XOF</div>
                            </div>
                            <div>
                                <div class="rappro-label">Consolidé par</div>
                                <div class="rappro-value"><?= View::e($jour['etat']['consolidateur_nom'] ?? 'En attente') ?></div>
                            </div>
                        </div>

                        <?php if (!empty($jour['etat']['explication_ecart'])): ?>
                            <div class="note-ecart">
                                <strong>Explication de l'écart déclarée :</strong> <?= View::e($jour['etat']['explication_ecart']) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($jour['etat']['justification_retard'])): ?>
                            <div class="note-ecart">
                                <strong>Soumission rétroactive — justification :</strong> <?= View::e($jour['etat']['justification_retard']) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                <?php endforeach; ?>
            </div>

        <?php endforeach; ?>

        <div class="signatures">
            <div class="sig-box">
                <div class="sig-title">Le Caissier / Chef d'Agence</div>
            </div>
            <div class="sig-box">
                <div class="sig-title">La Caissière Principale</div>
            </div>
            <div class="sig-box">
                <div class="sig-title">Visa Direction Générale</div>
            </div>
        </div>

    <?php endif; ?>

    <div class="footer-note">
        Document généré automatiquement par l'ERP La Belle Porte Transit le <?= View::e($editeLe) ?> par <?= View::e($editePar) ?>.
        Les montants sont exprimés en XOF sauf mention contraire. Les encaissements listés correspondent aux règlements
        effectivement passés en caisse à la date indiquée, y compris les recouvrements portant sur des factures antérieures.
    </div>

</body>
</html>
