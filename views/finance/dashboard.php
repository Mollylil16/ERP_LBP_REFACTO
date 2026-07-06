<?php

use App\Helpers\View;
use App\Helpers\Auth;
use App\View\Components\Ui;
use App\View\Components\Dashboard;

/** @var \App\Support\ViewBag $viewData */
$viewData ??= \App\Support\ViewBag::from(get_defined_vars());

ob_start();

$kpis = [
    [
        'label' => 'Total Facturé',
        'value' => number_format($stats['facture_xof'], 0, ',', ' ') . ' XOF',
        'meta' => number_format($stats['facture_eur'], 2, ',', ' ') . ' EUR',
        'tone' => 'primary',
        'href' => 'finance/factures'
    ],
    [
        'label' => 'Fonds Encaissés',
        'value' => number_format($stats['encaisse_xof'], 0, ',', ' ') . ' XOF',
        'meta' => number_format($stats['encaisse_eur'], 2, ',', ' ') . ' EUR',
        'tone' => 'success',
        'href' => 'finance/factures'
    ],
    [
        'label' => 'Reste à Recouvrer',
        'value' => number_format($stats['restant_xof'], 0, ',', ' ') . ' XOF',
        'meta' => number_format($stats['restant_eur'], 2, ',', ' ') . ' EUR',
        'tone' => 'warning',
        'href' => 'finance/factures'
    ],
    [
        'label' => 'Décaissements Prestataires',
        'value' => (string) $stats['pending_payouts'],
        'meta' => 'Demandes en attente',
        'tone' => 'danger',
        'href' => 'finance/depenses'
    ],
    [
        'label' => 'Clôtures Caisse d\'Agences',
        'value' => (string) $stats['pending_closures'],
        'meta' => 'Rapports à consolider',
        'tone' => 'info',
        'href' => 'finance/clotures'
    ]
];
?>
<div class="finea-shell module-dashboard-shell">
    <div class="finea-container">
        
        <!-- 1. Header Banner -->
        <?= Ui::pageHeader(
            'Finance & Trésorerie',
            'Tableau de bord financier de LBP Transit',
            [
                'class' => 'module-dashboard-hero',
                'eyebrow' => 'FINANCE • PILOTAGE GLOBAL',
                'actions' => '
                    <a href="' . View::url('finance/factures') . '" class="rh-filter-btn rh-filter-btn--primary" style="background:#fabd02; color:#172033; font-weight:750; margin-right: 0.5rem;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="rh-btn-icon"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        Nouvelle Facture
                    </a>
                    <a href="' . View::url('finance/depenses') . '" class="rh-filter-btn rh-filter-btn--primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="rh-btn-icon"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                        Nouvelle Dépense
                    </a>
                '
            ]
        ) ?>

        <!-- 2. Rich KPIs Grid -->
        <div style="margin-top: 2rem;">
            <?= Dashboard::kpis($kpis) ?>
        </div>

        <!-- 3. Tables Grid -->
        <div style="display: grid; grid-template-columns: 1fr; gap: 2rem; margin-top: 2rem;">
            
            <!-- Invoices Section -->
            <section class="finea-section-card">
                <div class="module-section-heading" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                    <div>
                        <p class="finea-eyebrow" style="color:#2563eb; margin:0;">FACTURES CLIENTS</p>
                        <h2 class="finea-section-title" style="margin:0; border:none; padding:0;">Factures récentes</h2>
                    </div>
                    <a href="<?= View::url('finance/factures') ?>" class="rh-priorities-link" style="color:#2563eb; font-weight:600; text-decoration:none;">Voir toutes les factures →</a>
                </div>

                <div class="finea-table-wrap">
                    <table class="finea-table">
                        <thead>
                            <tr>
                                <th>N° Facture</th>
                                <th>Date émission</th>
                                <th>Client</th>
                                <th style="text-align:right;">Montant Total</th>
                                <th style="text-align:right;">Montant Restant</th>
                                <th style="text-align:center;">Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recentFactures === []): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; color:#64748b; padding: 2rem 14px;">Aucune facture disponible.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentFactures as $f): ?>
                                    <?php
                                    $badgeTone = match($f['statut']) {
                                        'payee' => 'success',
                                        'partiellement_payee' => 'warning',
                                        'emise' => 'info',
                                        'en_retard' => 'danger',
                                        default => 'neutral'
                                    };
                                    $badge = Ui::badge(str_replace('_', ' ', ucfirst($f['statut'])), $badgeTone);
                                    ?>
                                    <tr>
                                        <td><strong><?= View::e($f['numero_facture']) ?></strong></td>
                                        <td><?= $f['date_emission'] ? date('d/m/Y', strtotime($f['date_emission'])) : '—' ?></td>
                                        <td><?= View::e($f['client_name'] ?: 'Client inconnu') ?></td>
                                        <td style="text-align:right; font-weight: 600;">
                                            <?= number_format((float)$f['montant_total'], 2, ',', ' ') ?> <?= View::e($f['devise']) ?>
                                        </td>
                                        <td style="text-align:right; color: #ea580c; font-weight: 600;">
                                            <?= number_format((float)$f['montant_restant'], 2, ',', ' ') ?> <?= View::e($f['devise']) ?>
                                        </td>
                                        <td style="text-align:center;"><?= $badge ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Grid for Ledger and daily closures -->
            <div style="display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 2rem;">
                
                <!-- Double Entry Accounting Ledger -->
                <section class="finea-section-card">
                    <div class="module-section-heading" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                        <div>
                            <p class="finea-eyebrow" style="color:#1e3a8a; margin:0;">GRAND LIVRE</p>
                            <h2 class="finea-section-title" style="margin:0; border:none; padding:0;">Écritures comptables récentes</h2>
                        </div>
                        <a href="<?= View::url('finance/comptabilite') ?>" class="rh-priorities-link" style="color:#1e3a8a; font-weight:600; text-decoration:none;">Consulter le grand livre →</a>
                    </div>

                    <div class="finea-table-wrap">
                        <table class="finea-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Libellé</th>
                                    <th>Débit</th>
                                    <th>Crédit</th>
                                    <th style="text-align:right;">Montant</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($recentEcritures === []): ?>
                                    <tr>
                                        <td colspan="5" style="text-align:center; color:#64748b; padding: 2rem 14px;">Aucune écriture comptable.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentEcritures as $e): ?>
                                        <tr>
                                            <td><?= date('d/m/Y', strtotime($e['date_ecriture'])) ?></td>
                                            <td><strong><?= View::e($e['libelle']) ?></strong></td>
                                            <td><span style="font-weight:600; color:#1e3a8a;"><?= View::e($e['compte_debit']) ?></span></td>
                                            <td><span style="font-weight:600; color:#b45309;"><?= View::e($e['compte_credit']) ?></span></td>
                                            <td style="text-align:right; font-weight:600;">
                                                <?= number_format((float)$e['montant'], 2, ',', ' ') ?> <?= View::e($e['devise']) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Daily cash closures -->
                <section class="finea-section-card">
                    <div class="module-section-heading" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                        <div>
                            <p class="finea-eyebrow" style="color:#b45309; margin:0;">POINTS DE CAISSE</p>
                            <h2 class="finea-section-title" style="margin:0; border:none; padding:0;">Clôtures récentes</h2>
                        </div>
                        <a href="<?= View::url('finance/clotures') ?>" class="rh-priorities-link" style="color:#b45309; font-weight:600; text-decoration:none;">Gérer →</a>
                    </div>

                    <div class="finea-table-wrap">
                        <table class="finea-table">
                            <thead>
                                <tr>
                                    <th>Agence / Date</th>
                                    <th style="text-align:right;">Solde Caisses</th>
                                    <th style="text-align:center;">Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($recentEtats === []): ?>
                                    <tr>
                                        <td colspan="3" style="text-align:center; color:#64748b; padding: 2rem 14px;">Aucune clôture disponible.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentEtats as $et): ?>
                                        <?php
                                        $badgeTone = match($et['statut']) {
                                            'consolide' => 'success',
                                            'soumis' => 'info',
                                            default => 'neutral'
                                        };
                                        $badge = Ui::badge(ucfirst($et['statut']), $badgeTone);
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?= View::e($et['agence_name']) ?></strong><br>
                                                <small style="color:#64748b;"><?= date('d/m/Y', strtotime($et['date_jour'])) ?></small>
                                            </td>
                                            <td style="text-align:right; font-weight:600;">
                                                <?= number_format((float)$et['solde_caisse_agence_xof'], 0, ',', ' ') ?> XOF<br>
                                                <small style="color:#64748b; font-size:0.75rem;"><?= number_format((float)$et['solde_caisse_agence_eur'], 2, ',', ' ') ?> EUR</small>
                                            </td>
                                            <td style="text-align:center; vertical-align:middle;"><?= $badge ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

            </div>

        </div>

    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
