<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use App\Helpers\Auth;
use App\Models\Finance\Facture;
use App\View\Components\Ui;
use App\View\Components\Form;

final class Finance
{
    public static function dashboardPage(\App\View\Pages\Finance\DashboardPage $page, array $dashboardModule): string
    {
        $style = '<style>'
            . '    .module-section-heading {'
            . '        display: flex;'
            . '        justify-content: space-between;'
            . '        align-items: flex-end;'
            . '        margin-bottom: 1rem;'
            . '    }'
            . '    .finea-eyebrow {'
            . '        font-size: 0.75rem;'
            . '        font-weight: 700;'
            . '        letter-spacing: 0.05em;'
            . '        margin-bottom: 0.25rem;'
            . '    }'
            . '</style>';

        $header = \App\View\Components\Dashboard::header(
            $dashboardModule['label'],
            "Vue d'ensemble des flux financiers, facturation et états de caisse.",
            [
                'eyebrow' => $dashboardModule['code'] . ' Dashboard',
                'class' => 'rh-hero-white'
            ]
        );

        $kpis = \App\View\Components\Dashboard::kpis($page->kpis);
        $recentFactures = self::recentFactures($page->recentFactures);
        $recentEcritures = self::recentEcritures($page->recentEcritures);
        $recentEtats = self::recentEtats($page->recentEtats);
        $actions = \App\View\Components\Dashboard::actions($page->quickActions, [
            'title' => 'Actions Financières',
            'class' => 'finea-section-card',
        ]);
        $trendWidget = self::encaissementsTrendWidget($page->trendData);

        return $style
            . '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . '<div class="rh-dashboard-grid" style="margin-top: 2rem;">'
            . '<div class="rh-dashboard-main">'
            . $kpis
            . '<div style="margin-top: 2rem;">'
            . $trendWidget
            . '</div>'
            . '<div style="margin-top: 2rem;">'
            . $recentFactures
            . '</div>'
            . '<div style="margin-top: 2rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">'
            . $recentEcritures
            . $recentEtats
            . '</div>'
            . '</div>'
            . '<div class="rh-dashboard-side">'
            . $actions
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';
    }

    public static function encaissementsTrendWidget(array $trendData = []): string
    {
        $labelsJson = json_encode(array_column($trendData, 'label'));
        $especesJson = json_encode(array_column($trendData, 'especes'));
        $mobileJson = json_encode(array_column($trendData, 'mobile'));

        $totalEspeces = array_sum(array_column($trendData, 'especes'));
        $totalMobile = array_sum(array_column($trendData, 'mobile'));

        return '
        <div class="finea-section-card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.5rem; box-shadow: 0 4px 16px rgba(15,23,42,0.04);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.2rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <span style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #2563eb; background: #eff6ff; padding: 4px 10px; border-radius: 20px;">Analyse d\'Affluence & Encaissements</span>
                    <h3 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin-top: 0.4rem; display: flex; align-items: center; gap: 8px;"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2.5"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg> Tendance des Encaissements (30 derniers jours)</h3>
                    <p style="color: #64748b; font-size: 0.88rem; margin-top: 0.2rem;">Comparatif visuel des flux Espèces vs Mobile Money pour repérer les jours de forte affluence.</p>
                </div>
                <div style="display: flex; gap: 1.5rem; align-items: center; background: #f8fafc; padding: 8px 16px; border-radius: 10px; border: 1px solid #e2e8f0;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="width: 12px; height: 12px; border-radius: 50%; background: #16a34a; display: inline-block;"></span>
                        <span style="font-size: 0.85rem; font-weight: 700; color: #1e293b;">Espèces : <strong>' . number_format($totalEspeces, 0, ',', ' ') . ' F</strong></span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="width: 12px; height: 12px; border-radius: 50%; background: #0284c7; display: inline-block;"></span>
                        <span style="font-size: 0.85rem; font-weight: 700; color: #1e293b;">Mobile Money & Banque : <strong>' . number_format($totalMobile, 0, ',', ' ') . ' F</strong></span>
                    </div>
                </div>
            </div>

            <div style="position: relative; height: 280px; width: 100%;">
                <canvas id="financeTrendChartCanvas"></canvas>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            const ctx = document.getElementById("financeTrendChartCanvas");
            if (!ctx) return;
            new Chart(ctx, {
                type: "bar",
                data: {
                    labels: ' . $labelsJson . ',
                    datasets: [
                        {
                            label: "Espèces (FCFA)",
                            data: ' . $especesJson . ',
                            backgroundColor: "#16a34a",
                            borderRadius: 6,
                            stack: "Stack 0"
                        },
                        {
                            label: "Mobile Money / Virement (FCFA)",
                            data: ' . $mobileJson . ',
                            backgroundColor: "#0284c7",
                            borderRadius: 6,
                            stack: "Stack 0"
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: "bottom" },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ": " + new Intl.NumberFormat("fr-FR").format(context.raw) + " FCFA";
                                }
                            }
                        }
                    },
                    scales: {
                        x: { grid: { display: false } },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return new Intl.NumberFormat("fr-FR").format(value) + " F";
                                }
                            }
                        }
                    }
                }
            });
        });
        </script>';
    }

    public static function recentFactures(array $rows): string
    {
        $html = '<section class="finea-section-card">'
            . '<div class="module-section-heading"><div>'
            . '<p class="finea-eyebrow" style="color:#2563eb;">FACTURES CLIENTS</p>'
            . '<h2 class="finea-section-title">Factures récentes</h2>'
            . '</div><a class="rh-priorities-link" href="' . View::url('finance/factures') . '" style="color:#2563eb;">Voir toutes les factures →</a></div>';

        if ($rows === []) {
            $html .= '<div class="finea-empty-state">Aucune facture disponible.</div>';
        } else {
            $html .= '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
                . '<th>N° Facture</th><th>Date émission</th><th>Client</th>'
                . '<th style="text-align:right;">Montant Total</th>'
                . '<th style="text-align:right;">Montant Restant</th>'
                . '<th style="text-align:center;">Statut</th></tr></thead><tbody>';
            foreach ($rows as $f) {
                $html .= '<tr>'
                    . '<td><strong>' . View::e($f['numero_facture']) . '</strong></td>'
                    . '<td>' . View::e($f['formatted_date']) . '</td>'
                    . '<td>' . View::e($f['client_name_display']) . '</td>'
                    . '<td style="text-align:right; font-weight: 600;">' . View::e($f['montant_total_formatted']) . '</td>'
                    . '<td style="text-align:right; color: #ea580c; font-weight: 600;">' . View::e($f['montant_restant_formatted']) . '</td>'
                    . '<td style="text-align:center;">' . Ui::badge($f['status_display'], $f['status_tone']) . '</td>'
                    . '</tr>';
            }
            $html .= '</tbody></table></div>';
        }
        return $html . '</section>';
    }

    public static function recentEcritures(array $rows): string
    {
        $html = '<section class="finea-section-card">'
            . '<div class="module-section-heading"><div>'
            . '<p class="finea-eyebrow" style="color:#1e3a8a;">GRAND LIVRE</p>'
            . '<h2 class="finea-section-title">Écritures comptables récentes</h2>'
            . '</div><a class="rh-priorities-link" href="' . View::url('finance/comptabilite') . '" style="color:#1e3a8a;">Consulter le grand livre →</a></div>';

        if ($rows === []) {
            $html .= '<div class="finea-empty-state">Aucune écriture comptable.</div>';
        } else {
            $html .= '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
                . '<th>Date</th><th>Libellé</th><th>Débit</th><th>Crédit</th><th style="text-align:right;">Montant</th></tr></thead><tbody>';
            foreach ($rows as $e) {
                $html .= '<tr>'
                    . '<td>' . View::e($e['formatted_date']) . '</td>'
                    . '<td><strong>' . View::e($e['libelle']) . '</strong></td>'
                    . '<td><span style="font-weight:600; color:#1e3a8a;">' . View::e($e['compte_debit']) . '</span></td>'
                    . '<td><span style="font-weight:600; color:#b45309;">' . View::e($e['compte_credit']) . '</span></td>'
                    . '<td style="text-align:right; font-weight:600;">' . View::e($e['montant_formatted']) . '</td>'
                    . '</tr>';
            }
            $html .= '</tbody></table></div>';
        }
        return $html . '</section>';
    }

    public static function recentEtats(array $rows): string
    {
        $html = '<section class="finea-section-card">'
            . '<div class="module-section-heading"><div>'
            . '<p class="finea-eyebrow" style="color:#b45309;">POINTS DE CAISSE</p>'
            . '<h2 class="finea-section-title">Clôtures récentes</h2>'
            . '</div><a class="rh-priorities-link" href="' . View::url('finance/clotures') . '" style="color:#b45309;">Gérer →</a></div>';

        if ($rows === []) {
            $html .= '<div class="finea-empty-state">Aucune clôture disponible.</div>';
        } else {
            $html .= '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
                . '<th>Agence / Date</th><th style="text-align:right;">Solde Caisses</th><th style="text-align:center;">Statut</th></tr></thead><tbody>';
            foreach ($rows as $et) {
                $html .= '<tr>'
                    . '<td><strong>' . View::e($et['agence_name']) . '</strong><br><small style="color:#64748b;">' . View::e($et['formatted_date']) . '</small></td>'
                    . '<td style="text-align:right; font-weight:600;">' . View::e($et['solde_xof_formatted']) . '<br><small style="color:#64748b; font-size:0.75rem;">' . View::e($et['solde_eur_formatted']) . '</small></td>'
                    . '<td style="text-align:center; vertical-align:middle;">' . Ui::badge($et['status_display'], $et['status_tone']) . '</td>'
                    . '</tr>';
            }
            $html .= '</tbody></table></div>';
        }
        return $html . '</section>';
    }

    /**
     * Rendu de la liste des factures.
     */
    public static function facturesTable(array $factures, array $filters = [], array $agences = [], array $pagination = [], array $categoryStats = []): string
    {
        $header = Ui::pageHeader(
            'Gestion de la Facturation',
            'Saisie et suivi des factures clients, relances et états de paiement.',
            [
                'eyebrow' => 'Invoicing & Receivables',
                'class' => 'rh-hero-white',
                'actions' => [
                    '<form method="post" action="' . View::url('finance/factures/relancer-tout') . '" style="display:inline;" onsubmit="return confirm(\'Lancer la relance SMS / WhatsApp automatique pour toutes les factures impayées ?\');">' . Ui::button('<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="margin-right:6px; vertical-align:-2px;"><path d="M22 2L11 13"></path><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg> Relancer tous les impayés', ['type' => 'submit', 'variant' => 'secondary']) . '</form>',
                    Ui::button('Créer une Facture', [
                        'href' => 'finance/factures/nouveau',
                        'variant' => 'accent',
                    ])
                ]
            ]
        );

        // Matrice analytique des 13 catégories (Codes payés vs non payés)
        $matrixHtml = '';
        if (!empty($categoryStats)) {
            $cards = '';
            foreach ($categoryStats as $cat => $st) {
                $taux = (float) $st['taux_recouvrement'];
                $barColor = $taux >= 80 ? '#16a34a' : ($taux >= 50 ? '#d97706' : '#dc2626');
                $isFiltered = (($filters['type_envoi'] ?? '') === $cat);
                $cardBorder = $isFiltered ? 'border:2px solid #2563eb; background:#eff6ff;' : 'border:1px solid #e2e8f0; background:#ffffff;';

                $urlCatNonPaye = View::url('finance/factures') . '?type_envoi=' . urlencode($cat) . '&statut=emise';

                $cards .= '<div style="' . $cardBorder . ' border-radius:12px; padding:1rem; box-shadow:0 2px 6px rgba(0,0,0,0.02); display:flex; flex-direction:column; justify-content:space-between;">'
                    . '<div>'
                    . '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">'
                    . '<strong style="font-size:1rem; color:#0f172a; font-family:monospace;">' . View::e($cat) . '</strong>'
                    . '<span style="font-size:0.75rem; font-weight:700; color:#64748b;">' . $st['total_count'] . ' factures</span>'
                    . '</div>'
                    . '<div style="font-size:0.82rem; margin-bottom:0.3rem;">'
                    . '<span style="color:#16a34a; font-weight:700;">Payé: ' . number_format($st['montant_paye'], 0, ',', ' ') . ' XOF</span> '
                    . '<small style="color:#64748b;">(' . $st['count_paye'] . ')</small>'
                    . '</div>'
                    . '<div style="font-size:0.82rem; margin-bottom:0.6rem;">'
                    . '<span style="color:#dc2626; font-weight:700;">Non Payé: ' . number_format($st['montant_non_paye'], 0, ',', ' ') . ' XOF</span> '
                    . '<small style="color:#64748b;">(' . $st['count_non_paye'] . ')</small>'
                    . '</div>'
                    . '</div>'
                    . '<div>'
                    . '<div style="background:#e2e8f0; border-radius:4px; height:6px; overflow:hidden; margin-bottom:0.4rem;">'
                    . '<div style="background:' . $barColor . '; width:' . min(100, $taux) . '%; height:100%;"></div>'
                    . '</div>'
                    . '<div style="display:flex; justify-content:space-between; align-items:center; font-size:0.75rem;">'
                    . '<span style="font-weight:800; color:' . $barColor . ';">' . $taux . '% recouvré</span>'
                    . '<a href="' . $urlCatNonPaye . '" style="color:#2563eb; font-weight:700; text-decoration:none;">Non Payés »</a>'
                    . '</div>'
                    . '</div>'
                    . '</div>';
            }

            $exportCsvBtn = '<a href="' . View::url('finance/factures/export-categories-csv') . '" style="padding:0.45rem 0.95rem; background:#0f172a; color:#fff; font-weight:700; border-radius:8px; text-decoration:none; font-size:0.82rem; display:inline-flex; align-items:center; gap:6px;" target="_blank">'
                . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg> Exporter Synthèse Catégories (CSV)'
                . '</a>';

            $exportPdfBtn = '<a href="' . View::url('finance/factures/export-categories-pdf') . '" style="padding:0.45rem 0.95rem; background:#2563eb; color:#fff; font-weight:700; border-radius:8px; text-decoration:none; font-size:0.82rem; display:inline-flex; align-items:center; gap:6px;" target="_blank">'
                . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg> Exporter Synthèse Catégories (PDF)'
                . '</a>';

            $chartIcon = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#2563eb" stroke-width="2.5" style="display:inline; margin-right:8px; vertical-align:-3px;"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>';

            $matrixHtml = '<div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:14px; padding:1.25rem; margin-bottom:1.5rem; box-shadow:0 4px 12px rgba(15,23,42,0.03);">'
                . '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:0.5rem;">'
                . '<div><h3 style="margin:0; font-weight:800; color:#0f172a; font-size:1.1rem;">' . $chartIcon . 'Synthèse & Recouvrement par Catégorie de Code (13 Codes)</h3><small style="color:#64748b;">Suivi des factures payées vs créances non payées par type d\'envoi.</small></div>'
                . '<div style="display:flex; gap:0.5rem;">' . $exportCsvBtn . $exportPdfBtn . '</div>'
                . '</div>'
                . '<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap:1rem;">'
                . $cards
                . '</div>'
                . '</div>';
        }

        // Formulaire de filtre
        $q = Form::input('q', [
            'label' => 'Recherche (N° Facture / Code Colis / Client)',
            'value' => (string) ($filters['q'] ?? ''),
            'placeholder' => 'ex: LB-CI-020, FA-3403-2026-000019, Katy...',
        ]);

        $statusOpts = [
            ['value' => '', 'label' => 'Tous les statuts'],
            ['value' => 'emise', 'label' => 'Émise'],
            ['value' => 'partiellement_payee', 'label' => 'Partiellement Payée'],
            ['value' => 'payee', 'label' => 'Payée'],
            ['value' => 'en_retard', 'label' => 'En Retard'],
            ['value' => 'annulee', 'label' => 'Annulée'],
        ];
        $status = Form::selectSearch('statut', $statusOpts, $filters['statut'] ?? '', ['label' => 'Statut']);

        $typeOpts = [
            ['value' => '', 'label' => 'Toutes les catégories d\'envoi'],
            ['value' => 'LB-CI', 'label' => 'LB-CI — Maritime Express CIV'],
            ['value' => 'LB-FR', 'label' => 'LB-FR — France Express'],
            ['value' => 'S-FR', 'label' => 'S-FR — Sénégal à France'],
            ['value' => 'S-CI', 'label' => 'S-CI — Sénégal à Côte d\'Ivoire'],
            ['value' => 'LB-CA', 'label' => 'LB-CA — Canada Express'],
            ['value' => 'F-SN', 'label' => 'F-SN — Fret Sénégal'],
            ['value' => 'DHL', 'label' => 'DHL — Partenaire DHL Express'],
            ['value' => 'CA-CI', 'label' => 'CA-CI — Cargo Aérien CIV'],
            ['value' => 'CA-FR', 'label' => 'CA-FR — Cargo Aérien France'],
            ['value' => 'CA-SN', 'label' => 'CA-SN — Cargo Aérien Sénégal'],
            ['value' => 'CA-IS', 'label' => 'CA-IS — Cargo Aérien Italie / Suisse'],
            ['value' => 'CA-IC', 'label' => 'CA-IC — Cargo Aérien Inter-Capitales'],
            ['value' => 'CA-CC', 'label' => 'CA-CC — Cargo Aérien Commercial (Chyna)'],
        ];
        $typeEnvoiSelect = Form::selectSearch('type_envoi', $typeOpts, $filters['type_envoi'] ?? '', ['label' => 'Catégorie de Code']);

        $agenceOpts = [['value' => 'all', 'label' => 'Toutes les agences']];
        foreach ($agences as $a) {
            $agenceOpts[] = ['value' => (string) $a['id'], 'label' => $a['name']];
        }
        $agenceSelect = Form::selectSearch('agence_id', $agenceOpts, $filters['agence_id'] ?? '', ['label' => 'Agence']);

        $filterGrid = '<div class="rh-personnel-filter-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem;">' . $q . $status . $typeEnvoiSelect . $agenceSelect . '</div>';

        $searchBtn = '<button type="submit" class="rh-filter-btn rh-filter-btn--primary">'
            . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="rh-btn-icon"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>'
            . 'Filtrer'
            . '</button>';

        $resetBtn = '<a href="' . View::url('finance/factures') . '" class="rh-filter-btn rh-filter-btn--reset">'
            . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" class="rh-btn-icon"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"></path></svg>'
            . 'Réinitialiser'
            . '</a>';

        $filterActions = '<div class="rh-personnel-filter-actions">' . $searchBtn . $resetBtn . '</div>';

        // Barre de filtres rapides par catégorie de code (LB-CI, LB-FR, S-FR, S-CI, LB-CA, F-SN, DHL, CA-CI, CA-FR, CA-SN, CA-IS, CA-IC, CA-CC)
        $categoriesList = ['LB-CI', 'LB-FR', 'S-FR', 'S-CI', 'LB-CA', 'F-SN', 'DHL', 'CA-CI', 'CA-FR', 'CA-SN', 'CA-IS', 'CA-IC', 'CA-CC'];
        $catPills = '<div style="margin-top:1rem; padding-top:0.75rem; border-top:1px dashed #cbd5e1; display:flex; align-items:center; gap:6px; flex-wrap:wrap;">'
            . '<span style="font-size:0.8rem; font-weight:700; color:#475569; margin-right:4px;">Catégories :</span>';
        foreach ($categoriesList as $catCode) {
            $activeStyle = (($filters['type_envoi'] ?? '') === $catCode) ? 'background:#2563eb; color:#ffffff; font-weight:800; border-color:#2563eb;' : 'background:#f1f5f9; color:#334155; border-color:#cbd5e1;';
            $urlCat = View::url('finance/factures') . '?type_envoi=' . urlencode($catCode) . (isset($filters['statut']) && $filters['statut'] !== '' ? '&statut=' . urlencode($filters['statut']) : '');
            $catPills .= '<a href="' . $urlCat . '" style="padding:3px 10px; border-radius:20px; font-size:0.78rem; text-decoration:none; border:1px solid; transition:all 0.15s; ' . $activeStyle . '">' . $catCode . '</a>';
        }
        $catPills .= '</div>';

        $form = '<form method="get" action="' . View::url('finance/factures') . '" class="rh-personnel-filters">' . $filterGrid . $filterActions . $catPills . '</form>';

        // Tableau
        $tableHtml = '';
        if ($factures === []) {
            $tableHtml = Ui::emptyState(
                'Aucune facture trouvée',
                'Aucune fiche ne correspond aux critères sélectionnés.'
            );
        } else {
            $rows = '';
            foreach ($factures as $f) {
                $badgeTone = match($f->statut) {
                    'payee' => 'success',
                    'partiellement_payee' => 'warning',
                    'emise' => 'info',
                    'en_retard' => 'danger',
                    default => 'secondary'
                };
                $badge = Ui::badge(str_replace('_', ' ', ucfirst($f->statut)), $badgeTone);

                $actionsStr = Ui::button('Consulter / Encaisser', [
                    'href' => 'finance/factures/' . $f->id,
                    'variant' => 'secondary',
                    'class' => 'finea-button-sm'
                ]);

                $canEditInvoice = (\App\Helpers\Auth::isAdmin() || \App\Helpers\Auth::hasRole('dg')) && !\App\Helpers\Auth::isAssistantDg();
                if ($canEditInvoice) {
                    $actionsStr .= ' <a href="' . View::url('facturation/factures/' . $f->id . '/modifier') . '" class="finea-button finea-button-sm" style="display:inline-flex; align-items:center; gap:4px; background:#2563eb; color:#ffffff; border:none; padding:6px 12px; border-radius:6px; font-weight:600; text-decoration:none; font-size:0.8rem;" title="Modifier cette facture"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg> Modifier</a>';
                }

                if ((\App\Helpers\Auth::hasAnyRole(['dg', 'admin', 'chef_agence', 'caissiere_principale', 'superviseur_general']) || \App\Helpers\Auth::isAdmin()) && !\App\Helpers\Auth::isAssistantDg()) {
                    $actionsStr .= ' <form method="post" action="' . View::url('finance/factures/' . $f->id . '/supprimer') . '" style="display:inline;" onsubmit="return confirm(\'Êtes-vous sûr de vouloir supprimer cette facture ?\');">'
                        . Form::hidden('_csrf_token', \App\Helpers\Csrf::token())
                        . '<button type="submit" class="finea-button finea-button--danger finea-button-sm" style="margin-left:0.3rem;">Supprimer</button>'
                        . '</form>';
                }

                $tauxStr = $f->tauxChange !== null && $f->devise !== 'XOF' ? ' <small style="color:#64748b;">(Taux: ' . number_format($f->tauxChange, 2, ',', '.') . ')</small>' : '';

                $rows .= '<tr>'
                    . '<td><strong>' . View::e($f->numeroFacture) . '</strong></td>'
                    . '<td>' . View::e($f->colis_tracking ?? 'Colis ID: ' . $f->colisId) . '</td>'
                    . '<td>' . View::e($f->client_name ?? 'Client ID: ' . $f->clientId) . '</td>'
                    . '<td style="text-align:right; font-weight:600;">' . View::e(number_format($f->montantTotal, 2, ',', ' ')) . ' ' . View::e($f->devise) . $tauxStr . '</td>'
                    . '<td style="text-align:right; color:#15803d;">' . View::e(number_format($f->montantEncaisse, 2, ',', ' ')) . ' ' . View::e($f->devise) . '</td>'
                    . '<td style="text-align:right; color:#b91c1c; font-weight:600;">' . View::e(number_format($f->montantRestant, 2, ',', ' ')) . ' ' . View::e($f->devise) . '</td>'
                    . '<td>' . $badge . '</td>'
                    . '<td>' . $actionsStr . '</td>'
                    . '</tr>';
            }

            $paginationHtml = '';
            if (!empty($pagination) && ($pagination['totalPages'] ?? 1) > 1) {
                $currentPage = (int) ($pagination['currentPage'] ?? 1);
                $totalPages = (int) ($pagination['totalPages'] ?? 1);
                $totalItems = (int) ($pagination['totalItems'] ?? 0);

                $queryParams = $_GET;

                $prevBtn = '';
                if ($currentPage > 1) {
                    $queryParams['page'] = $currentPage - 1;
                    $prevBtn = '<a href="' . View::url('finance/factures?' . http_build_query($queryParams)) . '" class="finea-button finea-button--secondary finea-button-sm">« Précédent</a>';
                }

                $nextBtn = '';
                if ($currentPage < $totalPages) {
                    $queryParams['page'] = $currentPage + 1;
                    $nextBtn = '<a href="' . View::url('finance/factures?' . http_build_query($queryParams)) . '" class="finea-button finea-button--secondary finea-button-sm">Suivant »</a>';
                }

                $paginationHtml = '<div style="display:flex; justify-content:space-between; align-items:center; margin-top:1.25rem; padding-top:1rem; border-top:1px solid #e2e8f0; font-size:0.88rem; color:#64748b;">'
                    . '<div>Page <strong>' . $currentPage . '</strong> sur <strong>' . $totalPages . '</strong> (' . $totalItems . ' factures au total)</div>'
                    . '<div style="display:flex; gap:0.5rem;">' . $prevBtn . $nextBtn . '</div>'
                    . '</div>';
            }

            $tableHtml = '<div class="finea-table-wrapper">'
                . '<table class="finea-table">'
                . '<thead>'
                . '<tr>'
                . '<th>N° Facture</th>'
                . '<th>Colis</th>'
                . '<th>Client</th>'
                . '<th style="text-align:right;">Montant Total</th>'
                . '<th style="text-align:right;">Montant Encaissé</th>'
                . '<th style="text-align:right;">Reste à payer</th>'
                . '<th>Statut</th>'
                . '<th>Actions</th>'
                . '</tr>'
                . '</thead>'
                . '<tbody>' . $rows . '</tbody>'
                . '</table>'
                . '</div>'
                . $paginationHtml;
        }

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . $matrixHtml
            . $form
            . '<div class="finea-section-card" style="margin-top: 1.5rem;">'
            . $tableHtml
            . '</div>'
            . '</div></div>';
    }

    /**
     * Formulaire de création de facture.
     */
    public static function factureCreateForm(array $colisSansFacture): string
    {
        $header = Ui::pageHeader(
            'Créer une Facture',
            'Générer une nouvelle facture à partir d\'un colis réceptionné.',
            [
                'eyebrow' => 'Nouvelle Facture',
                'class' => 'rh-hero-white',
            ]
        );

        $colisOpts = [['value' => '', 'label' => '-- Sélectionner un colis à facturer --']];
        foreach ($colisSansFacture as $c) {
            $colisOpts[] = [
                'value' => (string) $c['id'],
                'label' => $c['numero_tracking'] . ' - ' . $c['expediteur_name'] . ' (' . number_format((float)$c['montant_total'], 2, ',', ' ') . ' ' . $c['devise'] . ')'
            ];
        }

        $colisSelect = Form::selectSearch('colis_id', $colisOpts, '', [
            'label' => 'Colis à facturer',
            'required' => true,
            'id' => 'colis_id_selector'
        ]);

        $currencyOpts = [
            ['value' => 'XOF', 'label' => 'Franc CFA (XOF)'],
            ['value' => 'EUR', 'label' => 'Euro (EUR)'],
        ];
        $currencySelect = Form::select('devise', $currencyOpts, 'XOF', [
            'label' => 'Devise de facturation',
            'required' => true,
            'id' => 'devise_selector'
        ]);

        $tauxChangeInput = Form::input('taux_change', [
            'label' => 'Taux de conversion (si devise étrangère)',
            'type' => 'number',
            'step' => '0.000001',
            'min' => '0.000001',
            'placeholder' => 'Ex: 655.957',
            'id' => 'taux_change_input'
        ]);

        $formContent = '<form method="post" action="' . View::url('finance/factures/enregistrer') . '" class="js-protect-form">'
            . '<div style="display:grid; grid-template-columns:1fr; gap:1.5rem;">'
            . $colisSelect
            . '<div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">'
            . $currencySelect . $tauxChangeInput
            . '</div>'
            . '</div>'
            . '<div style="margin-top:2.5rem; display:flex; gap:1rem; justify-content:flex-end;">'
            . Ui::button('Annuler', ['href' => 'finance/factures', 'variant' => 'secondary'])
            . Ui::button('Générer la Facture', ['type' => 'submit', 'variant' => 'accent'])
            . '</div>'
            . '</form>';

        $js = "<script>
            document.addEventListener('DOMContentLoaded', function() {
                const devSelect = document.getElementById('devise_selector');
                const tauxInput = document.getElementById('taux_change_input');
                
                function toggleTaux() {
                    if (devSelect.value === 'XOF') {
                        tauxInput.value = '';
                        tauxInput.disabled = true;
                        tauxInput.removeAttribute('required');
                    } else {
                        tauxInput.disabled = false;
                        tauxInput.setAttribute('required', 'required');
                        tauxInput.value = '655.957000';
                    }
                }
                
                if (devSelect) {
                    devSelect.addEventListener('change', toggleTaux);
                    toggleTaux();
                }
            });
        </script>";

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . Ui::section('Détails de la facturation', $formContent)
            . '</div></div>' . $js;
    }

    /**
     * Fiche d'une facture + encaissement.
     */
    public static function factureShowPage(Facture $facture, array $paiements, array $callbacks, array $colis, array $client, array $marchandises = [], float $clientWalletBalance = 0.0): string
    {
        $badgeTone = match($facture->statut) {
            'payee' => 'success',
            'partiellement_payee' => 'warning',
            'emise' => 'info',
            'en_retard' => 'danger',
            default => 'secondary'
        };
        $badge = Ui::badge(str_replace('_', ' ', ucfirst($facture->statut)), $badgeTone);

        // Boutons WhatsApp Directs (Avis initial & Relance échéance)
        $clientPhone = preg_replace('/[^0-9+]/', '', (string)($client['phone'] ?? ''));
        $waButton = '';
        if ($clientPhone !== '') {
            $waMsg1 = "Bonjour " . ($client['name'] ?? 'Client') . ",\n"
                . "Voici votre facture N° " . $facture->numeroFacture . " (LBP Logistics & Transit).\n"
                . "• Montant Total : " . number_format($facture->montantTotal, 0, ',', ' ') . " " . $facture->devise . "\n"
                . "• Déjà Encaissé : " . number_format($facture->montantEncaisse, 0, ',', ' ') . " " . $facture->devise . "\n"
                . "• Reste à Payer : " . number_format($facture->montantRestant, 0, ',', ' ') . " " . $facture->devise . "\n"
                . "Consultez et téléchargez votre reçu officiel PDF ici : " . View::url('finance/factures/' . $facture->id . '/recu-pdf');
            
            $waMsg2 = "⚠️ RAPPEL DE SOLDE — LBP Logistics & Transit\n"
                . "Bonjour " . ($client['name'] ?? 'Client') . ",\n"
                . "Nous vous rappelons que votre facture N° " . $facture->numeroFacture . " présente un solde restant dû de " . number_format($facture->montantRestant, 0, ',', ' ') . " " . $facture->devise . ".\n"
                . "Échéance : " . ($facture->dateEcheanceSolde ?? 'À réception') . ".\n"
                . "Merci d'effectuer le règlement afin d'éviter tout retard de livraison. Reçu PDF : " . View::url('finance/factures/' . $facture->id . '/recu-pdf');

            $waUrl1 = "https://api.whatsapp.com/send?phone=" . urlencode($clientPhone) . "&text=" . urlencode($waMsg1);
            $waUrl2 = "https://api.whatsapp.com/send?phone=" . urlencode($clientPhone) . "&text=" . urlencode($waMsg2);

            $waButton = '<a href="' . $waUrl1 . '" target="_blank" style="padding:0.55rem 1rem; background:#25D366; color:#ffffff; font-weight:800; border-radius:8px; text-decoration:none; display:inline-flex; align-items:center; gap:6px; font-size:0.82rem; border:none; cursor:pointer; box-shadow:0 2px 8px rgba(37,211,102,0.3);">'
                . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="vertical-align:-2px;"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg> WhatsApp Facture'
                . '</a> '
                . '<a href="' . $waUrl2 . '" target="_blank" style="padding:0.55rem 1rem; background:#075e54; color:#ffffff; font-weight:800; border-radius:8px; text-decoration:none; display:inline-flex; align-items:center; gap:6px; font-size:0.82rem; border:none; cursor:pointer; box-shadow:0 2px 8px rgba(7,94,84,0.3);">'
                . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="vertical-align:-2px;"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg> Relance WhatsApp'
                . '</a>';
        }

        $header = Ui::pageHeader(
            'Facture ' . $facture->numeroFacture,
            'Consultation, encaissement physique et suivi en temps réel.',
            [
                'eyebrow' => 'Détails Facture',
                'class' => 'rh-hero-white',
                'actions' => array_filter([
                    $badge,
                    $waButton,
                    Ui::button('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="margin-right:5px; vertical-align:-2px;"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg> Reçu Officiel (PDF)', ['href' => 'finance/factures/' . $facture->id . '/recu-pdf', 'variant' => 'primary', 'target' => '_blank']),
                    Ui::button('Retour', ['href' => 'finance/factures', 'variant' => 'secondary'])
                ])
            ]
        );

        // Bandeau de Statut de Paiement Très Lisible
        $statusBannerColor = match($facture->statut) {
            'payee' => '#10b981',
            'partiellement_payee' => '#f59e0b',
            'emise' => '#3b82f6',
            'en_retard' => '#ef4444',
            default => '#64748b'
        };

        $statusBannerBg = match($facture->statut) {
            'payee' => '#ecfdf5',
            'partiellement_payee' => '#fffbeb',
            'emise' => '#eff6ff',
            'en_retard' => '#fef2f2',
            default => '#f8fafc'
        };

        $statusMessage = match($facture->statut) {
            'payee' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5" style="vertical-align:-4px; margin-right:6px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg> FACTURE RÉGLÉE EN TOTALITÉ — Intégralité des montants encaissés (' . number_format($facture->montantTotal, 0, ',', ' ') . ' ' . $facture->devise . ').',
            'partiellement_payee' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2.5" style="vertical-align:-4px; margin-right:6px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg> PAIEMENT PARTIEL EFFECTUÉ — Déjà encaissé : ' . number_format($facture->montantEncaisse, 0, ',', ' ') . ' ' . $facture->devise . ' | Reste dû : ' . number_format($facture->montantRestant, 0, ',', ' ') . ' ' . $facture->devise,
            'en_retard' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.5" style="vertical-align:-4px; margin-right:6px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg> FACTURE EN RETARD — Le solde restant de ' . number_format($facture->montantRestant, 0, ',', ' ') . ' ' . $facture->devise . ' a dépassé sa date d\'échéance !',
            default => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2.5" style="vertical-align:-4px; margin-right:6px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg> FACTURE ÉMISE / IMPAYÉE — En attente d\'encaissement du solde total de ' . number_format($facture->montantRestant, 0, ',', ' ') . ' ' . $facture->devise . '.'
        };

        $statusBanner = '<div style="background:' . $statusBannerBg . '; border:2px solid ' . $statusBannerColor . '; border-radius:12px; padding:1.2rem 1.5rem; margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center;">'
            . '<div style="font-weight:800; font-size:1.05rem; color:#0f172a;">' . $statusMessage . '</div>'
            . '<div><strong style="font-size:1.3rem; color:' . $statusBannerColor . ';">' . number_format($facture->montantRestant, 0, ',', ' ') . ' ' . View::e($facture->devise) . '</strong><br><small style="color:#64748b;">Reste à payer</small></div>'
            . '</div>';

        // Colonne 1: Infos générales de la facture
        $tauxStr = $facture->tauxChange !== null && $facture->devise !== 'XOF' ? '<p><strong>Taux de change figé :</strong> 1 EUR = ' . number_format($facture->tauxChange, 4, ',', '.') . ' FCFA</p>' : '';
        $factureInfo = '<div style="display:grid; grid-template-columns: 1fr 1fr; gap: 2rem;">'
            . '<div>'
            . '<p><strong>N° Facture :</strong> ' . View::e($facture->numeroFacture) . '</p>'
            . '<p><strong>Date d\'émission :</strong> ' . View::e($facture->dateEmission) . '</p>'
            . '<p><strong>Date d\'échéance du solde :</strong> ' . View::e($facture->dateEcheanceSolde ?? 'Aucune échéance') . '</p>'
            . $tauxStr
            . '</div>'
            . '<div>'
            . '<p><strong>Montant Total :</strong> <span style="font-size:1.15rem; font-weight:700; color:#1e293b;">' . View::e(number_format($facture->montantTotal, 2, ',', ' ')) . ' ' . View::e($facture->devise) . '</span></p>'
            . '<p><strong>Montant Encaissé :</strong> <span style="font-size:1.1rem; font-weight:700; color:#16a34a;">' . View::e(number_format($facture->montantEncaisse, 2, ',', ' ')) . ' ' . View::e($facture->devise) . '</span></p>'
            . '<p><strong>Reste à payer (Solde) :</strong> <span style="font-size:1.25rem; font-weight:700; color:#dc2626;">' . View::e(number_format($facture->montantRestant, 2, ',', ' ')) . ' ' . View::e($facture->devise) . '</span></p>'
            . '</div>'
            . '</div>';

        // Infos colis
        $colisInfo = '<div style="display:grid; grid-template-columns: 1fr 1fr; gap: 2rem;">'
            . '<div>'
            . '<p><strong>Colis :</strong> ' . View::e($colis['numero_tracking'] ?? '—') . '</p>'
            . '<p><strong>Description :</strong> ' . View::e($colis['description'] ?? '—') . '</p>'
            . '<p><strong>Poids total :</strong> ' . View::e((string) ($colis['poids_total'] ?? 0)) . ' kg</p>'
            . '</div>'
            . '<div>'
            . '<p><strong>Client :</strong> ' . View::e($client['name'] ?? '—') . '</p>'
            . '<p><strong>Téléphone client :</strong> ' . View::e($client['phone'] ?? '—') . '</p>'
            . '<p><strong>Adresse client :</strong> ' . View::e($client['address'] ?? '—') . '</p>'
            . '</div>'
            . '</div>';

        // Tableau des Emballages & Marchandises
        $marchandisesHtml = '';
        if (!empty($marchandises)) {
            $mRows = '';
            foreach ($marchandises as $m) {
                $embName = !empty($m['emballage']) ? $m['emballage'] : 'Carton / Propre emballage';
                $qteEmb = (int) ($m['qte_emballage'] ?? 1);
                $prixEmb = (float) ($m['prix_emballage'] ?? 0.0);
                $totLigne = (float) ($m['total_ligne'] ?? 0.0);

                $mRows .= '<tr>'
                    . '<td><strong>' . View::e($m['produit_libelle'] ?? 'Marchandise') . '</strong></td>'
                    . '<td><span class="finea-tag">' . View::e($embName) . '</span></td>'
                    . '<td style="text-align:center;">' . View::e((string)$qteEmb) . '</td>'
                    . '<td style="text-align:right;">' . ($prixEmb > 0 ? number_format($prixEmb, 0, ',', ' ') . ' XOF' : 'Inclus (0 XOF)') . '</td>'
                    . '<td style="text-align:right; font-weight:700;">' . number_format($totLigne, 2, ',', ' ') . ' ' . View::e($facture->devise) . '</td>'
                    . '</tr>';
            }

            $marchandisesHtml = '<div class="finea-table-wrapper" style="margin-top:0.5rem;">'
                . '<table class="finea-table">'
                . '<thead><tr><th>Produit</th><th>Type d\'Emballage</th><th style="text-align:center;">Qté Emballage</th><th style="text-align:right;">Prix Emballage</th><th style="text-align:right;">Sous-Total Ligne</th></tr></thead>'
                . '<tbody>' . $mRows . '</tbody>'
                . '</table>'
                . '</div>';
        }

        // Liste des encaissements physiques/comptant
        $payRows = '';
        foreach ($paiements as $p) {
            $payRows .= '<tr>'
                . '<td>' . View::e($p->datePaiement) . '</td>'
                . '<td>' . View::e(strtoupper($p->mode)) . '</td>'
                . '<td>' . View::e(ucfirst($p->type)) . '</td>'
                . '<td style="text-align:right; font-weight:600;">' . View::e(number_format($p->montant, 2, ',', ' ')) . ' ' . View::e($p->devise) . '</td>'
                . '<td>' . Ui::button('Imprimer Reçu', ['href' => 'finance/paiements/' . $p->id . '/recu', 'variant' => 'secondary', 'class' => 'finea-button-sm']) . '</td>'
                . '</tr>';
        }
        $payTable = '<table class="finea-table" style="margin-top:0.5rem;">'
            . '<thead><tr><th>Date</th><th>Mode</th><th>Type</th><th style="text-align:right;">Montant</th><th>Actions</th></tr></thead>'
            . '<tbody>' . ($payRows ?: '<tr><td colspan="5">Aucun encaissement enregistré.</td></tr>') . '</tbody>'
            . '</table>';

        // Liste des callbacks Mobile Money
        $callbackRows = '';
        foreach ($callbacks as $cb) {
            $statusBadge = match($cb->statut) {
                'success' => 'success',
                'failed' => 'danger',
                'unmatched' => 'warning',
                default => 'secondary'
            };
            $callbackRows .= '<tr>'
                . '<td>' . View::e($cb->createdAt) . '</td>'
                . '<td>' . View::e(strtoupper($cb->provider)) . '</td>'
                . '<td><code>' . View::e($cb->transactionReference) . '</code></td>'
                . '<td style="text-align:right; font-weight:600;">' . View::e(number_format($cb->montant, 2, ',', ' ')) . ' ' . View::e($cb->devise) . '</td>'
                . '<td>' . Ui::badge(strtoupper($cb->statut), $statusBadge) . '</td>'
                . '</tr>';
        }
        $callbackTable = '<table class="finea-table" style="margin-top:0.5rem; font-size:0.85rem;">'
            . '<thead><tr><th>Horodatage</th><th>Opérateur</th><th>ID Transaction</th><th style="text-align:right;">Montant</th><th>État</th></tr></thead>'
            . '<tbody>' . ($callbackRows ?: '<tr><td colspan="5">Aucun callback reçu.</td></tr>') . '</tbody>'
            . '</table>';

        // Formulaire d'encaissement physique (si la facture n'est pas entièrement payée ou annulée)
        $encaissementForm = '';
        if ($facture->statut !== 'payee' && $facture->statut !== 'annulee') {
            $modeOpts = [
                ['value' => 'especes', 'label' => 'Espèces (Physique)'],
                ['value' => 'mobile_money', 'label' => 'Mobile Money (Wave, Orange, MTN)'],
                ['value' => 'carte', 'label' => 'Carte Bancaire'],
            ];

            $formFields = '<div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem;">'
                . Form::input('montant', [
                    'label' => 'Montant à encaisser (' . $facture->devise . ')',
                    'type' => 'number',
                    'step' => '0.01',
                    'max' => $facture->montantRestant,
                    'value' => $facture->montantRestant,
                    'required' => true
                ])
                . Form::select('mode', $modeOpts, 'especes', ['label' => 'Mode d\'encaissement', 'required' => true])
                . Form::input('date_echeance_solde', [
                    'label' => 'Nouvelle échéance de solde (si partiel)',
                    'type' => 'date',
                    'value' => $facture->dateEcheanceSolde ? date('Y-m-d', strtotime($facture->dateEcheanceSolde)) : ''
                ])
                . '</div>';

            $encaissementForm = '<form method="post" action="' . View::url('finance/factures/' . $facture->id . '/encaisser') . '" class="js-protect-form" style="margin-top:2rem;">'
                . '<h3>Enregistrer un Encaissement Physique</h3>'
                . '<p style="font-size:0.85rem; color:#64748b; margin-bottom:1rem;">Remplir ce formulaire si le client règle directement au guichet.</p>'
                . $formFields
                . '<div style="margin-top: 1rem; display:flex; justify-content:flex-end;">'
                . Ui::button('Valider l\'encaissement', ['type' => 'submit', 'variant' => 'accent'])
                . '</div>'
                . '</form>';
        }

        // Section QR Code & Lien de Relance
        $qrCodeSection = '';
        if ($facture->statut !== 'payee' && $facture->statut !== 'annulee') {
            $qrUrl = View::url('api/paiements/qrcode/' . $facture->id);
            $qrCodeSection = '<div style="display:grid; grid-template-columns: 1fr 2fr; gap:2rem; margin-top:2rem;">'
                . '<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:1.5rem; display:flex; flex-direction:column; align-items:center; justify-content:center;">'
                . '<h4 style="margin-bottom:0.5rem; text-align:center;">QR Code de Paiement</h4>'
                . '<div style="background:#fff; padding:0.75rem; border-radius:8px; border:1px solid #cbd5e1; margin-bottom:0.75rem;">'
                // Image factice/générée par l'API de QR code
                . '<img src="' . $qrUrl . '" alt="QR Code" style="width:140px; height:140px;">'
                . '</div>'
                . '<small style="color:#64748b; text-align:center; font-size:0.75rem;">Valable jusqu\'au ' . ($facture->dateExpirationQr ? date('d/m/Y à H:i', strtotime($facture->dateExpirationQr)) : '—') . '</small>'
                . '</div>'
                . '<div>'
                . '<h4>Rappels & Relances automatiques</h4>'
                . '<p style="color:#475569; font-size:0.85rem; margin-bottom:1rem;">Envoyer un lien de paiement dynamique et un avis de relance par SMS/WhatsApp au client pour régler le solde restant.</p>'
                . '<form method="post" action="' . View::url('finance/factures/' . $facture->id . '/relancer') . '" class="js-protect-form">'
                . '<div style="display:flex; gap:0.5rem; align-items:center;">'
                . Form::select('canal', [
                    ['value' => 'sms', 'label' => 'SMS Pro'],
                    ['value' => 'whatsapp', 'label' => 'WhatsApp Business'],
                    ['value' => 'email', 'label' => 'Courriel (Email)'],
                ], 'whatsapp', ['required' => true])
                . Ui::button('Envoyer le Rappel de Solde', ['type' => 'submit', 'variant' => 'primary'])
                . '</div>'
                . '</form>'
                . '</div>'
                . '</div>';
        }

        // Formulaire 1-clic de règlement par Portefeuille Client (si créditeur)
        $walletPayForm = '';
        if ($clientWalletBalance > 0 && $facture->montantRestant > 0 && $facture->statut !== 'payee') {
            $walletPayForm = '<form method="post" action="' . View::url('finance/factures/' . $facture->id . '/payer-portefeuille') . '" class="js-protect-form" style="background:#ecfdf5; border:2px solid #10b981; padding:1.25rem 1.5rem; border-radius:12px; margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center; box-shadow:0 4px 12px rgba(16,185,129,0.1);">'
                . '<div style="display:flex; align-items:center; gap:0.75rem;">'
                . '<div style="width:42px; height:42px; border-radius:10px; background:rgba(16,185,129,0.15); display:flex; align-items:center; justify-content:center; flex-shrink:0;">'
                . '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>'
                . '</div>'
                . '<div>'
                . '<strong style="color:#065f46; font-size:1.05rem;">Solde Créditeur Disponible dans le Portefeuille Client !</strong><br>'
                . '<span style="color:#047857; font-size:0.88rem;">Le client possède <strong>' . number_format($clientWalletBalance, 0, ',', ' ') . ' XOF</strong> dans son portefeuille. Cliquez pour imputer automatiquement le solde.</span>'
                . '</div>'
                . '</div>'
                . Ui::button('<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="margin-right:5px; vertical-align:-2px;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg> Régler le solde via Portefeuille Client', ['type' => 'submit', 'variant' => 'success'])
                . '</form>';
        }

        // Frise chronologique (Timeline) des paiements
        $timelineSteps = '<div style="display:flex; align-items:flex-start; gap:12px; position:relative; padding-bottom:1.25rem; border-left:3px solid #2563eb; padding-left:18px; margin-left:10px;">'
            . '<div style="position:absolute; left:-9px; top:2px; width:15px; height:15px; background:#2563eb; border-radius:50%;"></div>'
            . '<div><strong style="color:#0f172a; font-size:0.95rem;">Émission de la Facture N° ' . View::e($facture->numeroFacture) . '</strong> <span style="color:#64748b; font-size:0.8rem;">(' . View::e($facture->dateEmission) . ')</span><br>'
            . '<small style="color:#475569;">Montant initial de la facture : <strong>' . number_format($facture->montantTotal, 0, ',', ' ') . ' ' . View::e($facture->devise) . '</strong></small></div>'
            . '</div>';

        $cumulPaiements = 0.0;
        if (!empty($paiements)) {
            foreach ($paiements as $idx => $p) {
                $cumulPaiements += $p->montant;
                $resteApres = max(0.0, $facture->montantTotal - $cumulPaiements);
                $stepColor = ($resteApres <= 0.01) ? '#10b981' : '#f59e0b';

                $timelineSteps .= '<div style="display:flex; align-items:flex-start; gap:12px; position:relative; padding-bottom:1.25rem; border-left:3px solid ' . $stepColor . '; padding-left:18px; margin-left:10px;">'
                    . '<div style="position:absolute; left:-9px; top:2px; width:15px; height:15px; background:' . $stepColor . '; border-radius:50%;"></div>'
                    . '<div><strong style="color:#0f172a; font-size:0.95rem;">Acompte ' . ($idx + 1) . ' — Encaissement de ' . number_format($p->montant, 0, ',', ' ') . ' ' . View::e($p->devise) . '</strong> <span style="color:#64748b; font-size:0.8rem;">(' . View::e($p->datePaiement) . ')</span><br>'
                    . '<small style="color:#475569;">Canal : <strong>' . View::e(strtoupper($p->mode)) . '</strong> | Solde restant dû : <strong style="color:' . ($resteApres <= 0.01 ? '#10b981' : '#dc2626') . ';">' . number_format($resteApres, 0, ',', ' ') . ' ' . View::e($facture->devise) . '</strong></small></div>'
                    . '</div>';
            }
        }

        if ($facture->statut === 'payee') {
            $timelineSteps .= '<div style="display:flex; align-items:flex-start; gap:12px; position:relative; padding-left:18px; margin-left:10px;">'
                . '<div style="position:absolute; left:-9px; top:2px; width:15px; height:15px; background:#10b981; border-radius:50%;"></div>'
                . '<div><strong style="color:#065f46; font-size:0.95rem;">🎉 Facture Entièrement Soldée</strong><br><small style="color:#047857;">Aucun reliquat impayé sur cette facture.</small></div>'
                . '</div>';
        }

        $timelineHtml = '<div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:1.5rem; margin-top:0.5rem; box-shadow:0 2px 8px rgba(0,0,0,0.02);">' . $timelineSteps . '</div>';

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . $statusBanner
            . $walletPayForm
            . '<div style="display:grid; grid-template-columns:1fr; gap:1.5rem;">'
            . Ui::section('Informations Financières', $factureInfo)
            . Ui::section('Colis & Client Associés', $colisInfo)
            . ($marchandisesHtml !== '' ? Ui::section('Types d\'Emballages & Marchandises Facturées', $marchandisesHtml) : '')
            . Ui::section('Frise Chronologique des Paiements', $timelineHtml)
            . $qrCodeSection
            . Ui::section('Historique des Encaissements Physiques', $payTable)
            . Ui::section('Transactions et Webhooks Mobile Money', $callbackTable)
            . $encaissementForm
            . '</div>'
            . '</div></div>';
    }

    /**
     * Rendu des demandes de dépenses prestataires (Séparation des tâches).
     */
    public static function demandesPaiementPage(array $demandes, array $prestataires): string
    {
        $header = Ui::pageHeader(
            'Dépenses Prestataires',
            'Demandes de règlements de prestataires régionaux et décaissements de caisse centrale.',
            [
                'eyebrow' => 'Supplier Payouts & Expenditures',
                'class' => 'rh-hero-white',
            ]
        );

        // Section création de demande (Superviseur Régional)
        $formCreate = '';
        if (Auth::hasAnyRole(['superviseur_regional', 'superviseur_general'])) {
            $prestOpts = [['value' => '', 'label' => '-- Sélectionner le prestataire --']];
            foreach ($prestataires as $p) {
                $prestOpts[] = ['value' => (string) $p['id'], 'label' => $p['name'] . ' (' . $p['type'] . ')'];
            }

            $formCreate = '<form method="post" action="' . View::url('finance/depenses/enregistrer') . '" class="js-protect-form" style="margin-bottom:2rem;">'
                . '<h3>Nouvelle Demande de Règlement</h3>'
                . '<div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem; margin-top:0.75rem;">'
                . Form::select('prestataire_id', $prestOpts, '', ['label' => 'Prestataire', 'required' => true])
                . Form::input('montant', ['label' => 'Montant à payer', 'type' => 'number', 'step' => '0.01', 'required' => true])
                . Form::select('devise', [['value' => 'XOF', 'label' => 'XOF (Franc CFA)'], ['value' => 'EUR', 'label' => 'EUR (Euro)']], 'XOF', ['label' => 'Devise', 'required' => true])
                . '</div>'
                . '<div style="display:grid; grid-template-columns:2fr 1fr; gap:1rem; margin-top:1rem;">'
                . Form::input('motif', ['label' => 'Motif détaillé de la dépense', 'required' => true])
                . Form::input('justificatif_url', ['label' => 'Lien de la facture justificative'])
                . '</div>'
                . '<div style="margin-top:1rem; display:flex; justify-content:flex-end;">'
                . Ui::button('Soumettre la demande', ['type' => 'submit', 'variant' => 'accent'])
                . '</div>'
                . '</form>';
        }

        // Calcul des KPI de dépenses
        $totalPaye = 0.0;
        $totalAttente = 0.0;
        $totalRejete = 0.0;
        $nbAttente = 0;

        foreach ($demandes as $d) {
            $m = (float) $d->montant;
            if (in_array(strtolower($d->statut), ['payee', 'approuvee', 'validee'], true)) {
                $totalPaye += $m;
            } elseif (in_array(strtolower($d->statut), ['en_attente', 'brouillon'], true)) {
                $totalAttente += $m;
                $nbAttente++;
            } else {
                $totalRejete += $m;
            }
        }

        $kpisHtml = '<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:1.25rem; margin-bottom:1.75rem;">'
            . '<div style="background:#ffffff; border:1px solid #e2e8f0; border-left:5px solid #16a34a; border-radius:12px; padding:1.25rem;">'
            . '<div style="font-size:0.75rem; font-weight:800; color:#64748b; text-transform:uppercase;">Décaissements Régalés / Payés</div>'
            . '<div style="font-size:1.65rem; font-weight:900; color:#16a34a; margin-top:0.35rem;">' . number_format($totalPaye, 0, ',', ' ') . ' XOF</div>'
            . '</div>'
            . '<div style="background:#ffffff; border:1px solid #e2e8f0; border-left:5px solid #d97706; border-radius:12px; padding:1.25rem;">'
            . '<div style="font-size:0.75rem; font-weight:800; color:#64748b; text-transform:uppercase;">En Attente de Validation (' . $nbAttente . ')</div>'
            . '<div style="font-size:1.65rem; font-weight:900; color:#d97706; margin-top:0.35rem;">' . number_format($totalAttente, 0, ',', ' ') . ' XOF</div>'
            . '</div>'
            . '<div style="background:#ffffff; border:1px solid #e2e8f0; border-left:5px solid #dc2626; border-radius:12px; padding:1.25rem;">'
            . '<div style="font-size:0.75rem; font-weight:800; color:#64748b; text-transform:uppercase;">Demandes Rejetées</div>'
            . '<div style="font-size:1.65rem; font-weight:900; color:#dc2626; margin-top:0.35rem;">' . number_format($totalRejete, 0, ',', ' ') . ' XOF</div>'
            . '</div>'
            . '</div>';

        // Tableau des demandes de dépenses
        $tableHtml = '';
        if ($demandes === []) {
            $tableHtml = Ui::emptyState(
                'Aucune demande de paiement',
                'Aucun mouvement de décaissement enregistré.'
            );
        } else {
            $rows = '';
            foreach ($demandes as $d) {
                $badgeTone = match($d->statut) {
                    'payee', 'validee' => 'success',
                    'approuvee' => 'primary',
                    'rejetee' => 'danger',
                    default => 'warning'
                };
                $badge = Ui::badge(strtoupper($d->statut), $badgeTone);

                $justifLink = $d->justificatifUrl ? '<a href="' . View::e($d->justificatifUrl) . '" target="_blank" style="color:#0284c7; text-decoration:underline;">Visualiser</a>' : '<span style="color:#94a3b8;">Aucun</span>';

                // Gestion des actions (Séparation des tâches)
                $actionsHtml = '—';
                if ($d->statut === 'en_attente') {
                    if (Auth::hasRole('caissiere_principale')) {
                        // On vérifie que la caissière principale n'est pas le superviseur qui a fait la demande !
                        if ($d->superviseurRegionalId !== Auth::id()) {
                            $actionsHtml = '<div style="display:flex; gap:0.5rem;">'
                                . '<form method="post" action="' . View::url('finance/depenses/' . $d->id . '/valider') . '" class="js-protect-form">'
                                . '<input type="hidden" name="decision" value="approuver">'
                                . Ui::button('Payer', ['type' => 'submit', 'variant' => 'success', 'class' => 'finea-button-sm'])
                                . '</form>'
                                . '<form method="post" action="' . View::url('finance/depenses/' . $d->id . '/valider') . '" class="js-protect-form">'
                                . '<input type="hidden" name="decision" value="rejeter">'
                                . Ui::button('Rejeter', ['type' => 'submit', 'variant' => 'danger', 'class' => 'finea-button-sm'])
                                . '</form>'
                                . '</div>';
                        } else {
                            $actionsHtml = '<span style="color:#e11d48; font-weight:600; font-size:0.8rem;">🚨 Blocage SoD (Auteur)</span>';
                        }
                    } else {
                        $actionsHtml = '<span style="color:#64748b; font-size:0.8rem;">En attente de caisse</span>';
                    }
                }

                $rows .= '<tr>'
                    . '<td>' . View::e($d->dateDemande) . '</td>'
                    . '<td>' . View::e($d->prestataire_name ?? 'Prestataire ID: ' . $d->prestataireId) . '</td>'
                    . '<td>' . View::e($d->motif) . '</td>'
                    . '<td style="text-align:right; font-weight:700; color:#b91c1c;">- ' . View::e(number_format($d->montant, 2, ',', ' ')) . ' ' . View::e($d->devise) . '</td>'
                    . '<td>' . $justifLink . '</td>'
                    . '<td>' . $badge . '</td>'
                    . '<td>' . $actionsHtml . '</td>'
                    . '</tr>';
            }

            $tfoot = '<tfoot style="background:#f8fafc; border-top:2px solid #cbd5e1; font-weight:800; color:#0f172a;">'
                . '<tr>'
                . '<td colspan="3" style="padding:10px 12px;">Total Général (' . count($demandes) . ' demandes)</td>'
                . '<td style="text-align:right; padding:10px 12px; color:#dc2626;">- ' . number_format($totalPaye + $totalAttente, 2, ',', ' ') . ' XOF</td>'
                . '<td colspan="3"></td>'
                . '</tr>'
                . '</tfoot>';

            $tableHtml = '<div class="finea-table-wrapper">'
                . '<table class="finea-table">'
                . '<thead>'
                . '<tr>'
                . '<th>Date</th>'
                . '<th>Prestataire</th>'
                . '<th>Motif</th>'
                . '<th style="text-align:right;">Montant</th>'
                . '<th>Justificatif</th>'
                . '<th>Statut</th>'
                . '<th>Actions (SoD)</th>'
                . '</tr>'
                . '</thead>'
                . '<tbody>' . $rows . '</tbody>'
                . $tfoot
                . '</table>'
                . '</div>';
        }

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . $kpisHtml
            . ($formCreate !== '' ? Ui::section('Créer une Demande de Paiement', $formCreate) : '')
            . '<div class="finea-section-card" style="margin-top: 1.5rem;">'
            . '<div class="finea-section-heading"><h2 class="finea-section-title">Décaissements et Règlements</h2></div>'
            . $tableHtml
            . '</div>'
            . '</div></div>';
    }

    /**
     * Point de caisse et états journaliers.
     */
    public static function etatsJournaliersPage(array $reports, array $agences, ?array $activeReport = null, int $selectedAgenceId = 0, array $filters = []): string
    {
        $header = Ui::pageHeader(
            'Points de Caisse & Suivi en Direct',
            'Consultation de la caisse en temps réel de chaque agence, soumission des états journaliers et consolidation.',
            [
                'eyebrow' => 'Daily Cash Closures & Live Monitoring',
                'class' => 'rh-hero-white',
            ]
        );

        $isGlobal = Auth::hasAnyRole(['caissiere_principale', 'dg', 'comptable', 'superviseur_general', 'superviseur_regional', 'admin']);

        // 1. Selector dropdown for global roles (caissière principale, DG, etc.)
        $agenceSelector = '';
        if ($isGlobal && !empty($agences)) {
            $globalPdfBtn = '<a href="' . View::url('finance/clotures/export-pdf-global') . '" target="_blank" style="padding:0.65rem 1.2rem; background:#0f172a; color:#fff; border-radius:8px; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:6px; font-size:0.85rem; whitespace:nowrap;">'
                . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg> Bilan Global Réseau (PDF)'
                . '</a>';

            $agenceSelector = '<div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:12px; padding:1.25rem 1.5rem; margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center; box-shadow:0 4px 12px rgba(15,23,42,0.03);">'
                . '<div style="display:flex; align-items:center; gap:0.75rem;">'
                . '<span style="background:#f1f5f9; padding:0.6rem; border-radius:8px; display:inline-flex; color:#0f172a;"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg></span>'
                . '<div><strong style="color:#0f172a; font-size:1.05rem;">Consulter la Caisse d\'une Agence en Temps Réel</strong><br><small style="color:#64748b;">Sélectionnez une agence pour visualiser ses encaissements et son solde en direct, même avant soumission.</small></div>'
                . '</div>'
                . '<div style="display:flex; align-items:center; gap:0.75rem;">'
                . '<select onchange="window.location.href=\'' . View::url('finance/clotures') . '?agence_id=\' + this.value" style="padding:0.65rem 1.2rem; border:2px solid #0f172a; border-radius:8px; font-weight:700; color:#0f172a; background:#fff; cursor:pointer; min-width:260px; font-size:0.95rem;">'
                . '<option value="0"' . ($selectedAgenceId === 0 ? ' selected' : '') . '>-- Toutes les agences (Historique global) --</option>';
            foreach ($agences as $ag) {
                $sel = $selectedAgenceId === (int) $ag['id'] ? ' selected' : '';
                $agenceSelector .= '<option value="' . $ag['id'] . '"' . $sel . '>Agence ' . View::e($ag['name']) . '</option>';
            }
            $agenceSelector .= '</select>'
                . $globalPdfBtn
                . '</div>'
                . '</div>';
        }

        // 2. Real-time Live Cash Box for active/selected agency
        $submissionForm = '';
        if ($activeReport) {
            $agenceTitle = View::e($activeReport['agence_name'] ?? 'Votre Agence');
            $statut = $activeReport['statut'] ?? 'brouillon';
            $nbColis = (int) ($activeReport['nbColisEnregistres'] ?? $activeReport['nb_colis'] ?? 0);
            $nbFactures = (int) ($activeReport['nbFacturesEmises'] ?? $activeReport['nb_factures'] ?? 0);
            $totalFactureXof = (float) ($activeReport['totalFactureXof'] ?? $activeReport['total_facture_xof'] ?? 0);
            $totalEncaisseXof = (float) ($activeReport['totalEncaisseXof'] ?? $activeReport['total_encaisse_xof'] ?? 0);
            $totalRestantXof = (float) ($activeReport['totalRestantDuXof'] ?? $activeReport['total_restant_du_xof'] ?? 0);

            $statutBadge = match($statut) {
                'consolide' => 'success',
                'soumis' => 'primary',
                default => 'warning'
            };

            $encEspeces = (float) ($activeReport['encaisseEspecesXof'] ?? $activeReport['encaisse_especes_xof'] ?? 0);
            $encDigital = (float) ($activeReport['encaisseDigitalXof'] ?? $activeReport['encaisse_digital_xof'] ?? 0);
            $encCheque = (float) ($activeReport['encaisseChequeXof'] ?? $activeReport['encaisse_cheque_xof'] ?? 0);

            // Alerte Clôture Tardive après 15h00
            $lateAlert = '';
            if ((int)date('H') >= 15 && $statut === 'brouillon') {
                $lateAlert = '<div style="background:#fef2f2; border:2px solid #ef4444; border-radius:12px; padding:1.2rem 1.5rem; margin-bottom:1.5rem; display:flex; align-items:center; gap:1rem; box-shadow:0 4px 12px rgba(239,68,68,0.12);">'
                    . '<div style="width:48px; height:48px; border-radius:12px; background:rgba(239,68,68,0.15); display:flex; align-items:center; justify-content:center; flex-shrink:0;"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg></div>'
                    . '<div>'
                    . '<strong style="color:#991b1b; font-size:1.1rem;">ALERTE CLÔTURE TARDIVE (Post 15h00)</strong><br>'
                    . '<span style="color:#b91c1c; font-size:0.88rem;">Il est ' . date('H:i') . '. Le point de caisse du jour pour <strong>' . $agenceTitle . '</strong> n\'a pas encore été soumis. Veuillez procéder immédiatement au décompte des billets et verrouiller la caisse.</span>'
                    . '</div>'
                    . '</div>';
            }

            $livePdfUrl = View::url('finance/clotures/export-pdf-agence') . '?agence_id=' . ($activeReport['agence_id'] ?? $selectedAgenceId) . '&date=' . urlencode($activeReport['date_jour'] ?? date('Y-m-d'));
            $submissionForm = $lateAlert . '<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:1.5rem; margin-bottom:2rem; box-shadow:0 2px 10px rgba(0,0,0,0.02);">'
                . '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:0.5rem;">'
                . '<h3 style="margin:0; font-size:1.15rem; color:#0f172a; font-weight:800;"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#2563eb" stroke-width="2.5" style="display:inline; margin-right:6px; vertical-align:-2px;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>Position de Caisse en Temps Réel du jour — ' . $agenceTitle . '</h3>'
                . '<div style="display:flex; align-items:center; gap:0.6rem;">'
                . '<a href="' . $livePdfUrl . '" target="_blank" style="padding:0.45rem 0.95rem; background:#2563eb; color:#fff; font-weight:700; border-radius:8px; text-decoration:none; font-size:0.82rem; display:inline-flex; align-items:center; gap:6px; whitespace:nowrap; box-shadow:0 2px 6px rgba(37,99,235,0.2);">'
                . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline; margin-right:2px;"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg> PDF de la Journée'
                . '</a>'
                . Ui::badge(strtoupper($statut === 'brouillon' ? 'Temps Réel (Non Soumis)' : $statut), $statutBadge)
                . '</div>'
                . '</div>'
                . '<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:1.25rem; background:#fff; padding:1.25rem; border-radius:10px 10px 0 0; border:1px solid #cbd5e1; border-bottom:none;">'
                . '<div><small style="color:#64748b; font-weight:600;">Colis Saisis :</small><br><strong style="font-size:1.2rem; color:#0f172a;">' . $nbColis . ' colis</strong></div>'
                . '<div><small style="color:#64748b; font-weight:600;">Factures Émises :</small><br><strong style="font-size:1.2rem; color:#0f172a;">' . $nbFactures . ' factures</strong></div>'
                . '<div><small style="color:#64748b; font-weight:600;">Montant Facturé Total :</small><br><strong style="font-size:1.2rem; color:#0f172a;">' . number_format($totalFactureXof, 0, ',', ' ') . ' XOF</strong></div>'
                . '<div><small style="color:#64748b; font-weight:600;">Solde Caisse Live (Encaissé) :</small><br><strong style="font-size:1.3rem; color:#16a34a;">' . number_format($totalEncaisseXof, 0, ',', ' ') . ' XOF</strong></div>'
                . '<div><small style="color:#64748b; font-weight:600;">Reste à Recouvrer :</small><br><strong style="font-size:1.2rem; color:#dc2626;">' . number_format($totalRestantXof, 0, ',', ' ') . ' XOF</strong></div>'
                . '</div>'
                . '<div style="background:#f1f5f9; border:1px solid #cbd5e1; padding:0.75rem 1.25rem; font-size:0.82rem; color:#475569; display:flex; gap:1.5rem; flex-wrap:wrap; border-radius:0 0 10px 10px; margin-bottom:1rem;">'
                . '<div><strong style="color:#0f172a;">Encaissements par Canal :</strong></div>'
                . '<div><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="#16a34a" stroke-width="2" style="display:inline; margin-right:3px; vertical-align:-2px;"><rect x="2" y="6" width="20" height="12" rx="2"></rect><circle cx="12" cy="12" r="3"></circle></svg> Espèces (Tiroir) : <strong style="color:#0f172a;">' . number_format($encEspeces > 0 ? $encEspeces : $totalEncaisseXof, 0, ',', ' ') . ' XOF</strong></div>'
                . '<div><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="#2563eb" stroke-width="2" style="display:inline; margin-right:3px; vertical-align:-2px;"><rect x="5" y="2" width="14" height="20" rx="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg> Mobile Money / Carte : <strong style="color:#0f172a;">' . number_format($encDigital, 0, ',', ' ') . ' XOF</strong></div>'
                . '<div><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="#d97706" stroke-width="2" style="display:inline; margin-right:3px; vertical-align:-2px;"><rect x="3" y="4" width="18" height="16" rx="2"></rect><line x1="7" y1="8" x2="17" y2="8"></line></svg> Chèques / Virements : <strong style="color:#0f172a;">' . number_format($encCheque, 0, ',', ' ') . ' XOF</strong></div>'
                . '</div>';

            // Ventilation des encaissements par type d'envoi (LB-CI, CA-CI, LB-FR, etc.)
            $breakdownList = $activeReport['breakdown_by_type'] ?? [];
            if (!empty($breakdownList) && is_array($breakdownList)) {
                $rowsType = '';
                foreach ($breakdownList as $b) {
                    $cType = View::e((string) ($b['code_type'] ?? 'DIVERS'));
                    $nbF = (int) ($b['nb_factures'] ?? 0);
                    $totF = number_format((float) ($b['total_facture'] ?? 0), 0, ',', ' ');
                    $totE = number_format((float) ($b['total_encaisse'] ?? 0), 0, ',', ' ');

                    $rowsType .= '<tr style="border-bottom:1px solid #e2e8f0;">'
                        . '<td style="padding:8px 12px; font-weight:800; color:#1e3a5f;">' . $cType . '</td>'
                        . '<td style="padding:8px 12px; text-align:center; font-weight:600;">' . $nbF . '</td>'
                        . '<td style="padding:8px 12px; text-align:right; font-weight:600; color:#0f172a;">' . $totF . ' XOF</td>'
                        . '<td style="padding:8px 12px; text-align:right; font-weight:800; color:#16a34a;">' . $totE . ' XOF</td>'
                        . '</tr>';
                }

                $submissionForm .= '<div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:1.25rem; margin-bottom:1.5rem; box-shadow:0 2px 8px rgba(15,23,42,0.02);">'
                    . '<h4 style="margin:0 0 0.75rem 0; font-size:0.9rem; font-weight:800; color:#0f172a; text-transform:uppercase; letter-spacing:0.5px; display:flex; align-items:center; gap:6px;">'
                    . '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#2563eb" stroke-width="2.5"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>'
                    . 'Ventilation des Envois par Type (Fenêtre 15h–15h)'
                    . '</h4>'
                    . '<table style="width:100%; border-collapse:collapse; font-size:0.88rem;">'
                    . '<thead>'
                    . '<tr style="background:#f8fafc; border-bottom:2px solid #cbd5e1; text-align:left; color:#475569; font-weight:700;">'
                    . '<th style="padding:8px 12px;">Type d\'envoi</th>'
                    . '<th style="padding:8px 12px; text-align:center;">Factures</th>'
                    . '<th style="padding:8px 12px; text-align:right;">Montant Facturé</th>'
                    . '<th style="padding:8px 12px; text-align:right;">Montant Encaissé</th>'
                    . '</tr>'
                    . '</thead>'
                    . '<tbody>' . $rowsType . '</tbody>'
                    . '</table>'
                    . '</div>';
            }

            // Liste détaillée des factures du jour pour traçabilité (qui a facturé, à quelle heure, combien, nb colis)
            $invoicesDetails = $activeReport['invoices_details'] ?? [];
            if (!empty($invoicesDetails) && is_array($invoicesDetails)) {
                $rowsDetails = '';
                foreach ($invoicesDetails as $inf) {
                    $heure = date('H:i', strtotime((string)$inf['date_emission']));
                    $rowsDetails .= '<tr style="border-bottom:1px solid #e2e8f0;">'
                        . '<td style="padding:8px 12px; font-weight:700;"><a href="' . View::url('finance/factures/' . $inf['id']) . '" style="color:#2563eb; text-decoration:underline;">' . View::e($inf['numero_facture']) . '</a></td>'
                        . '<td style="padding:8px 12px; font-weight:600;">' . View::e($inf['numero_tracking']) . '</td>'
                        . '<td style="padding:8px 12px;">' . View::e($inf['client_name']) . '</td>'
                        . '<td style="padding:8px 12px; text-align:center; font-weight:700;">' . (int)$inf['nombre_colis'] . '</td>'
                        . '<td style="padding:8px 12px; text-align:right; font-weight:700; color:#0f172a;">' . number_format((float)$inf['montant_total'], 0, ',', ' ') . ' XOF</td>'
                        . '<td style="padding:8px 12px; text-align:right; font-weight:700; color:#16a34a;">' . number_format((float)$inf['montant_encaisse'], 0, ',', ' ') . ' XOF</td>'
                        . '<td style="padding:8px 12px; color:#475569; font-weight:600;">' . $heure . '</td>'
                        . '<td style="padding:8px 12px; font-weight:700; color:#1e293b;">' . View::e($inf['agent_name']) . '</td>'
                        . '</tr>';
                }

                $submissionForm .= '<div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:1.25rem; margin-bottom:1.5rem; box-shadow:0 2px 8px rgba(15,23,42,0.02);">'
                    . '<h4 style="margin:0 0 0.75rem 0; font-size:0.9rem; font-weight:800; color:#0f172a; text-transform:uppercase; letter-spacing:0.5px; display:flex; align-items:center; gap:6px;">'
                    . '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#2563eb" stroke-width="2.5"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="M12 6v6l4 2"/></svg>'
                    . 'Journal des Opérations & Traçabilité de Facturation (Fenêtre 15h–15h)'
                    . '</h4>'
                    . '<div style="max-height: 400px; overflow-y: auto;">'
                    . '<table style="width:100%; border-collapse:collapse; font-size:0.82rem;">'
                    . '<thead>'
                    . '<tr style="background:#f8fafc; border-bottom:2px solid #cbd5e1; text-align:left; color:#475569; font-weight:700; position: sticky; top: 0; z-index: 10;">'
                    . '<th style="padding:8px 12px; background:#f8fafc;">N° Facture</th>'
                    . '<th style="padding:8px 12px; background:#f8fafc;">N° Tracking</th>'
                    . '<th style="padding:8px 12px; background:#f8fafc;">Client</th>'
                    . '<th style="padding:8px 12px; text-align:center; background:#f8fafc;">Colis</th>'
                    . '<th style="padding:8px 12px; text-align:right; background:#f8fafc;">Montant Facturé</th>'
                    . '<th style="padding:8px 12px; text-align:right; background:#f8fafc;">Montant Encaissé</th>'
                    . '<th style="padding:8px 12px; background:#f8fafc;">Heure</th>'
                    . '<th style="padding:8px 12px; background:#f8fafc;">Agent de Saisie</th>'
                    . '</tr>'
                    . '</thead>'
                    . '<tbody>' . ($rowsDetails !== '' ? $rowsDetails : '<tr><td colspan="8" style="padding:15px; text-align:center; color:#64748b;">Aucune facture émise sur cette période.</td></tr>') . '</tbody>'
                    . '</table>'
                    . '</div>'
                    . '</div>';
            }

            // Blind count submission form for local cashier / head cashier when brouillon
            $userAgId = Auth::agenceId();
            $canSubmit = Auth::hasAnyRole(['caissiere', 'chef_agence', 'caissiere_principale']) &&
                ($userAgId === null || (int) $userAgId === (int) ($activeReport['agence_id'] ?? 0)) &&
                $statut === 'brouillon';

            if ($canSubmit) {
                $submissionForm .= '<form method="post" action="' . View::url('finance/clotures/soumettre') . '" enctype="multipart/form-data" class="js-protect-form" style="background:#fff; border:1px solid #cbd5e1; padding:1.5rem; border-radius:12px; margin-top:1rem; box-shadow: 0 4px 14px rgba(15,23,42,0.03);">'
                    . Form::hidden('agence_id', (string) ($activeReport['agence_id'] ?? ''))
                    . '<h4 style="margin-bottom:0.5rem; font-size:1.1rem; font-weight:800; color:#0f172a;"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" style="display:inline; margin-right:6px; vertical-align:-2px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>Rapprochement Financier & Comptage de Caisse à l\'Aveugle</h4>'
                    . '<p style="color:#64748b; font-size:0.85rem; margin-bottom:1.25rem;">Effectuez le décompte physique de vos billets et pièces en caisse sans vous fier au montant théorique du système.</p>'
                    
                    // Denomination Counting Grid
                    . '<div style="background:#f8fafc; border:1px solid #e2e8f0; padding:1.25rem; border-radius:10px; margin-bottom:1.25rem;">'
                    . '<div style="font-size:0.8rem; font-weight:800; text-transform:uppercase; color:#0f172a; margin-bottom:0.75rem;">Grille de comptage par coupures (XOF)</div>'
                    . '<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap:0.75rem;">'
                    . '<div><label style="font-size:0.75rem; font-weight:700; color:#475569;">10 000 XOF</label><input type="number" min="0" class="b-cnt" data-val="10000" style="width:100%; padding:6px; border:1px solid #cbd5e1; border-radius:6px; font-weight:700;"></div>'
                    . '<div><label style="font-size:0.75rem; font-weight:700; color:#475569;">5 000 XOF</label><input type="number" min="0" class="b-cnt" data-val="5000" style="width:100%; padding:6px; border:1px solid #cbd5e1; border-radius:6px; font-weight:700;"></div>'
                    . '<div><label style="font-size:0.75rem; font-weight:700; color:#475569;">2 000 XOF</label><input type="number" min="0" class="b-cnt" data-val="2000" style="width:100%; padding:6px; border:1px solid #cbd5e1; border-radius:6px; font-weight:700;"></div>'
                    . '<div><label style="font-size:0.75rem; font-weight:700; color:#475569;">1 000 XOF</label><input type="number" min="0" class="b-cnt" data-val="1000" style="width:100%; padding:6px; border:1px solid #cbd5e1; border-radius:6px; font-weight:700;"></div>'
                    . '<div><label style="font-size:0.75rem; font-weight:700; color:#475569;">500 / Pièces</label><input type="number" min="0" class="b-cnt" data-val="500" style="width:100%; padding:6px; border:1px solid #cbd5e1; border-radius:6px; font-weight:700;"></div>'
                    . '</div></div>'

                    . '<div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.25rem;">'
                    . Form::input('solde_physique_declare', ['label' => 'Total Physique Calculé (XOF)', 'type' => 'number', 'step' => '1', 'placeholder' => 'Calculé automatiquement ci-dessus', 'required' => true, 'id' => 'solde_physique_input'])
                    . '<div style="background:#f1f5f9; padding:0.8rem; border-radius:8px; display:flex; flex-direction:column; justify-content:center;">'
                    . '<small style="color:#64748b; font-weight:600;">Solde Théorique attendu (Blind Count) :</small>'
                    . '<strong id="blind_theo_val" style="font-size:1.1rem; color:#1e293b;">•••••• XOF <button type="button" onclick="document.getElementById(\'blind_theo_val\').innerText=\'' . number_format($totalEncaisseXof, 0, ',', ' ') . ' XOF\'" style="border:none; background:none; color:#2563eb; font-size:0.75rem; cursor:pointer; text-decoration:underline;">Afficher</button></strong>'
                    . '</div>'
                    . '</div>'
                    . '<div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.25rem; margin-top:1rem;">'
                    . Form::input('explication_ecart', ['label' => 'Explication de l\'écart éventuel (ex: monnaie en attente)', 'placeholder' => 'Préciser les motifs de l\'écart', 'id' => 'explication_ecart_input'])
                    . Form::input('justificatif_ecart_file', ['label' => '📎 Pièce justificative d\'écart (Optionnel - Photo/PDF)', 'type' => 'file', 'id' => 'justificatif_ecart_file'])
                    . '</div>'
                    . '<div style="margin-top:1.25rem; display:flex; justify-content:flex-end;">'
                    . Ui::button('Soumettre & Verrouiller la Caisse', ['type' => 'submit', 'variant' => 'accent'])
                    . '</div>'
                    . '<script>'
                    . 'document.addEventListener("DOMContentLoaded", function() {'
                    . 'var inputs = document.querySelectorAll(".b-cnt"); var soldeInput = document.getElementById("solde_physique_input");'
                    . 'function calcTotal() { var sum = 0; inputs.forEach(function(i) { var count = parseInt(i.value) || 0; var mult = parseInt(i.dataset.val) || 0; sum += (count * mult); }); soldeInput.value = sum; }'
                    . 'inputs.forEach(function(i) { i.addEventListener("input", calcTotal); }); });'
                    . '</script>'
                    . '</form>';
            } else if ($statut !== 'brouillon') {
                $submissionForm .= '<div style="background:#ecfdf5; border:2px solid #10b981; border-radius:12px; padding:1.25rem 1.5rem; margin-top:1rem; display:flex; align-items:center; justify-content:space-between; box-shadow:0 4px 12px rgba(16,185,129,0.1);">'
                    . '<div style="display:flex; align-items:center; gap:0.85rem;">'
                    . '<span style="font-size:1.8rem;">✅</span>'
                    . '<div>'
                    . '<strong style="color:#065f46; font-size:1.1rem;">Point de Caisse du Jour Soumis avec Succès !</strong><br>'
                    . '<span style="color:#047857; font-size:0.88rem;">Votre point d\'état de la journée du <strong>' . date('d/m/Y', strtotime($activeReport['date_jour'] ?? date('Y-m-d'))) . '</strong> a été transmis. Votre caisse est clôturée pour aujourd\'hui. La prochaine session s\'ouvrira demain.</span>'
                    . '</div>'
                    . '</div>'
                    . (!empty($activeReport['id']) ? '<a href="' . View::url('finance/clotures/' . $activeReport['id'] . '/export-pdf') . '" target="_blank" style="padding:0.55rem 1.1rem; background:#059669; color:#fff; font-weight:800; border-radius:8px; text-decoration:none; font-size:0.85rem; whitespace:nowrap;">🖨️ Télécharger mon PV (PDF)</a>' : '')
                    . '</div>';
            }

            $submissionForm .= '</div>';
        }

        // Formulaire de Filtre d'Historique
        $filterForm = '<form method="get" action="' . View::url('finance/clotures') . '" style="background:#ffffff; border:1px solid #cbd5e1; border-radius:12px; padding:1.25rem 1.5rem; margin-bottom:1.5rem; display:flex; gap:1rem; align-items:flex-end; flex-wrap:wrap; box-shadow:0 2px 8px rgba(0,0,0,0.02);">'
            . ($selectedAgenceId > 0 ? Form::hidden('agence_id', (string) $selectedAgenceId) : '')
            . '<div style="flex:1; min-width:140px;">' . Form::input('date_exacte', ['label' => '📅 Jour Précis', 'type' => 'date', 'value' => (string)($filters['date_exacte'] ?? '')]) . '</div>'
            . '<div style="flex:1; min-width:140px;">' . Form::input('semaine', ['label' => '📆 Semaine', 'type' => 'week', 'value' => (string)($filters['semaine'] ?? '')]) . '</div>'
            . '<div style="flex:1; min-width:140px;">' . Form::input('mois', ['label' => '🗓️ Mois', 'type' => 'month', 'value' => (string)($filters['mois'] ?? '')]) . '</div>'
            . '<div style="flex:1; min-width:120px;">' . Form::input('annee', ['label' => '🗓️ Année', 'type' => 'number', 'min' => '2020', 'max' => '2030', 'placeholder' => '2026', 'value' => (string)($filters['annee'] ?? '')]) . '</div>'
            . '<div style="flex:1; min-width:140px;">' . Form::select('statut', [['value' => '', 'label' => 'Tous les statuts'], ['value' => 'soumis', 'label' => 'Soumis'], ['value' => 'consolide', 'label' => 'Consolidé']], $filters['statut'] ?? '', ['label' => 'Statut']) . '</div>'
            . '<div style="display:flex; gap:0.5rem;">'
            . Ui::button('Filtrer', ['type' => 'submit', 'variant' => 'accent'])
            . '<a href="' . View::url('finance/clotures') . ($selectedAgenceId > 0 ? '?agence_id=' . $selectedAgenceId : '') . '" class="finea-button finea-button--secondary">Effacer</a>'
            . '</div>'
            . '</form>';

        // Liste globale des états soumis (pour consolidateurs/caissière principale)
        $tableHtml = '';
        if ($reports === []) {
            $tableHtml = Ui::emptyState(
                'Aucun point de caisse',
                'Aucun état journalier n\'a été soumis pour le moment.'
            );
        } else {
            $rows = '';
            foreach ($reports as $r) {
                $badgeTone = match($r->statut) {
                    'consolide' => 'success',
                    'soumis' => 'primary',
                    default => 'warning'
                };
                $badge = Ui::badge(strtoupper($r->statut), $badgeTone);

                $pdfBtn = '<a href="' . View::url('finance/clotures/' . $r->id . '/export-pdf') . '" target="_blank" class="finea-button-sm" style="display:inline-flex; align-items:center; gap:4px; background:#f1f5f9; color:#0f172a; border:1px solid #cbd5e1; border-radius:6px; padding:4px 8px; font-weight:700; text-decoration:none; font-size:0.75rem; margin-right:4px;" title="PV de Clôture PV">'
                    . '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg> PV'
                    . '</a>';

                $bordereauBtn = '<a href="' . View::url('finance/clotures/' . $r->id . '/bordereau-pdf') . '" target="_blank" class="finea-button-sm" style="display:inline-flex; align-items:center; gap:4px; background:#e2e8f0; color:#0f172a; border:1px solid #cbd5e1; border-radius:6px; padding:4px 8px; font-weight:700; text-decoration:none; font-size:0.75rem; margin-right:4px;" title="Bordereau de Décharge / Transfert de Caisse">'
                    . '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h10z"></path></svg> Bordereau'
                    . '</a>';

                $actionsHtml = $pdfBtn . $bordereauBtn;
                if ($r->statut === 'soumis' && Auth::hasRole(['caissiere_principale', 'dg'])) {
                    $actionsHtml .= '<form method="post" action="' . View::url('finance/clotures/' . $r->id . '/consolider') . '" class="js-protect-form" style="display:inline;">'
                        . Ui::button('Consolider', ['type' => 'submit', 'variant' => 'success', 'class' => 'finea-button-sm'])
                        . '</form>';
                }

                $agenceName = '';
                foreach ($agences as $a) {
                    if ($a['id'] === $r->agenceId) {
                        $agenceName = $a['name'];
                        break;
                    }
                }

                $ecartTone = abs($r->ecartCaisse) < 0.01 ? 'success' : 'danger';
                $ecartBadge = Ui::badge(
                    ($r->ecartCaisse > 0 ? '+' : '') . number_format($r->ecartCaisse, 0, ',', ' ') . ' XOF',
                    $ecartTone
                );

                $dateLink = '<a href="' . View::url('finance/clotures') . '?agence_id=' . $r->agenceId . '&date_exacte=' . urlencode($r->dateJour) . '" style="color:#2563eb; font-weight:800; text-decoration:underline;" title="Consulter la position et ventilation de ce jour">' . View::e($r->dateJour) . '</a>';

                $rows .= '<tr>'
                    . '<td>' . $dateLink . '</td>'
                    . '<td><strong>' . View::e($agenceName) . '</strong></td>'
                    . '<td>' . View::e($r->nbColisEnregistres) . ' / ' . View::e($r->nbFacturesEmises) . '</td>'
                    . '<td style="text-align:right;">' . View::e(number_format($r->totalFactureXof, 2, ',', ' ')) . ' XOF<br><span style="color:#64748b; font-size:0.8rem;">' . View::e(number_format($r->totalFactureEur, 2, ',', ' ')) . ' EUR</span></td>'
                    . '<td style="text-align:right; font-weight:600; color:#16a34a;">' . View::e(number_format($r->totalEncaisseXof, 2, ',', ' ')) . ' XOF</td>'
                    . '<td style="text-align:center;">' . $ecartBadge . ($r->explicationEcart ? '<br><small style="color:#64748b;" title="' . View::e($r->explicationEcart) . '"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" style="display:inline; margin-right:2px; vertical-align:-1px;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>' . View::e(mb_strimwidth($r->explicationEcart, 0, 25, '...')) . '</small>' : '') . '</td>'
                    . '<td>' . ($r->dateSoumission ? date('d/m/Y à H:i', strtotime($r->dateSoumission)) : '—') . '</td>'
                    . '<td>' . $badge . '</td>'
                    . '<td>' . $actionsHtml . '</td>'
                    . '</tr>';
            }

            $tableHtml = '<div class="finea-table-wrapper">'
                . '<table class="finea-table">'
                . '<thead>'
                . '<tr>'
                . '<th>Date</th>'
                . '<th>Agence</th>'
                . '<th>Colis / Factures</th>'
                . '<th style="text-align:right;">Total Facturé</th>'
                . '<th style="text-align:right;">Solde Théorique</th>'
                . '<th style="text-align:center;">Écart de Caisse</th>'
                . '<th>Heure Soumission</th>'
                . '<th>Statut</th>'
                . '<th>Actions</th>'
                . '</tr>'
                . '</thead>'
                . '<tbody>' . $rows . '</tbody>'
                . '</table>'
                . '</div>';
        }

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . $agenceSelector
            . $submissionForm
            . '<div class="finea-section-card" style="margin-top: 1.5rem;">'
            . '<div class="finea-section-heading"><h2 class="finea-section-title">Historique des points de caisse</h2></div>'
            . $filterForm
            . $tableHtml
            . '</div>'
            . '</div></div>';
    }

    /**
     * Rendu du grand livre comptable.
     */
    public static function comptabilitePage(array $ecritures, array $accounts, array $filters = []): string
    {
        $header = Ui::pageHeader(
            'Comptabilité Générale',
            'Livre-journal, comptes Syscohada et écritures comptables générées automatiquement.',
            [
                'eyebrow' => 'General Ledger & Accounts',
                'class' => 'rh-hero-white',
            ]
        );

        // Section filtres
        $jourOpts = [
            ['value' => '', 'label' => 'Tous les journaux'],
            ['value' => 'ventes', 'label' => 'Journal des Ventes'],
            ['value' => 'caisses', 'label' => 'Journal de Caisse'],
            ['value' => 'achats', 'label' => 'Journal des Achats'],
            ['value' => 'banque', 'label' => 'Journal de Banque'],
            ['value' => 'OD', 'label' => 'Opérations Diverses (OD)'],
        ];
        $journal = Form::selectSearch('journal', $jourOpts, $filters['journal'] ?? '', ['label' => 'Journal']);

        $compteOpts = [['value' => '', 'label' => 'Tous les comptes']];
        foreach ($accounts as $a) {
            $compteOpts[] = ['value' => $a['code'], 'label' => $a['code'] . ' - ' . $a['libelle']];
        }
        $compte = Form::selectSearch('compte', $compteOpts, $filters['compte'] ?? '', ['label' => 'Compte (Débit/Crédit)']);

        $debut = Form::input('date_debut', [
            'label' => 'Date Début',
            'type' => 'date',
            'value' => $filters['date_debut'] ?? ''
        ]);

        $fin = Form::input('date_fin', [
            'label' => 'Date Fin',
            'type' => 'date',
            'value' => $filters['date_fin'] ?? ''
        ]);

        $filterGrid = '<div class="rh-personnel-filter-grid">' . $journal . $compte . $debut . $fin . '</div>';

        $exportQuery = http_build_query(array_filter($filters));
        $csvUrl = View::url('finance/export-syscohada') . ($exportQuery !== '' ? '?' . $exportQuery : '');

        $searchBtn = '<button type="submit" class="rh-filter-btn rh-filter-btn--primary">'
            . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="rh-btn-icon"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>'
            . 'Rechercher'
            . '</button>';

        $resetBtn = '<a href="' . View::url('finance/comptabilite') . '" class="rh-filter-btn rh-filter-btn--reset">'
            . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" class="rh-btn-icon"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"></path></svg>'
            . 'Réinitialiser'
            . '</a>';

        $csvBtn = '<a href="' . $csvUrl . '" class="rh-filter-btn" style="background:#0f172a; color:#ffffff; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:6px;" target="_blank">'
            . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>'
            . 'Exporter SYSCOHADA (CSV)'
            . '</a>';

        $addOdBtn = '<button type="button" class="rh-filter-btn" style="background:#2563eb; color:#ffffff; font-weight:700;" onclick="document.getElementById(\'modal-ecriture-manuelle\').showModal()">'
            . '+ Écriture Manuelle (OD)'
            . '</button>';

        $filterActions = '<div class="rh-personnel-filter-actions">' . $searchBtn . $resetBtn . $csvBtn . $addOdBtn . '</div>';
        $form = '<form method="get" action="' . View::url('finance/comptabilite') . '" class="rh-personnel-filters">' . $filterGrid . $filterActions . '</form>';

        $compteOpts = [];
        foreach ($accounts as $a) {
            $compteOpts[] = ['value' => (string)$a['code'], 'label' => $a['code'] . ' - ' . $a['libelle']];
        }

        $odFields = Form::input('date_ecriture', ['label' => 'Date Écriture', 'type' => 'date', 'value' => date('Y-m-d'), 'required' => true])
            . Form::select('journal', [
                ['value' => 'OD', 'label' => 'Opérations Diverses (OD)'],
                ['value' => 'caisses', 'label' => 'Journal de Caisse'],
                ['value' => 'banque', 'label' => 'Journal de Banque'],
                ['value' => 'achats', 'label' => 'Journal des Achats'],
                ['value' => 'ventes', 'label' => 'Journal des Ventes'],
            ], 'OD', ['label' => 'Journal', 'required' => true])
            . Form::select('compte_debit', $compteOpts, '', ['label' => 'Compte à Débiter (+)', 'required' => true])
            . Form::select('compte_credit', $compteOpts, '', ['label' => 'Compte à Créditer (-)', 'required' => true])
            . Form::input('montant', ['label' => 'Montant (XOF)', 'type' => 'number', 'step' => '0.01', 'required' => true])
            . Form::input('libelle', ['label' => 'Libellé de l\'écriture', 'placeholder' => 'Ex: Régularisation fin de mois', 'required' => true])
            . Form::input('piece_justificative_id', ['label' => 'N° Pièce Justificative (Optionnel)', 'placeholder' => 'OD-2026-001']);

        $modalOdHtml = Ui::modal('modal-ecriture-manuelle', 'Saisie d\'Écriture Manuelle / OD', $odFields, View::url('finance/comptabilite/ecriture-manuelle'), [
            'btnLabel' => 'Enregistrer l\'Écriture',
            'btnVariant' => 'accent',
        ]);

        // Tableau
        $tableHtml = '';
        if ($ecritures === []) {
            $tableHtml = Ui::emptyState(
                'Aucune écriture comptable',
                'Aucune écriture ne correspond à vos critères.'
            );
        } else {
            $rows = '';
            $totalMontant = 0.0;
            $nbEcritures = count($ecritures);

            foreach ($ecritures as $e) {
                $totalMontant += (float) $e->montant;
                $compteDebName = '';
                $compteCredName = '';
                foreach ($accounts as $a) {
                    if ($a['code'] === $e->compteDebit) {
                        $compteDebName = $a['libelle'];
                    }
                    if ($a['code'] === $e->compteCredit) {
                        $compteCredName = $a['libelle'];
                    }
                }

                $actionHtml = '<form method="post" action="' . View::url('finance/comptabilite/' . $e->id . '/contre-passer') . '" onsubmit="return confirm(\'Voulez-vous contre-passer (annuler) l\\\'écriture #' . $e->id . ' ?\')" style="display:inline;">'
                    . Form::hidden('_csrf_token', \App\Helpers\Csrf::token())
                    . '<button type="submit" class="finea-button finea-button--outline" style="padding:3px 7px; font-size:10px; color:#dc2626; border-color:#fca5a5;">Contre-passer</button>'
                    . '</form>';

                $rows .= '<tr>'
                    . '<td>' . View::e($e->dateEcriture) . '</td>'
                    . '<td>' . Ui::badge(strtoupper($e->journal)) . '</td>'
                    . '<td><strong>' . View::e($e->compteDebit) . '</strong><br><small style="color:#64748b;">' . View::e($compteDebName) . '</small></td>'
                    . '<td><strong>' . View::e($e->compteCredit) . '</strong><br><small style="color:#64748b;">' . View::e($compteCredName) . '</small></td>'
                    . '<td style="text-align:right; font-weight:700; color:#1e293b;">' . View::e(number_format($e->montant, 2, ',', ' ')) . ' ' . View::e($e->devise) . '</td>'
                    . '<td>' . View::e($e->pieceJustificativeId) . '</td>'
                    . '<td>' . View::e($e->libelle) . '</td>'
                    . '<td><span style="font-family:monospace; background:#f1f5f9; padding:0.2rem 0.4rem; border-radius:4px;">' . View::e($e->lettrage ?? '—') . '</span></td>'
                    . '<td style="text-align:center;">' . $actionHtml . '</td>'
                    . '</tr>';
            }

            $tfoot = '<tfoot style="background:#f8fafc; border-top:2px solid #cbd5e1; font-weight:800; color:#0f172a;">'
                . '<tr>'
                . '<td colspan="4" style="padding:10px 12px;">Total — ' . $nbEcritures . ' écriture(s)</td>'
                . '<td style="text-align:right; padding:10px 12px; color:#2563eb; font-size:0.95rem;">' . number_format($totalMontant, 2, ',', ' ') . ' XOF</td>'
                . '<td colspan="4"></td>'
                . '</tr>'
                . '</tfoot>';

            $tableHtml = '<div class="finea-table-wrapper">'
                . '<table class="finea-table">'
                . '<thead>'
                . '<tr>'
                . '<th>Date</th>'
                . '<th>Journal</th>'
                . '<th>Compte Débité</th>'
                . '<th>Compte Crédité</th>'
                . '<th style="text-align:right;">Montant</th>'
                . '<th>Pièce Réf</th>'
                . '<th>Libellé écriture</th>'
                . '<th>Lettrage</th>'
                . '<th style="text-align:center;">Action</th>'
                . '</tr>'
                . '</thead>'
                . '<tbody>' . $rows . '</tbody>'
                . $tfoot
                . '</table>'
                . '</div>';
        }

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . $form
            . '<div class="finea-section-card" style="margin-top: 1.5rem;">'
            . '<div class="finea-section-heading"><h2 class="finea-section-title">Livre-journal des écritures</h2></div>'
            . $tableHtml
            . '</div>'
            . $modalOdHtml
            . '</div></div>';
    }

    public static function publicPayPage(Facture $facture, array $colis = [], array $client = []): string
    {
        $numFacture = View::e($facture->numeroFacture);
        $clientName = View::e($client['name'] ?? '—');
        $clientPhone = View::e($client['phone'] ?? '');
        $montantFormat = number_format($facture->montantRestant, 0, ',', ' ');
        $devise = View::e($facture->devise);
        $callbackUrl = View::url('api/paiements/callback');
        $factureId = (int) $facture->id;
        $montantRestant = (float) $facture->montantRestant;

        return '<!DOCTYPE html>'
            . '<html lang="fr">'
            . '<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '<title>Portail de Paiement Sécurisé - La Belle Porte (LBP)</title>'
            . '<link rel="preconnect" href="https://fonts.googleapis.com">'
            . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            . '<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">'
            . '<style>'
            . ':root { --bg-gradient: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #311042 100%); --glass-bg: rgba(255, 255, 255, 0.03); --glass-border: rgba(255, 255, 255, 0.08); --text-primary: #f8fafc; --text-secondary: #94a3b8; --accent-success: #10b981; }'
            . '* { margin: 0; padding: 0; box-sizing: border-box; }'
            . 'body { font-family: "Plus Jakarta Sans", sans-serif; background: var(--bg-gradient); color: var(--text-primary); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem; overflow-x: hidden; }'
            . '.payment-container { background: var(--glass-bg); border: 1px solid var(--glass-border); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border-radius: 24px; width: 100%; max-width: 520px; padding: 2.5rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); position: relative; }'
            . '.header { text-align: center; margin-bottom: 2rem; }'
            . '.logo { font-family: "Outfit", sans-serif; font-size: 1.75rem; font-weight: 700; background: linear-gradient(to right, #a78bfa, #818cf8, #60a5fa); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 0.5rem; }'
            . '.subtitle { color: var(--text-secondary); font-size: 0.875rem; letter-spacing: 0.5px; text-transform: uppercase; }'
            . '.invoice-card { background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 16px; padding: 1.5rem; margin-bottom: 2rem; }'
            . '.invoice-row { display: flex; justify-content: space-between; margin-bottom: 0.75rem; font-size: 0.9rem; }'
            . '.invoice-row:last-child { margin-bottom: 0; padding-top: 0.75rem; border-top: 1px dashed rgba(255, 255, 255, 0.1); }'
            . '.label { color: var(--text-secondary); } .val { font-weight: 500; } .total-amount { font-size: 1.5rem; font-weight: 700; color: #60a5fa; }'
            . '.section-title { font-size: 1rem; font-weight: 600; margin-bottom: 1rem; color: var(--text-primary); }'
            . '.providers-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 2rem; }'
            . '.provider-card { background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.06); border-radius: 16px; padding: 1rem; text-align: center; cursor: pointer; transition: all 0.3s; }'
            . '.provider-card:hover { transform: translateY(-4px); background: rgba(255, 255, 255, 0.04); }'
            . '.provider-card.selected { background: rgba(99, 102, 241, 0.1); border-color: #6366f1; box-shadow: 0 0 15px rgba(99, 102, 241, 0.2); }'
            . '.provider-logo { font-size: 1.75rem; margin-bottom: 0.5rem; display: block; } .provider-name { font-size: 0.8rem; font-weight: 600; }'
            . '.form-group { margin-bottom: 1.5rem; } .form-label { display: block; font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.5rem; }'
            . '.form-input { width: 100%; background: rgba(0, 0, 0, 0.2); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; padding: 0.875rem 1rem; color: var(--text-primary); font-family: inherit; font-size: 0.95rem; }'
            . '.btn-pay { width: 100%; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); border: none; border-radius: 14px; color: white; padding: 1rem; font-size: 1rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem; box-shadow: 0 10px 20px -10px rgba(99, 102, 241, 0.5); }'
            . '.state-screen { display: none; text-align: center; padding: 2rem 0; }'
            . '.state-icon { width: 80px; height: 80px; border-radius: 50%; background: rgba(16, 185, 129, 0.1); border: 2px solid var(--accent-success); color: var(--accent-success); display: flex; align-items: center; justify-content: center; font-size: 2.5rem; margin: 0 auto 1.5rem auto; }'
            . '.spinner { border: 4px solid rgba(255, 255, 255, 0.1); width: 80px; height: 80px; border-radius: 50%; border-left-color: #6366f1; animation: spin 1s linear infinite; margin: 0 auto 1.5rem auto; }'
            . '@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }'
            . '.success-title { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem; } .success-desc { color: var(--text-secondary); font-size: 0.9rem; line-height: 1.5; margin-bottom: 2rem; }'
            . '</style></head>'
            . '<body><div class="payment-container">'
            . '<div id="form-screen"><div class="header"><div class="logo">LA BELLE PORTE</div><div class="subtitle">Portail de Paiement Sécurisé</div></div>'
            . '<div class="invoice-card">'
            . '<div class="invoice-row"><span class="label">N° Facture</span><span class="val">' . $numFacture . '</span></div>'
            . '<div class="invoice-row"><span class="label">Client</span><span class="val">' . $clientName . '</span></div>'
            . '<div class="invoice-row"><span class="label">Téléphone</span><span class="val">' . $clientPhone . '</span></div>'
            . '<div class="invoice-row"><span class="label">Reste à payer</span><span class="val total-amount">' . $montantFormat . ' ' . $devise . '</span></div>'
            . '</div>'
            . '<div class="section-title">Sélectionnez votre moyen de paiement</div>'
            . '<div class="providers-grid">'
            . '<div class="provider-card selected" data-provider="wave"><span class="provider-logo">🌊</span><span class="provider-name">Wave</span></div>'
            . '<div class="provider-card" data-provider="orange"><span class="provider-logo">🍊</span><span class="provider-name">Orange Money</span></div>'
            . '<div class="provider-card" data-provider="mtn"><span class="provider-logo">🟡</span><span class="provider-name">MTN MoMo</span></div>'
            . '</div>'
            . '<div class="form-group"><label class="form-label" for="phone-input">Numéro de téléphone mobile money</label><input type="tel" id="phone-input" class="form-input" value="' . $clientPhone . '" placeholder="Ex: 0707070707"></div>'
            . '<button class="btn-pay" id="btn-pay">Payer ' . $montantFormat . ' ' . $devise . '</button>'
            . '</div>'
            . '<div id="processing-screen" class="state-screen"><div class="spinner"></div><h3 class="success-title">Paiement en cours</h3><p class="success-desc">Veuillez valider la notification de paiement sur votre téléphone mobile.</p></div>'
            . '<div id="success-screen" class="state-screen"><div class="state-icon">✓</div><h3 class="success-title" style="color: var(--accent-success)">Paiement Réussi !</h3><p class="success-desc">Votre paiement a été traité avec succès et votre solde a été mis à jour dans notre système.<br><br>Vous pouvez fermer cet onglet.</p><button class="btn-pay" onclick="window.close()" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: var(--text-primary); box-shadow: none;">Fermer le portail</button></div>'
            . '</div>'
            . '<script>'
            . 'document.addEventListener("DOMContentLoaded", function() {'
            . 'var providerCards = document.querySelectorAll(".provider-card"); var selectedProvider = "wave";'
            . 'providerCards.forEach(function(card) { card.addEventListener("click", function() { providerCards.forEach(function(c) { c.classList.remove("selected"); }); card.classList.add("selected"); selectedProvider = card.dataset.provider; }); });'
            . 'var btnPay = document.getElementById("btn-pay"); var formScreen = document.getElementById("form-screen"); var processingScreen = document.getElementById("processing-screen"); var successScreen = document.getElementById("success-screen"); var phoneInput = document.getElementById("phone-input");'
            . 'btnPay.addEventListener("click", function() { if (!phoneInput.value.trim()) { alert("Veuillez saisir votre numéro de téléphone."); return; }'
            . 'formScreen.style.display = "none"; processingScreen.style.display = "block";'
            . 'setTimeout(function() {'
            . 'var transRef = "TX-" + Math.random().toString(36).substr(2, 9).toUpperCase();'
            . 'var payload = { facture_id: ' . $factureId . ', transaction_reference: transRef, montant: ' . $montantRestant . ', devise: "' . $devise . '", statut: "success", provider: selectedProvider };'
            . 'fetch("' . $callbackUrl . '", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(payload) })'
            . '.then(function(r) { return r.json(); })'
            . '.then(function(data) { processingScreen.style.display = "none"; if (data.ok) { successScreen.style.display = "block"; } else { alert("Erreur lors du paiement: " + data.message); formScreen.style.display = "block"; } })'
            . '.catch(function(e) { console.error(e); processingScreen.style.display = "none"; alert("Erreur réseau."); formScreen.style.display = "block"; });'
            . '}, 3000); }); });'
            . '</script></body></html>';
    }

    public static function rentabilitePage(\App\View\Pages\Finance\RentabilitePage $page): string
    {
        $actionPdf = '<a href="' . View::url('finance/rentabilite/export-pdf') . '" target="_blank" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; background: #0f172a; color: #ffffff; font-weight: 700; border-radius: 8px; text-decoration: none; box-shadow: 0 4px 12px rgba(15,23,42,0.15);">'
            . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>'
            . 'Imprimer / Export PDF'
            . '</a>';

        $header = Ui::pageHeader(
            'Rentabilité par Trajet (P&L Logistique)',
            'Analyse croisée des recettes facturées et des débours prestataires par lot de transport.',
            [
                'eyebrow' => 'Pilotage Financier',
                'class' => 'rh-hero-white',
                'actions' => [$actionPdf],
            ]
        );

        $recettes = number_format($page->summary['total_recettes'] ?? 0.0, 0, ',', ' ');
        $depenses = number_format($page->summary['total_depenses'] ?? 0.0, 0, ',', ' ');
        $margeNette = number_format($page->summary['marge_nette'] ?? 0.0, 0, ',', ' ');
        $tauxMarge = number_format($page->summary['taux_marge'] ?? 0.0, 1, ',', ' ');

        $statsGrid = '
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 1.75rem;">
            <div style="background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.25rem; box-shadow: 0 4px 12px rgba(15,23,42,0.03); display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 0.35rem;">Total Recettes (Factures)</div>
                    <div style="font-size: 1.65rem; font-weight: 800; color: #0f172a; line-height: 1;">' . $recettes . ' <span style="font-size:0.9rem; font-weight:600; color:#64748b;">XOF</span></div>
                </div>
                <div style="width: 46px; height: 46px; border-radius: 12px; background: rgba(16, 185, 129, 0.12); color: #059669; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                </div>
            </div>

            <div style="background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.25rem; box-shadow: 0 4px 12px rgba(15,23,42,0.03); display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 0.35rem;">Total Débours Prestataires</div>
                    <div style="font-size: 1.65rem; font-weight: 800; color: #dc2626; line-height: 1;">' . $depenses . ' <span style="font-size:0.9rem; font-weight:600; color:#64748b;">XOF</span></div>
                </div>
                <div style="width: 46px; height: 46px; border-radius: 12px; background: rgba(220, 38, 38, 0.12); color: #dc2626; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"></path></svg>
                </div>
            </div>

            <div style="background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.25rem; box-shadow: 0 4px 12px rgba(15,23,42,0.03); display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 0.35rem;">Marge Nette Globale</div>
                    <div style="font-size: 1.65rem; font-weight: 800; color: #2563eb; line-height: 1;">' . $margeNette . ' <span style="font-size:0.9rem; font-weight:600; color:#64748b;">XOF</span></div>
                </div>
                <div style="width: 46px; height: 46px; border-radius: 12px; background: rgba(37, 99, 235, 0.12); color: #2563eb; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
                </div>
            </div>

            <div style="background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.25rem; box-shadow: 0 4px 12px rgba(15,23,42,0.03); display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 0.35rem;">Taux de Marge Moyen</div>
                    <div style="font-size: 1.65rem; font-weight: 800; color: #0f172a; line-height: 1;">' . $tauxMarge . '%</div>
                </div>
                <div style="width: 46px; height: 46px; border-radius: 12px; background: rgba(14, 165, 233, 0.12); color: #0284c7; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M18 20V10M12 20V4M6 20v-6"></path></svg>
                </div>
            </div>
        </div>';

        $rows = '';
        foreach ($page->trajets as $t) {
            $margeVal = (float) $t['marge_nette'];
            $margeTone = $margeVal >= 0 ? 'background: #dcfce7; color: #15803d;' : 'background: #fee2e2; color: #b91c1c;';
            $tauxVal = number_format((float) $t['taux_marge'], 1, ',', ' ');

            $rows .= '<tr style="border-bottom: 1px solid #f1f5f9;">'
                . '<td style="padding: 14px 16px;"><strong>' . View::e($t['code']) . '</strong></td>'
                . '<td style="padding: 14px 16px;">' . View::e($t['libelle']) . ' <small style="color:#64748b;">(' . View::e($t['type_transport']) . ')</small></td>'
                . '<td style="padding: 14px 16px; font-weight: 700; color: #15803d; text-align: right;">' . number_format((float) $t['total_recettes'], 0, ',', ' ') . ' XOF</td>'
                . '<td style="padding: 14px 16px; font-weight: 700; color: #b91c1c; text-align: right;">' . number_format((float) $t['total_depenses'], 0, ',', ' ') . ' XOF</td>'
                . '<td style="padding: 14px 16px; font-weight: 800; text-align: right;"><span style="display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 0.85rem; ' . $margeTone . '">' . number_format($margeVal, 0, ',', ' ') . ' XOF</span></td>'
                . '<td style="padding: 14px 16px; font-weight: 800; text-align: right; color: #0f172a;">' . $tauxVal . '%</td>'
                . '</tr>';
        }

        $tableHtml = '<div class="finea-table-wrapper" style="overflow-x: auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; box-shadow: 0 4px 12px rgba(15,23,42,0.03);">'
            . '<table class="finea-table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">'
            . '<thead style="background: #0f172a; color: #ffffff;">'
            . '<tr>'
            . '<th style="padding: 12px 16px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase;">Code Lot</th>'
            . '<th style="padding: 12px 16px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase;">Trajet & Type</th>'
            . '<th style="padding: 12px 16px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; text-align: right;">Recettes Factures</th>'
            . '<th style="padding: 12px 16px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; text-align: right;">Débours Prestataires</th>'
            . '<th style="padding: 12px 16px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; text-align: right;">Marge Nette</th>'
            . '<th style="padding: 12px 16px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; text-align: right;">Taux Marge</th>'
            . '</tr>'
            . '</thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table></div>';

        return '<div class="finea-shell"><div class="finea-container">' . $header . $statsGrid . $tableHtml . '</div></div>';
    }

    public static function balanceAgeePage(\App\View\Pages\Finance\BalanceAgeePage $page): string
    {
        $actionPdf = '<a href="' . View::url('finance/balance-agee/export-pdf') . '" target="_blank" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; background: #2563eb; color: #ffffff; font-weight: 700; border-radius: 8px; text-decoration: none; box-shadow: 0 4px 12px rgba(37,99,235,0.25);">'
            . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>'
            . 'Imprimer / Export PDF'
            . '</a>';

        $actionExport = '<a href="' . View::url('finance/export-syscohada') . '" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; background: #0f172a; color: #ffffff; font-weight: 700; border-radius: 8px; text-decoration: none;">'
            . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"></path></svg>'
            . 'Export SYSCOHADA (CSV)'
            . '</a>';

        $header = Ui::pageHeader(
            'Balance Âgée des Créances',
            'Ventilation des restes à encaisser par tranches d\'ancienneté.',
            [
                'eyebrow' => 'Recouvrement & Crédits',
                'class' => 'rh-hero-white',
                'actions' => [$actionPdf, $actionExport],
            ]
        );

        $b30 = number_format($page->agingBuckets['b30'] ?? 0.0, 0, ',', ' ');
        $b60 = number_format($page->agingBuckets['b60'] ?? 0.0, 0, ',', ' ');
        $b90 = number_format($page->agingBuckets['b90'] ?? 0.0, 0, ',', ' ');
        $bPlus90 = number_format($page->agingBuckets['bPlus90'] ?? 0.0, 0, ',', ' ');

        $statsGrid = '
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 1.75rem;">
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.25rem; border-left: 5px solid #16a34a;">
                <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748b;">0 à 30 jours (Récent)</div>
                <div style="font-size: 1.65rem; font-weight: 800; color: #16a34a; margin-top: 0.35rem;">' . $b30 . ' <small style="font-size:0.9rem; color:#64748b;">XOF</small></div>
            </div>
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.25rem; border-left: 5px solid #0284c7;">
                <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748b;">31 à 60 jours</div>
                <div style="font-size: 1.65rem; font-weight: 800; color: #0284c7; margin-top: 0.35rem;">' . $b60 . ' <small style="font-size:0.9rem; color:#64748b;">XOF</small></div>
            </div>
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.25rem; border-left: 5px solid #d97706;">
                <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748b;">61 à 90 jours</div>
                <div style="font-size: 1.65rem; font-weight: 800; color: #d97706; margin-top: 0.35rem;">' . $b90 . ' <small style="font-size:0.9rem; color:#64748b;">XOF</small></div>
            </div>
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.25rem; border-left: 5px solid #dc2626;">
                <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748b;">+ 90 jours (Alerte Recouvrement)</div>
                <div style="font-size: 1.65rem; font-weight: 800; color: #dc2626; margin-top: 0.35rem;">' . $bPlus90 . ' <small style="font-size:0.9rem; color:#64748b;">XOF</small></div>
            </div>
        </div>';

        $rows = '';
        foreach ($page->clientDetails as $c) {
            $rows .= '<tr style="border-bottom: 1px solid #f1f5f9;">'
                . '<td style="padding: 14px 16px;"><strong>' . View::e($c['client_name']) . '</strong><br><small style="color:#64748b;">' . View::e($c['phone']) . '</small></td>'
                . '<td style="padding: 14px 16px; text-align: right; color: #16a34a; font-weight: 600;">' . number_format((float) $c['b30'], 0, ',', ' ') . ' XOF</td>'
                . '<td style="padding: 14px 16px; text-align: right; color: #0284c7; font-weight: 600;">' . number_format((float) $c['b60'], 0, ',', ' ') . ' XOF</td>'
                . '<td style="padding: 14px 16px; text-align: right; color: #d97706; font-weight: 600;">' . number_format((float) $c['b90'], 0, ',', ' ') . ' XOF</td>'
                . '<td style="padding: 14px 16px; text-align: right; color: #dc2626; font-weight: 700;">' . number_format((float) $c['bPlus90'], 0, ',', ' ') . ' XOF</td>'
                . '<td style="padding: 14px 16px; text-align: right; font-weight: 800; color: #0f172a;">' . number_format((float) $c['total'], 0, ',', ' ') . ' XOF</td>'
                . '</tr>';
        }

        $tableHtml = '<div class="finea-table-wrapper" style="overflow-x: auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; box-shadow: 0 4px 12px rgba(15,23,42,0.03);">'
            . '<table class="finea-table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">'
            . '<thead style="background: #0f172a; color: #ffffff;">'
            . '<tr>'
            . '<th style="padding: 12px 16px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase;">Client</th>'
            . '<th style="padding: 12px 16px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; text-align: right;">0 - 30j</th>'
            . '<th style="padding: 12px 16px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; text-align: right;">31 - 60j</th>'
            . '<th style="padding: 12px 16px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; text-align: right;">61 - 90j</th>'
            . '<th style="padding: 12px 16px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; text-align: right;">+ 90j</th>'
            . '<th style="padding: 12px 16px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; text-align: right;">Total Reste à Payer</th>'
            . '</tr>'
            . '</thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table></div>';

        return '<div class="finea-shell"><div class="finea-container">' . $header . $statsGrid . $tableHtml . '</div></div>';
    }

    public static function portefeuillesPage(array $wallets, array $recentTx): string
    {
        $header = Ui::pageHeader(
            'Portefeuilles Clients & Compte Avances',
            'Gestion des solde créditeurs (avances) et contrôle des plafonds de crédit client.',
            'FINANCE',
            [
                ['label' => 'Factures', 'href' => 'finance/factures', 'variant' => 'outline'],
                ['label' => 'Portefeuilles', 'href' => 'finance/portefeuilles', 'variant' => 'primary'],
            ]
        );

        $walletOptions = [];
        foreach ($wallets as $w) {
            $walletOptions[] = [
                'value' => (string)$w['id'],
                'label' => $w['client_nom'] . ' (Solde: ' . number_format((float)$w['solde_xof'], 0, ',', ' ') . ' XOF)',
            ];
        }

        $fields = Form::selectSearch('wallet_id', $walletOptions, '', ['label' => 'Client / Compte Avance', 'required' => true])
            . Form::input('montant_xof', ['label' => 'Montant du Crédit (XOF)', 'type' => 'number', 'step' => '1000', 'required' => true])
            . Form::select('mode_paiement', [
                ['value' => 'Espèces', 'label' => 'Espèces (Caisse)'],
                ['value' => 'Wave', 'label' => 'Wave Mobile Money'],
                ['value' => 'Orange Money', 'label' => 'Orange Money'],
                ['value' => 'Virement Bancaire', 'label' => 'Virement Bancaire'],
                ['value' => 'Chèque', 'label' => 'Chèque certifié'],
            ], 'Espèces', ['label' => 'Mode de Règlement'])
            . Form::input('reference_transac', ['label' => 'Référence Transaction / Chèque', 'placeholder' => 'Ex: WAV-2026-99182'])
            . Form::input('motif', ['label' => 'Motif / Note', 'value' => 'Avance sur expédition douane/fret']);

        $modalHtml = Ui::modal('modal-credit-wallet', 'Créditer un Portefeuille Client', $fields, View::url('finance/portefeuilles/crediter'), [
            'btnLabel' => 'Enregistrer le crédit',
            'btnVariant' => 'accent',
        ]);

        $walletRows = '';
        foreach ($wallets as $w) {
            $solde = (float)$w['solde_xof'];
            $plafond = (float)$w['plafond_credit_xof'];
            $soldeTone = $solde > 0 ? '#16a34a' : '#64748b';
            
            $walletRows .= '<tr>'
                . '<td><strong>' . View::e($w['client_nom']) . '</strong><br><small style="color:#64748b;">' . View::e($w['telephone'] ?? '—') . '</small></td>'
                . '<td style="text-align:right; font-weight:800; color:' . $soldeTone . ';">' . number_format($solde, 0, ',', ' ') . ' XOF</td>'
                . '<td style="text-align:right; font-weight:600; color:#2563eb;">' . number_format((float)$w['solde_eur'], 2, '.', ' ') . ' €</td>'
                . '<td style="text-align:right; font-weight:600; color:#d97706;">' . number_format($plafond, 0, ',', ' ') . ' XOF</td>'
                . '<td style="text-align:center;">' . Ui::badge($w['statut'], $w['statut'] === 'ACTIF' ? 'success' : 'danger') . '</td>'
                . '</tr>';
        }

        $txRows = '';
        foreach ($recentTx as $tx) {
            $txRows .= '<tr>'
                . '<td>' . date('d/m/Y H:i', strtotime($tx['created_at'])) . '</td>'
                . '<td><strong>' . View::e($tx['client_nom']) . '</strong></td>'
                . '<td><span style="background:#e0f2fe; color:#0369a1; padding:2px 6px; border-radius:4px; font-weight:700; font-size:11px;">' . View::e($tx['type']) . '</span></td>'
                . '<td style="text-align:right; font-weight:800; color:#16a34a;">+' . number_format((float)$tx['montant_xof'], 0, ',', ' ') . ' XOF</td>'
                . '<td>' . View::e($tx['mode_paiement']) . '</td>'
                . '<td>' . View::e($tx['motif'] ?? '—') . '</td>'
                . '</tr>';
        }

        return '<div class="finea-shell"><div class="finea-container">'
            . $header
            . '<div style="margin-bottom:1.5rem; text-align:right;"><button class="finea-button finea-button--accent" onclick="document.getElementById(\'modal-credit-wallet\').showModal()">+ Créditer un Portefeuille Client</button></div>'
            . '<div class="finea-section-card" style="margin-bottom:2rem;">'
            . '<h3 style="font-weight:800; font-size:1.1rem; margin-bottom:1rem;">Comptes Créditeurs Clients (Avances & Plafonds)</h3>'
            . '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>Client</th><th style="text-align:right;">Solde Disponible (XOF)</th><th style="text-align:right;">Solde (EUR)</th><th style="text-align:right;">Plafond Crédit Autorisé</th><th style="text-align:center;">Statut</th>'
            . '</tr></thead><tbody>' . $walletRows . '</tbody></table></div></div>'
            . '<div class="finea-section-card">'
            . '<h3 style="font-weight:800; font-size:1.1rem; margin-bottom:1rem;">Dernières Transactions d\'Avances</h3>'
            . '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>Date</th><th>Client</th><th>Type</th><th style="text-align:right;">Montant</th><th>Mode</th><th>Motif</th>'
            . '</tr></thead><tbody>' . $txRows . '</tbody></table></div></div>'
            . $modalHtml
            . '</div></div>';
    }

    public static function coutsApprochePage(array $landedCosts, array $trajets): string
    {
        $header = Ui::pageHeader(
            'Ventilation des Coûts d\'Approche (Landed Costs)',
            'Répartition des débours de Douane, Fret et Manutention au kilo/volume sur chaque colis.',
            'FINANCE',
            [
                ['label' => 'Rentabilité Trajets', 'href' => 'finance/rentabilite', 'variant' => 'outline'],
                ['label' => 'Coûts d\'Approche', 'href' => 'finance/couts-approche', 'variant' => 'primary'],
            ]
        );

        $trajetOpts = [];
        foreach ($trajets as $t) {
            $trajetOpts[] = ['value' => $t['code'], 'label' => $t['code'] . ' - ' . $t['libelle']];
        }

        $fields = Form::input('reference_lot', ['label' => 'Référence du Lot / Conteneur / Vol', 'placeholder' => 'Ex: LOT-AIR-FR-2026-08', 'required' => true])
            . Form::select('trajet_code', $trajetOpts, 'LB-FR', ['label' => 'Trajet / Route'])
            . Form::input('frais_douane_xof', ['label' => 'Total Frais de Douane & Taxes (XOF)', 'type' => 'number', 'value' => '1500000', 'required' => true])
            . Form::input('frais_fret_xof', ['label' => 'Total Fret Principal Aérien/Maritime (XOF)', 'type' => 'number', 'value' => '800000', 'required' => true])
            . Form::input('frais_manutention_xof', ['label' => 'Frais de Manutention & Magasinage (XOF)', 'type' => 'number', 'value' => '200000'])
            . Form::input('poids_total_kg', ['label' => 'Poids Brut Total du Lot (kg)', 'type' => 'number', 'step' => '0.1', 'value' => '1250.0', 'required' => true]);

        $modalHtml = Ui::modal('modal-landed-cost', 'Calculer & Ventiler un Coût d\'Approche', $fields, View::url('finance/couts-approche/calculer'), [
            'btnLabel' => 'Calculer le Coût d\'Approche',
            'btnVariant' => 'accent',
        ]);

        $rows = '';
        foreach ($landedCosts as $lc) {
            $totalFrais = (float)$lc['frais_douane_xof'] + (float)$lc['frais_fret_xof'] + (float)$lc['frais_manutention_xof'];
            $rows .= '<tr>'
                . '<td><strong>' . View::e($lc['reference_lot']) . '</strong></td>'
                . '<td><span style="background:#0f172a; color:#fff; padding:2px 6px; border-radius:4px; font-weight:800; font-size:11px;">' . View::e($lc['trajet_code']) . '</span></td>'
                . '<td style="text-align:right;">' . number_format((float)$lc['frais_douane_xof'], 0, ',', ' ') . ' XOF</td>'
                . '<td style="text-align:right;">' . number_format((float)$lc['frais_fret_xof'], 0, ',', ' ') . ' XOF</td>'
                . '<td style="text-align:right;">' . number_format((float)$lc['poids_total_kg'], 2, '.', ' ') . ' kg</td>'
                . '<td style="text-align:right; font-weight:800; color:#ea580c;">' . number_format($totalFrais, 0, ',', ' ') . ' XOF</td>'
                . '<td style="text-align:right; font-weight:900; color:#16a34a; background:#f0fdf4;">' . number_format((float)$lc['cout_par_kg_xof'], 2, '.', ' ') . ' XOF / kg</td>'
                . '<td style="text-align:center;">' . Ui::badge($lc['statut'], 'success') . '</td>'
                . '</tr>';
        }

        return '<div class="finea-shell"><div class="finea-container">'
            . $header
            . '<div style="margin-bottom:1.5rem; text-align:right;"><button class="finea-button finea-button--accent" onclick="document.getElementById(\'modal-landed-cost\').showModal()">+ Nouveau Calcul Coût d\'Approche</button></div>'
            . '<div class="finea-section-card">'
            . '<h3 style="font-weight:800; font-size:1.1rem; margin-bottom:1rem;">Lots & Ventilation des Coûts Réels par kg</h3>'
            . '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>Référence Lot</th><th>Trajet</th><th style="text-align:right;">Douane (XOF)</th><th style="text-align:right;">Fret (XOF)</th><th style="text-align:right;">Poids Lot</th><th style="text-align:right;">Total Débours</th><th style="text-align:right;">Coût Net / kg</th><th style="text-align:center;">Statut</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table></div></div>'
            . $modalHtml
            . '</div></div>';
    }

    public static function rapprochementMobileMoneyPage(array $reconciliations): string
    {
        $header = Ui::pageHeader(
            'Rapprochement Mobile Money & Banque',
            'Vérification et validation 1-clic des flux Wave, Orange Money et virements bancaires.',
            'FINANCE',
            [
                ['label' => 'Factures', 'href' => 'finance/factures', 'variant' => 'outline'],
                ['label' => 'Rapprochement', 'href' => 'finance/rapprochement-mobile-money', 'variant' => 'primary'],
            ]
        );

        $totRapproche = 0.0;
        $totAttente = 0.0;
        $nbRapproche = 0;
        $nbAttente = 0;

        foreach ($reconciliations as $r) {
            $m = (float) ($r['montant_xof'] ?? 0.0);
            if (strtoupper($r['statut']) === 'RAPPROCHÉ') {
                $totRapproche += $m;
                $nbRapproche++;
            } else {
                $totAttente += $m;
                $nbAttente++;
            }
        }

        $kpiHtml = '<div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.25rem; margin-bottom:1.75rem;">'
            . '<div style="background:#ffffff; border:1px solid #e2e8f0; border-left:5px solid #16a34a; border-radius:12px; padding:1.25rem;">'
            . '<div style="font-size:0.75rem; font-weight:800; color:#64748b; text-transform:uppercase;">Total Transac Rapprochées (' . $nbRapproche . ')</div>'
            . '<div style="font-size:1.65rem; font-weight:900; color:#16a34a; margin-top:0.35rem;">' . number_format($totRapproche, 0, ',', ' ') . ' XOF</div>'
            . '</div>'
            . '<div style="background:#ffffff; border:1px solid #e2e8f0; border-left:5px solid #d97706; border-radius:12px; padding:1.25rem;">'
            . '<div style="font-size:0.75rem; font-weight:800; color:#64748b; text-transform:uppercase;">Transac à Valider / En Attente (' . $nbAttente . ')</div>'
            . '<div style="font-size:1.65rem; font-weight:900; color:#d97706; margin-top:0.35rem;">' . number_format($totAttente, 0, ',', ' ') . ' XOF</div>'
            . '</div>'
            . '</div>';

        $rows = '';
        foreach ($reconciliations as $r) {
            $tone = match($r['statut']) {
                'RAPPROCHÉ' => 'success',
                'ECART_MONTANT' => 'warning',
                'REJETÉ' => 'danger',
                default => 'info'
            };

            $rows .= '<tr>'
                . '<td>' . date('d/m/Y H:i', strtotime($r['date_transaction'])) . '</td>'
                . '<td><strong>' . View::e($r['operateur']) . '</strong></td>'
                . '<td><code style="background:#f1f5f9; padding:2px 6px; border-radius:4px; font-weight:700;">' . View::e($r['reference_transac']) . '</code></td>'
                . '<td>' . View::e($r['client_nom'] ?? '—') . '</td>'
                . '<td>' . View::e($r['facture_numero'] ?? '—') . '</td>'
                . '<td style="text-align:right; font-weight:800; color:#16a34a;">' . number_format((float)$r['montant_xof'], 0, ',', ' ') . ' XOF</td>'
                . '<td style="text-align:center;">' . Ui::badge($r['statut'], $tone) . '</td>'
                . '<td style="text-align:center;">'
                . '<form method="post" action="' . View::url('finance/rapprochement-mobile-money/valider') . '" style="display:inline;">'
                . Form::hidden('_csrf_token', \App\Helpers\Csrf::token())
                . Form::hidden('id', (string)$r['id'])
                . Form::hidden('statut', 'RAPPROCHÉ')
                . '<button type="submit" class="finea-button finea-button--outline" style="padding:4px 8px; font-size:11px;">Valider 1-Clic</button>'
                . '</form>'
                . '</td>'
                . '</tr>';
        }

        $tfoot = '<tfoot style="background:#f8fafc; border-top:2px solid #cbd5e1; font-weight:800; color:#0f172a;">'
            . '<tr>'
            . '<td colspan="5" style="padding:10px 12px;">Total Flux Mobile Money (' . count($reconciliations) . ' flux)</td>'
            . '<td style="text-align:right; padding:10px 12px; color:#16a34a; font-size:0.95rem;">' . number_format($totRapproche + $totAttente, 0, ',', ' ') . ' XOF</td>'
            . '<td colspan="2"></td>'
            . '</tr>'
            . '</tfoot>';

        return '<div class="finea-shell"><div class="finea-container">'
            . $header
            . $kpiHtml
            . '<div class="finea-section-card">'
            . '<h3 style="font-weight:800; font-size:1.1rem; margin-bottom:1rem;">Transactions Mobile Money & Relevés en Attente</h3>'
            . '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>Date</th><th>Opérateur</th><th>N° Transaction</th><th>Client</th><th>Facture LBP</th><th style="text-align:right;">Montant Reçu</th><th style="text-align:center;">Statut</th><th style="text-align:center;">Action</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody>' . $tfoot . '</table></div></div>'
            . '</div></div>';
    }

    public static function tresoreriePage(float $totalEncaissementsPrevus, float $totalDecaissementsPrevus, float $soldeTrésorerieEstime): string
    {
        $header = Ui::pageHeader(
            'Trésorerie Prévisionnelle & Cashflow (30/60/90 jours)',
            'Anticipation des encaissements clients et des décaissements prestataires / douane.',
            'FINANCE',
            [
                ['label' => 'Tableau de bord', 'href' => 'finance/dashboard', 'variant' => 'outline'],
                ['label' => 'Trésorerie', 'href' => 'finance/tresorerie', 'variant' => 'primary'],
            ]
        );

        $soldeTone = $soldeTrésorerieEstime >= 0 ? '#16a34a' : '#dc2626';

        $kpis = '<div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">'
            . '<div style="background:#ffffff; border:1px solid #e2e8f0; border-left:5px solid #16a34a; padding:1.25rem; border-radius:12px;">'
            . '<div style="font-size:0.75rem; font-weight:800; color:#64748b; text-transform:uppercase;">Encaissements Prévisionnels (Rentrées)</div>'
            . '<div style="font-size:1.8rem; font-weight:900; color:#16a34a; margin-top:0.4rem;">+' . number_format($totalEncaissementsPrevus, 0, ',', ' ') . ' XOF</div>'
            . '</div>'
            . '<div style="background:#ffffff; border:1px solid #e2e8f0; border-left:5px solid #dc2626; padding:1.25rem; border-radius:12px;">'
            . '<div style="font-size:0.75rem; font-weight:800; color:#64748b; text-transform:uppercase;">Décaissements Prévus (Douane / Fret)</div>'
            . '<div style="font-size:1.8rem; font-weight:900; color:#dc2626; margin-top:0.4rem;">-' . number_format($totalDecaissementsPrevus, 0, ',', ' ') . ' XOF</div>'
            . '</div>'
            . '<div style="background:#ffffff; border:1px solid #e2e8f0; border-left:5px solid ' . $soldeTone . '; padding:1.25rem; border-radius:12px;">'
            . '<div style="font-size:0.75rem; font-weight:800; color:#64748b; text-transform:uppercase;">Trésorerie Nette Estimée</div>'
            . '<div style="font-size:1.8rem; font-weight:900; color:' . $soldeTone . '; margin-top:0.4rem;">' . number_format($soldeTrésorerieEstime, 0, ',', ' ') . ' XOF</div>'
            . '</div>'
            . '</div>';

        $tableHorizon = '<div class="finea-section-card" style="margin-top:1.5rem;">'
            . '<h3 style="font-weight:800; font-size:1.1rem; margin-bottom:1rem;">Ventilation du Cashflow par Horizons Temporels</h3>'
            . '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>Horizon Temporel</th><th style="text-align:right;">Rentrées Prévues (Factures)</th><th style="text-align:right;">Sorties Prévues (Fournisseurs)</th><th style="text-align:right;">Impact Cashflow Net</th>'
            . '</tr></thead><tbody>'
            . '<tr>'
            . '<td><strong>Court Terme (0 à 30 jours)</strong></td>'
            . '<td style="text-align:right; color:#16a34a; font-weight:700;">+' . number_format($totalEncaissementsPrevus * 0.6, 0, ',', ' ') . ' XOF</td>'
            . '<td style="text-align:right; color:#dc2626; font-weight:700;">-' . number_format($totalDecaissementsPrevus * 0.8, 0, ',', ' ') . ' XOF</td>'
            . '<td style="text-align:right; font-weight:800; color:' . ($totalEncaissementsPrevus*0.6 >= $totalDecaissementsPrevus*0.8 ? '#16a34a' : '#dc2626') . ';">' . number_format(($totalEncaissementsPrevus*0.6) - ($totalDecaissementsPrevus*0.8), 0, ',', ' ') . ' XOF</td>'
            . '</tr>'
            . '<tr>'
            . '<td><strong>Moyen Terme (31 à 60 jours)</strong></td>'
            . '<td style="text-align:right; color:#16a34a; font-weight:700;">+' . number_format($totalEncaissementsPrevus * 0.25, 0, ',', ' ') . ' XOF</td>'
            . '<td style="text-align:right; color:#dc2626; font-weight:700;">-' . number_format($totalDecaissementsPrevus * 0.15, 0, ',', ' ') . ' XOF</td>'
            . '<td style="text-align:right; font-weight:800; color:#16a34a;">+' . number_format(($totalEncaissementsPrevus*0.25) - ($totalDecaissementsPrevus*0.15), 0, ',', ' ') . ' XOF</td>'
            . '</tr>'
            . '<tr>'
            . '<td><strong>Long Terme (61 à 90+ jours)</strong></td>'
            . '<td style="text-align:right; color:#16a34a; font-weight:700;">+' . number_format($totalEncaissementsPrevus * 0.15, 0, ',', ' ') . ' XOF</td>'
            . '<td style="text-align:right; color:#dc2626; font-weight:700;">-' . number_format($totalDecaissementsPrevus * 0.05, 0, ',', ' ') . ' XOF</td>'
            . '<td style="text-align:right; font-weight:800; color:#16a34a;">+' . number_format(($totalEncaissementsPrevus*0.15) - ($totalDecaissementsPrevus*0.05), 0, ',', ' ') . ' XOF</td>'
            . '</tr>'
            . '</tbody>'
            . '<tfoot style="background:#f8fafc; border-top:2px solid #cbd5e1; font-weight:900;"><tr>'
            . '<td>Total Cumulé 90j</td>'
            . '<td style="text-align:right; color:#16a34a;">+' . number_format($totalEncaissementsPrevus, 0, ',', ' ') . ' XOF</td>'
            . '<td style="text-align:right; color:#dc2626;">-' . number_format($totalDecaissementsPrevus, 0, ',', ' ') . ' XOF</td>'
            . '<td style="text-align:right; color:' . $soldeTone . ';">' . number_format($soldeTrésorerieEstime, 0, ',', ' ') . ' XOF</td>'
            . '</tr></tfoot>'
            . '</table></div></div>';

        return '<div class="finea-shell"><div class="finea-container">'
            . $header
            . $kpis
            . '<div class="finea-section-card">'
            . '<h3 style="font-weight:800; font-size:1.1rem; margin-bottom:0.5rem;">Analyse Prévisionnelle de la Trésorerie LBP</h3>'
            . '<p style="color:#64748b; font-size:0.9rem;">La trésorerie nette estimée prend en compte l\'ensemble des créances clients à recouvrer par rapport aux engagements de décaissement douaniers et prestataires en attente de validation.</p>'
            . '</div>'
            . $tableHorizon
            . '</div></div>';
    }

    public static function balanceComptesPage(array $balance, array $filters = []): string
    {
        $header = Ui::pageHeader(
            'Balance Générale des Comptes (SYSCOHADA)',
            'Récapitulatif synthétique des débits, crédits et soldes nets par compte comptable.',
            [
                'eyebrow' => 'General Ledger Balance',
                'class' => 'rh-hero-white',
            ]
        );

        $debut = Form::input('date_debut', ['label' => 'Date Début', 'type' => 'date', 'value' => $filters['date_debut'] ?? '']);
        $fin = Form::input('date_fin', ['label' => 'Date Fin', 'type' => 'date', 'value' => $filters['date_fin'] ?? '']);
        $searchBtn = '<button type="submit" class="rh-filter-btn rh-filter-btn--primary">Rechercher</button>';
        $resetBtn = '<a href="' . View::url('finance/balance-comptes') . '" class="rh-filter-btn rh-filter-btn--reset">Réinitialiser</a>';

        $form = '<form method="get" action="' . View::url('finance/balance-comptes') . '" class="rh-personnel-filters">'
            . '<div class="rh-personnel-filter-grid">' . $debut . $fin . '</div>'
            . '<div class="rh-personnel-filter-actions">' . $searchBtn . $resetBtn . '</div>'
            . '</form>';

        $rows = '';
        $totDeb = 0.0;
        $totCred = 0.0;
        $totSoldeDeb = 0.0;
        $totSoldeCred = 0.0;

        foreach ($balance as $b) {
            $totDeb += (float) $b['total_debit'];
            $totCred += (float) $b['total_credit'];
            $totSoldeDeb += (float) $b['solde_debiteur'];
            $totSoldeCred += (float) $b['solde_crediteur'];

            $sDeb = $b['solde_debiteur'] > 0 ? number_format((float)$b['solde_debiteur'], 2, ',', ' ') . ' XOF' : '—';
            $sCred = $b['solde_crediteur'] > 0 ? number_format((float)$b['solde_crediteur'], 2, ',', ' ') . ' XOF' : '—';

            $rows .= '<tr style="border-bottom:1px solid #f1f5f9;">'
                . '<td><code style="background:#0f172a; color:#fff; padding:2px 6px; border-radius:4px; font-weight:800;">' . View::e($b['code']) . '</code></td>'
                . '<td><strong>' . View::e($b['libelle']) . '</strong></td>'
                . '<td style="text-align:center;"><span class="finea-badge">' . View::e($b['classe']) . '</span></td>'
                . '<td style="text-align:right; font-weight:600;">' . number_format((float)$b['total_debit'], 2, ',', ' ') . ' XOF</td>'
                . '<td style="text-align:right; font-weight:600;">' . number_format((float)$b['total_credit'], 2, ',', ' ') . ' XOF</td>'
                . '<td style="text-align:right; font-weight:800; color:#2563eb;">' . $sDeb . '</td>'
                . '<td style="text-align:right; font-weight:800; color:#dc2626;">' . $sCred . '</td>'
                . '</tr>';
        }

        $tfoot = '<tfoot style="background:#f8fafc; border-top:2px solid #cbd5e1; font-weight:900; color:#0f172a;">'
            . '<tr>'
            . '<td colspan="3" style="padding:12px;">Total Général (' . count($balance) . ' comptes)</td>'
            . '<td style="text-align:right; padding:12px;">' . number_format($totDeb, 2, ',', ' ') . ' XOF</td>'
            . '<td style="text-align:right; padding:12px;">' . number_format($totCred, 2, ',', ' ') . ' XOF</td>'
            . '<td style="text-align:right; padding:12px; color:#2563eb;">' . number_format($totSoldeDeb, 2, ',', ' ') . ' XOF</td>'
            . '<td style="text-align:right; padding:12px; color:#dc2626;">' . number_format($totSoldeCred, 2, ',', ' ') . ' XOF</td>'
            . '</tr>'
            . '</tfoot>';

        $tableHtml = '<div class="finea-table-wrapper">'
            . '<table class="finea-table">'
            . '<thead style="background:#0f172a; color:#ffffff;">'
            . '<tr>'
            . '<th>Compte</th><th>Intitulé du compte</th><th style="text-align:center;">Classe</th><th style="text-align:right;">Total Débit</th><th style="text-align:right;">Total Crédit</th><th style="text-align:right;">Solde Débiteur</th><th style="text-align:right;">Solde Créditeur</th>'
            . '</tr>'
            . '</thead>'
            . '<tbody>' . $rows . '</tbody>'
            . $tfoot
            . '</table></div>';

        return '<div class="finea-shell"><div class="finea-container">'
            . $header
            . $form
            . '<div class="finea-section-card" style="margin-top:1.5rem;">'
            . '<div class="finea-section-heading"><h2 class="finea-section-title">Balance de Vérification SYSCOHADA</h2></div>'
            . $tableHtml
            . '</div></div></div>';
    }

    public static function planComptablePage(array $plan): string
    {
        $header = Ui::pageHeader(
            'Plan Comptable SYSCOHADA',
            'Nomenclature et codification des comptes généraux de l\'entreprise.',
            [
                'eyebrow' => 'Chart of Accounts',
                'class' => 'rh-hero-white',
            ]
        );

        $fields = Form::input('code', ['label' => 'Numéro de Compte (ex: 601300)', 'placeholder' => '601300', 'required' => true])
            . Form::input('libelle', ['label' => 'Intitulé du Compte', 'placeholder' => 'Achats de carburant', 'required' => true])
            . Form::select('classe', [
                ['value' => '1', 'label' => 'Classe 1 — Ressources durables'],
                ['value' => '2', 'label' => 'Classe 2 — Actifs immobilisés'],
                ['value' => '3', 'label' => 'Classe 3 — Stocks'],
                ['value' => '4', 'label' => 'Classe 4 — Tiers (Clients / Fournisseurs)'],
                ['value' => '5', 'label' => 'Classe 5 — Trésorerie (Banques / Caisses)'],
                ['value' => '6', 'label' => 'Classe 6 — Charges'],
                ['value' => '7', 'label' => 'Classe 7 — Produits'],
            ], '6', ['label' => 'Classe Comptable', 'required' => true]);

        $modalHtml = Ui::modal('modal-add-account', 'Ajouter un Compte Comptable', $fields, View::url('finance/plan-comptable/enregistrer'), [
            'btnLabel' => 'Enregistrer le Compte',
            'btnVariant' => 'accent',
        ]);

        $rows = '';
        foreach ($plan as $p) {
            $rows .= '<tr>'
                . '<td><code style="background:#0f172a; color:#fff; padding:3px 8px; border-radius:4px; font-weight:900;">' . View::e($p['code']) . '</code></td>'
                . '<td><strong>' . View::e($p['libelle']) . '</strong></td>'
                . '<td style="text-align:center;"><span class="finea-badge">Classe ' . View::e($p['classe']) . '</span></td>'
                . '</tr>';
        }

        $tableHtml = '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>Code Compte</th><th>Intitulé du Compte</th><th style="text-align:center;">Classe</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';

        return '<div class="finea-shell"><div class="finea-container">'
            . $header
            . '<div style="margin-bottom:1.5rem; text-align:right;"><button class="finea-button finea-button--accent" onclick="document.getElementById(\'modal-add-account\').showModal()">+ Ajouter un Compte SYSCOHADA</button></div>'
            . '<div class="finea-section-card">'
            . '<h3 style="font-weight:800; font-size:1.1rem; margin-bottom:1rem;">Nomenclature des Comptes Actifs (' . count($plan) . ' comptes)</h3>'
            . $tableHtml
            . '</div>'
            . $modalHtml
            . '</div></div>';
    }

    public static function guidePage(): string
    {
        $header = Ui::pageHeader(
            'Guide Interactif & Tutoriel Complet — Module Finance',
            'Manuel opératoire détaillé pas-à-pas pour les Caissières, la Caissière Principale et les Comptables.',
            [
                'eyebrow' => 'Manuel d\'Utilisation & Formation',
                'class' => 'rh-hero-white',
            ]
        );

        $script = '<script>
        function openGuideTab(evt, tabId) {
            var i, tabcontent, tablinks;
            tabcontent = document.getElementsByClassName("guide-tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].style.display = "none";
            }
            tablinks = document.getElementsByClassName("guide-tab-btn");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].className = tablinks[i].className.replace(" active-tab", "");
            }
            document.getElementById(tabId).style.display = "block";
            evt.currentTarget.className += " active-tab";
        }
        </script>
        <style>
        .guide-tab-bar { display: flex; gap: 0.5rem; border-bottom: 2px solid #e2e8f0; margin-bottom: 1.5rem; overflow-x: auto; padding-bottom: 4px; }
        .guide-tab-btn { background: none; border: none; padding: 10px 16px; font-weight: 700; font-size: 0.9rem; color: #64748b; cursor: pointer; border-radius: 8px 8px 0 0; transition: all 0.2s; white-space: nowrap; }
        .guide-tab-btn:hover { background: #f1f5f9; color: #0f172a; }
        .guide-tab-btn.active-tab { background: #2563eb; color: #ffffff; box-shadow: 0 4px 12px rgba(37,99,235,0.2); }
        .guide-tab-content { display: none; }
        .guide-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
        .guide-card h3 { color: #0f172a; font-weight: 800; font-size: 1.15rem; margin-top: 0; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 8px; }
        .guide-step-badge { background: #2563eb; color: #ffffff; width: 26px; height: 26px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 800; }
        .guide-alert { padding: 1rem 1.25rem; border-radius: 10px; font-size: 0.9rem; margin: 1rem 0; line-height: 1.5; }
        .guide-alert--info { background: #eff6ff; border-left: 4px solid #2563eb; color: #1e40af; }
        .guide-alert--warning { background: #fffbe6; border-left: 4px solid #d97706; color: #92400e; }
        .guide-alert--danger { background: #fef2f2; border-left: 4px solid #dc2626; color: #991b1b; }
        .guide-alert--success { background: #f0fdf4; border-left: 4px solid #16a34a; color: #166534; }
        .guide-list { margin: 0; padding-left: 1.25rem; line-height: 1.7; color: #334155; }
        </style>';

        // ONGLET 1 : Introduction & Concept
        $tab1 = '<div id="tab-intro" class="guide-tab-content" style="display: block;">'
            . '<div class="guide-card">'
            . '<h3><span class="guide-step-badge">1</span> Bienvenue dans le Module Finance ERP LBP</h3>'
            . '<p style="color:#475569; line-height:1.6;">Le module Finance assure la traçabilité intégrale des flux d\'argent de La Belle Porte (LBP), de la saisie d\'encaissement au guichet jusqu\'à la génération du bilan comptable SYSCOHADA.</p>'
            . '<div class="guide-alert guide-alert--info">💡 <strong>Objectif principal :</strong> Garantir une étanchéité totale entre la gestion opérationnelle de la caisse (Caissières), le contrôle de centralisation (Caissière Principale) et le livre-journal légal (Comptables).</div>'
            . '</div>'
            . '<div class="guide-card">'
            . '<h3>🔒 Séparation des Tâches (SoD - Segregation of Duties)</h3>'
            . '<ul class="guide-list">'
            . '<li><strong>Principe de Double Contrôle :</strong> Une caissière qui soumet son point de caisse d\'agence ne peut pas le valider/consolider elle-même. La consolidation est réservée à la Caissière Principale.</li>'
            . '<li><strong>Règlement des Dépenses :</strong> Un superviseur régional qui formule une demande de paiement prestataire ne peut pas effectuer le décaissement lui-même.</li>'
            . '<li><strong>Inaltérabilité Comptable :</strong> Les écritures comptables générées ne peuvent pas être supprimées arbitrairement. Toute correction s\'effectue par <strong>Contre-passation d\'écriture</strong> traçable.</li>'
            . '</ul>'
            . '</div>'
            . '<div class="guide-card">'
            . '<h3>🔀 Gestion Multi-devises (XOF & EUR)</h3>'
            . '<p style="color:#475569;">LBP gère les opérations en <strong>XOF (Franc CFA)</strong> et en <strong>EUR (Euro)</strong> pour les colis en provenance/destination de France ou du Canada. Toutes les sommes sont converties automatiquement au taux officiel (1 EUR = 655.957 XOF) dans le grand-livre pour la comptabilité locale.</p>'
            . '</div>'
            . '</div>';

        // ONGLET 2 : Guide Caissière
        $tab2 = '<div id="tab-caissiere" class="guide-tab-content">'
            . '<div class="guide-card">'
            . '<h3><span class="guide-step-badge">1</span> Enregistrement d\'un Encaissement Client</h3>'
            . '<ol class="guide-list">'
            . '<li>Allez dans le menu <strong>Factures Clients</strong> (`/finance/factures`).</li>'
            . '<li>Recherchez la facture par son numéro ou le nom du client.</li>'
            . '<li>Cliquez sur <strong>"Enregistrer un Paiement"</strong>.</li>'
            . '<li>Sélectionnez le mode de paiement (Espèces, Wave, Orange Money, Virement).</li>'
            . '<li>Validez : Le reçu de paiement PDF est généré et la caisse en temps réel est mise à jour instantanément.</li>'
            . '</ol>'
            . '</div>'
            . '<div class="guide-card">'
            . '<h3><span class="guide-step-badge">2</span> Consultation de la Position Live & PDF de la Journée</h3>'
            . '<p style="color:#475569;">À tout moment de la journée, accédez à la page <strong>Points de Caisse</strong> (`/finance/clotures`).</p>'
            . '<div class="guide-alert guide-alert--success">🖨️ <strong>Nouveauté PDF Temps Réel :</strong> Cliquez sur le bouton bleu <strong>"🖨️ PDF de la Journée"</strong> dans l\'en-tête de votre position pour imprimer une fiche de caisse intermédiaire non officielle à n\'importe quelle heure.</div>'
            . '</div>'
            . '<div class="guide-card">'
            . '<h3><span class="guide-step-badge">3</span> Clôture & Décompte Physique des Billets (Le Soir)</h3>'
            . '<div class="guide-alert guide-alert--warning">⚠️ <strong>Procédure de Fin de Journée :</strong></div>'
            . '<ol class="guide-list">'
            . '<li>Remplissez la grille de comptage de billets (ex: 10 billets de 10 000 XOF, 5 de 5 000 XOF...).</li>'
            . '<li>Le système calcule le <strong>Solde Physique Déclaré</strong> en temps réel.</li>'
            . '<li>En cas d\'écart avec le solde théorique calculé par les factures, saisissez obligatoire une <strong>explication d\'écart</strong>.</li>'
            . '<li>Cliquez sur <strong>"Soumettre et Verrouiller ma Caisse"</strong>. Une confirmation s\'affiche pour éviter les clics accidentels.</li>'
            . '</ol>'
            . '<div class="guide-alert guide-alert--danger">⛔ <strong>Attention :</strong> Une fois la caisse soumise, elle est verrouillée pour la journée et passe en attente de validation par la Caissière Principale.</div>'
            . '</div>'
            . '</div>';

        // ONGLET 3 : Caissière Principale
        $tab3 = '<div id="tab-principale" class="guide-tab-content">'
            . '<div class="guide-card">'
            . '<h3><span class="guide-step-badge">1</span> Supervision des Points de Caisse Multi-Agences</h3>'
            . '<p style="color:#475569;">La Caissière Principale dispose d\'une vue d\'ensemble sur les caisses de toutes les agences (Abidjan, San Pedro, Paris, etc.).</p>'
            . '<ul class="guide-list">'
            . '<li>Accédez au menu <strong>Points de Caisse</strong>.</li>'
            . '<li>Utilisez le sélecteur d\'agence pour basculer entre les fiches de caisse.</li>'
            . '<li>Téléchargez le <strong>PDF de Synthèse Globale Multi-Agences</strong> via le bouton vert en haut de page.</li>'
            . '</ul>'
            . '</div>'
            . '<div class="guide-card">'
            . '<h3><span class="guide-step-badge">2</span> Consolidation et Génération Écritures Comptables</h3>'
            . '<ol class="guide-list">'
            . '<li>Vérifiez l\'exactitude du solde physique déclaré et l\'explication en cas d\'écart.</li>'
            . '<li>Cliquez sur <strong>"Consolider le Point de Caisse"</strong>.</li>'
            . '<li>Le système génère automatiquement les écritures de centralisation au journal de caisse (Compte 571200).</li>'
            . '</ol>'
            . '</div>'
            . '<div class="guide-card">'
            . '<h3><span class="guide-step-badge">3</span> Paiement des Dépenses Prestataires</h3>'
            . '<p style="color:#475569;">Sur la page <strong>Dépenses Prestataires</strong> (`/finance/depenses`), examinez les demandes de décaissement soumises par les superviseurs régionaux. Cliquez sur <strong>"Payer"</strong> pour valider le décaissement ou <strong>"Rejeter"</strong> avec motif.</p>'
            . '</div>'
            . '</div>';

        // ONGLET 4 : Guide Comptable
        $tab4 = '<div id="tab-comptable" class="guide-tab-content">'
            . '<div class="guide-card">'
            . '<h3><span class="guide-step-badge">1</span> Consultation du Livre-Journal</h3>'
            . '<p style="color:#475569;">Accédez au menu <strong>Livre Journal</strong> (`/finance/comptabilite`). Filtrer par journal (Ventes, Caisses, Achats, Banque, OD), compte ou période.</p>'
            . '<div class="guide-alert guide-alert--success">📊 <strong>Ligne de Totaux `tfoot` :</strong> Le bas du tableau affiche le nombre d\'écritures filtrées et le montant total cumulé en XOF.</div>'
            . '</div>'
            . '<div class="guide-card">'
            . '<h3><span class="guide-step-badge">2</span> Saisie d\'Écritures Manuelles (OD / Régularisations)</h3>'
            . '<ol class="guide-list">'
            . '<li>Cliquez sur le bouton bleu <strong>"+ Écriture Manuelle (OD)"</strong>.</li>'
            . '<li>Saisissez la date, le journal, le compte à débiter (+), le compte à créditer (-), le montant XOF et le libellé explicatif.</li>'
            . '<li>Validez : L\'écriture est enregistrée et immédiatement prise en compte dans le grand-livre.</li>'
            . '</ol>'
            . '</div>'
            . '<div class="guide-card">'
            . '<h3><span class="guide-step-badge">3</span> Contre-passation d\'Écriture en 1-Clic</h3>'
            . '<p style="color:#475569;">En cas d\'erreur sur une écriture enregistrée :</p>'
            . '<ol class="guide-list">'
            . '<li>Cliquez sur le bouton rouge <strong>"Contre-passer"</strong> sur la ligne de l\'écriture erronée.</li>'
            . '<li>Confirmez : Le système génère automatiquement l\'écriture inverse exacte (inversion des comptes) avec la référence `EXT-...` et marque l\'écriture originale avec le lettrage `EXT`.</li>'
            . '</ol>'
            . '</div>'
            . '<div class="guide-card">'
            . '<h3><span class="guide-step-badge">4</span> Export SYSCOHADA (CSV pour Sage / Odoo / Cegid)</h3>'
            . '<p style="color:#475569;">Cliquez sur <strong>"Exporter SYSCOHADA (CSV)"</strong>. Le fichier généré inclut le BOM UTF-8 (pour Excel) et respecte les filtres de date/journal sélectionnés à l\'écran.</p>'
            . '</div>'
            . '</div>';

        // ONGLET 5 : Balance & Plan
        $tab5 = '<div id="tab-balance" class="guide-tab-content">'
            . '<div class="guide-card">'
            . '<h3><span class="guide-step-badge">1</span> Balance Générale des Comptes</h3>'
            . '<p style="color:#475569;">La page <strong>Balance des Comptes</strong> (`/finance/balance-comptes`) récapitule pour chaque compte :</p>'
            . '<ul class="guide-list">'
            . '<li>Le cumul total des Débits</li>'
            . '<li>Le cumul total des Crédits</li>'
            . '<li>Le <strong>Solde Débiteur</strong> (en bleu) ou <strong>Solde Créditeur</strong> (en rouge)</li>'
            . '</ul>'
            . '<div class="guide-alert guide-alert--info">⚖️ <strong>Vérification d\'Équilibre :</strong> Le pied de page confirme que le total général des débits est exactement égal au total général des crédits.</div>'
            . '</div>'
            . '<div class="guide-card">'
            . '<h3><span class="guide-step-badge">2</span> Nomenclature du Plan Comptable SYSCOHADA</h3>'
            . '<p style="color:#475569;">Accédez au menu <strong>Plan Comptable</strong> (`/finance/plan-comptable`).</p>'
            . '<ul class="guide-list">'
            . '<li>Consultez les 30+ comptes pré-configurés (Classes 1 à 7).</li>'
            . '<li>Cliquez sur <strong>"+ Ajouter un Compte SYSCOHADA"</strong> pour créer un nouveau compte spécifique (ex: `601300 - Carburant flotte`).</li>'
            . '</ul>'
            . '</div>'
            . '</div>';

        // ONGLET 6 : Pilotage & Trésorerie
        $tab6 = '<div id="tab-pilotage" class="guide-tab-content">'
            . '<div class="guide-card">'
            . '<h3><span class="guide-step-badge">1</span> P&L Rentabilité par Trajet / Lot</h3>'
            . '<p style="color:#475569;">Sur la page <strong>Rentabilité (P&L)</strong> (`/finance/rentabilite`), analysez le résultat net de chaque ligne de transport (ex: LB-FR Aérien) : Recettes Facturées - Débours Prestataires/Douane = Marge Nette & Taux de Marge %.</p>'
            . '</div>'
            . '<div class="guide-card">'
            . '<h3><span class="guide-step-badge">2</span> Balance Âgée & Recouvrement Créances</h3>'
            . '<p style="color:#475569;">Sur la page <strong>Balance Âgée</strong> (`/finance/balance-agee`), suivez les impayés clients ventilés par tranches : 0-30j (récent), 31-60j, 61-90j et +90j (alerte recouvrement).</p>'
            . '</div>'
            . '<div class="guide-card">'
            . '<h3><span class="guide-step-badge">3</span> Projection Prévisionnelle de Trésorerie</h3>'
            . '<p style="color:#475569;">La page <strong>Trésorerie & Cashflow</strong> (`/finance/tresorerie`) anticipe les flux de trésorerie nette sur 3 horizons : <strong>Court Terme (0-30j)</strong>, <strong>Moyen Terme (31-60j)</strong> et <strong>Long Terme (61-90j)</strong>.</p>'
            . '</div>'
            . '<div class="guide-card">'
            . '<h3><span class="guide-step-badge">4</span> Rapprochement Mobile Money & Banque</h3>'
            . '<p style="color:#475569;">Sur la page <strong>Rapprochement Mobile</strong> (`/finance/rapprochement-mobile-money`), comparez les montants reçus via Wave/Orange Money avec les factures et validez en 1-clic.</p>'
            . '</div>'
            . '</div>';

        $navigation = '<div class="guide-tab-bar">'
            . '<button class="guide-tab-btn active-tab" onclick="openGuideTab(event, \'tab-intro\')">🏠 Vue d\'Ensemble & SoD</button>'
            . '<button class="guide-tab-btn" onclick="openGuideTab(event, \'tab-caissiere\')">💵 Guide Caissière</button>'
            . '<button class="guide-tab-btn" onclick="openGuideTab(event, \'tab-principale\')">📑 Caissière Principale</button>'
            . '<button class="guide-tab-btn" onclick="openGuideTab(event, \'tab-comptable\')">📊 Guide Comptable</button>'
            . '<button class="guide-tab-btn" onclick="openGuideTab(event, \'tab-balance\')">⚖️ Balance & Plan SYSCOHADA</button>'
            . '<button class="guide-tab-btn" onclick="openGuideTab(event, \'tab-pilotage\')">📈 Pilotage & Trésorerie</button>'
            . '</div>';

        return $script
            . '<div class="finea-shell"><div class="finea-container">'
            . $header
            . $navigation
            . $tab1 . $tab2 . $tab3 . $tab4 . $tab5 . $tab6
            . '</div></div>';
    }
}
