<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use App\View\Components\Dashboard;
use App\View\Components\Ui;
use App\View\Components\Form;
use App\View\Components\Rh;
use App\View\Pages\Facturation\DashboardPage;

final class Facturation
{
    public static function dashboardPage(DashboardPage $page, array $dashboardModule): string
    {
        $header = Dashboard::header(
            $dashboardModule['label'],
            $dashboardModule['description'],
            [
                'eyebrow' => $dashboardModule['code'] . ' Dashboard',
                'class' => 'rh-hero-white'
            ]
        );

        $kpis = Dashboard::kpis($page->kpis);
        $introContent = '<p style="color: #64748b; margin: 0 0 1.25rem 0;">'
            . 'Cet espace permet de rechercher les factures émises par période, agence et trajet '
            . '(avec montants), et de consulter ou modifier une facture verrouillée avec traçabilité '
            . 'complète — réservé au rôle Responsable.'
            . '</p>'
            . Ui::button('Ouvrir Filtre & Recherche', ['href' => 'facturation/filtre', 'variant' => 'accent']);
        $intro = Ui::section('Recherche & Audit des Factures', $introContent);
        $actions = Dashboard::actions($page->quickActions, [
            'title' => 'Actions rapides',
            'class' => 'finea-section-card',
        ]);

        return '<div class="finea-shell facturation-dashboard">'
            . '<div class="finea-container">'
            . $header
            . '<div class="rh-dashboard-grid" style="margin-top: 2rem;">'
            . '<div class="rh-dashboard-main">'
            . $kpis
            . '<div style="margin-top: 2rem;">'
            . $intro
            . '</div>'
            . '</div>'
            . '<div class="rh-dashboard-side">'
            . $actions
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';
    }

    /**
     * Écran de filtre/recherche par période (mois/année début-fin), agence et trajet,
     * avec montants (Règle point 5 du cahier des charges — contrairement à l'export Logistique/Colisage).
     *
     * @param array<int, array<string, mixed>> $sites
     * @param array<int, array<string, mixed>> $trajets
     * @param array<int, array<string, mixed>> $results
     * @param array<string, mixed> $kpis
     * @param array{currentPage: int, totalPages: int, itemsPerPage: int, totalItems: int} $pagination
     */
    public static function filtrePage(
        int $startMonth,
        int $startYear,
        int $endMonth,
        int $endYear,
        int $selectedAgenceId,
        string $selectedCategorie = 'all',
        string $selectedStatutPaiement = 'all',
        string $dateFrom = '',
        string $dateTo = '',
        string $searchQuery = '',
        bool $canSeeAllAgencies = true,
        array $sites = [],
        array $trajets = [],
        array $results = [],
        array $kpis = [],
        array $pagination = ['currentPage' => 1, 'totalPages' => 1, 'itemsPerPage' => 50, 'totalItems' => 0]
    ): string {
        $months = View::monthNames();
        $years = range((int) date('Y') - 2, (int) date('Y') + 1);

        $exportQuery = http_build_query(array_filter([
            'start_month'     => $startMonth,
            'start_year'      => $startYear,
            'end_month'       => $endMonth,
            'end_year'        => $endYear,
            'date_from'       => $dateFrom,
            'date_to'         => $dateTo,
            'agence_id'       => $selectedAgenceId,
            'categorie_code'  => $selectedCategorie,
            'statut_paiement' => $selectedStatutPaiement,
            'q'               => $searchQuery,
        ]));

        $header = Ui::pageHeader(
            'Vue d\'ensemble & Recherche Facturation',
            'Filtrage avancé des créances et factures par catégorie (Cargo, Rapide, DHL), agence et statut de paiement.',
            [
                'eyebrow' => 'Facturation • Pilotage des Encaissements & Impayés',
                'class' => 'rh-hero-white',
                'actions' => [
                    Ui::button('🖨️ Export PDF Officiel', ['href' => 'facturation/filtre/export-pdf?' . $exportQuery, 'variant' => 'danger', 'target' => '_blank']),
                    Ui::button('📊 Export Excel (CSV UTF-8)', ['href' => 'facturation/filtre/export-excel?' . $exportQuery, 'variant' => 'accent']),
                ],
            ]
        );

        $monthOpts = static function (int $selected) use ($months): array {
            $opts = [];
            foreach ($months as $num => $name) {
                $opts[] = ['value' => (string) $num, 'label' => $name, 'attrs' => $num === $selected ? ['selected' => true] : []];
            }
            return $opts;
        };
        $yearOpts = static function (int $selected) use ($years): array {
            $opts = [];
            foreach ($years as $y) {
                $opts[] = ['value' => (string) $y, 'label' => (string) $y, 'attrs' => $y === $selected ? ['selected' => true] : []];
            }
            return $opts;
        };

        $agenceOpts = [['value' => '0', 'label' => 'Toutes les agences']];
        foreach ($sites as $s) {
            $agenceOpts[] = ['value' => (string) $s['id'], 'label' => $s['name'] . ' (' . $s['code'] . ')'];
        }

        // Catégories groupées avec optgroup
        $categoriesGroups = [
            ['value' => 'all', 'label' => 'Toutes les catégories'],
            ['value' => 'groupage_cargo', 'label' => '✈️ Tout le Groupage Cargo (Tous codes)'],
            ['value' => 'colis_rapide', 'label' => '⚡ Tout le Colis Rapide (Tous codes)'],
            ['value' => 'dhl', 'label' => '🚚 DHL / Express'],
            ['value' => 'autres', 'label' => 'Autres / Transit'],
            ['value' => 'LB-CI', 'label' => '↳ LB-CI : Abidjan ➔ France'],
            ['value' => 'LB-FR', 'label' => '↳ LB-FR : France ➔ Abidjan'],
            ['value' => 'S-FR', 'label' => '↳ S-FR : Sénégal ➔ France'],
            ['value' => 'S-CI', 'label' => '↳ S-CI : Sénégal ➔ Côte d\'Ivoire'],
            ['value' => 'LB-CA', 'label' => '↳ LB-CA : Abidjan ➔ Canada'],
            ['value' => 'F-SN', 'label' => '↳ F-SN : France ➔ Sénégal'],
            ['value' => 'CA-CI', 'label' => '↳ CA-CI : Abidjan ➔ Paris (Rapide)'],
            ['value' => 'CA-FR', 'label' => '↳ CA-FR : Paris ➔ Abidjan (Rapide)'],
        ];

        $statutPaiementOpts = [
            ['value' => 'all', 'label' => 'Tous les statuts'],
            ['value' => 'impayes', 'label' => '🔴 Impayés uniquement (Reste > 0)'],
            ['value' => 'partiellement_payee', 'label' => '🟡 Partiellement payés'],
            ['value' => 'payee', 'label' => '🟢 Payés en totalité'],
        ];

        $filterForm = '<form method="get" action="' . View::url('facturation/filtre') . '" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:1.25rem;">'
            . '<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:1rem; align-items:end;">'
            
            // Statut de paiement (Impayés mis en avant)
            . '<div>'
            . '<label style="display:block; font-size:0.75rem; font-weight:800; text-transform:uppercase; color:#0f172a; margin-bottom:0.35rem;">Statut Paiement</label>'
            . Form::select('statut_paiement', $statutPaiementOpts, $selectedStatutPaiement, ['class' => 'finea-select', 'style' => 'width:100%; font-weight:700; border-color:#cbd5e1;'])
            . '</div>'

            // Catégorie / Trajet
            . '<div>'
            . '<label style="display:block; font-size:0.75rem; font-weight:800; text-transform:uppercase; color:#0f172a; margin-bottom:0.35rem;">Catégorie de Code</label>'
            . Form::select('categorie_code', $categoriesGroups, $selectedCategorie, ['class' => 'finea-select', 'style' => 'width:100%; font-weight:700; border-color:#cbd5e1;'])
            . '</div>'

            // Agence
            . '<div>'
            . '<label style="display:block; font-size:0.75rem; font-weight:800; text-transform:uppercase; color:#0f172a; margin-bottom:0.35rem;">Agence</label>'
            . Form::select('agence_id', $agenceOpts, (string) $selectedAgenceId, ['class' => 'finea-select', 'disabled' => !$canSeeAllAgencies, 'style' => 'width:100%; border-color:#cbd5e1;'])
            . '</div>'

            // Date début
            . '<div>'
            . '<label style="display:block; font-size:0.75rem; font-weight:800; text-transform:uppercase; color:#0f172a; margin-bottom:0.35rem;">Date Début</label>'
            . '<input type="date" name="date_from" value="' . View::e($dateFrom) . '" class="finea-input" style="width:100%; padding:0.5rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem;">'
            . '</div>'

            // Date fin
            . '<div>'
            . '<label style="display:block; font-size:0.75rem; font-weight:800; text-transform:uppercase; color:#0f172a; margin-bottom:0.35rem;">Date Fin</label>'
            . '<input type="date" name="date_to" value="' . View::e($dateTo) . '" class="finea-input" style="width:100%; padding:0.5rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem;">'
            . '</div>'

            // Recherche
            . '<div>'
            . '<label style="display:block; font-size:0.75rem; font-weight:800; text-transform:uppercase; color:#0f172a; margin-bottom:0.35rem;">Recherche libre</label>'
            . '<input type="text" name="q" placeholder="N° Facture, Tracking, Client..." value="' . View::e($searchQuery) . '" class="finea-input" style="width:100%; padding:0.5rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem;">'
            . '</div>'

            // Boutons Filtrer & Reset
            . '<div style="display:flex; gap:6px;">'
            . '<button type="submit" class="finea-button finea-button--primary" style="flex:1; padding:0.55rem 1rem; border-radius:6px; font-weight:700; background:#0f172a;">Filtrer</button>'
            . '<a href="' . View::url('facturation/filtre') . '" class="finea-button finea-button--secondary" style="padding:0.55rem 0.8rem; border-radius:6px; text-decoration:none; color:#64748b; background:#fff; border:1px solid #cbd5e1;">✕</a>'
            . '</div>'

            . '</div></form>';

        $totalImpaye = (float) ($kpis['totalImpaye'] ?? 0);
        $totalEncaisse = (float) ($kpis['totalEncaisse'] ?? 0);
        $totalMontant = (float) ($kpis['totalMontantXof'] ?? 0);

        $kpisCardsHtml = '<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1rem; margin: 1.5rem 0;">'
            . '<div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:1rem; box-shadow:0 1px 3px rgba(0,0,0,0.04);">'
            . '<div style="font-size:0.75rem; font-weight:800; color:#64748b; text-transform:uppercase;">Total Factures Trouvées</div>'
            . '<div style="font-size:1.6rem; font-weight:900; color:#0f172a; margin-top:0.25rem;">' . number_format((int) $kpis['totalCount'], 0, ',', ' ') . '</div>'
            . '<div style="font-size:0.8rem; color:#64748b; margin-top:0.2rem;">' . (int) $kpis['totalColis'] . ' colis (' . number_format((float) $kpis['totalPoids'], 1, ',', ' ') . ' kg)</div>'
            . '</div>'

            . '<div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:1rem; box-shadow:0 1px 3px rgba(0,0,0,0.04);">'
            . '<div style="font-size:0.75rem; font-weight:800; color:#64748b; text-transform:uppercase;">Chiffre d\'Affaires Total</div>'
            . '<div style="font-size:1.6rem; font-weight:900; color:#0284c7; margin-top:0.25rem;">' . number_format($totalMontant, 0, ',', ' ') . ' <small style="font-size:0.8rem; color:#64748b;">FCFA</small></div>'
            . '<div style="font-size:0.8rem; color:#64748b; margin-top:0.2rem;">Montant total facturé</div>'
            . '</div>'

            . '<div style="background:#fff; border:1px solid #bbf7d0; border-left:4px solid #16a34a; border-radius:10px; padding:1rem; box-shadow:0 1px 3px rgba(0,0,0,0.04);">'
            . '<div style="font-size:0.75rem; font-weight:800; color:#15803d; text-transform:uppercase;">Montant Déjà Encaissé</div>'
            . '<div style="font-size:1.6rem; font-weight:900; color:#15803d; margin-top:0.25rem;">' . number_format($totalEncaisse, 0, ',', ' ') . ' <small style="font-size:0.8rem;">FCFA</small></div>'
            . '<div style="font-size:0.8rem; color:#15803d; margin-top:0.2rem; font-weight:600;">Paiements validés</div>'
            . '</div>'

            . '<div style="background:#fff; border:1px solid #fecaca; border-left:4px solid #dc2626; border-radius:10px; padding:1rem; box-shadow:0 1px 3px rgba(0,0,0,0.04);">'
            . '<div style="font-size:0.75rem; font-weight:800; color:#dc2626; text-transform:uppercase;">TOTAL IMPAYÉS / RESTE À RECOUVRER</div>'
            . '<div style="font-size:1.6rem; font-weight:900; color:#dc2626; margin-top:0.25rem;">' . number_format($totalImpaye, 0, ',', ' ') . ' <small style="font-size:0.8rem;">FCFA</small></div>'
            . '<div style="font-size:0.8rem; color:#dc2626; margin-top:0.2rem; font-weight:700;">Créances à encaisser</div>'
            . '</div>'
            . '</div>';

        $tableSection = Ui::section('Résultats de la recherche (' . count($results) . ' factures affichées)', self::filtreResultsTable($results));

        $paginationHtml = '';
        if (($pagination['totalPages'] ?? 1) > 1) {
            $baseParams = [
                'start_month'     => $startMonth,
                'start_year'      => $startYear,
                'end_month'       => $endMonth,
                'end_year'        => $endYear,
                'date_from'       => $dateFrom,
                'date_to'         => $dateTo,
                'agence_id'       => $selectedAgenceId,
                'categorie_code'  => $selectedCategorie,
                'statut_paiement' => $selectedStatutPaiement,
                'q'               => $searchQuery,
            ];
            $paginationHtml = '<div style="margin-top: 1.5rem;">' . Rh::pagination(
                (int) $pagination['currentPage'],
                (int) $pagination['totalPages'],
                static fn(int $page): string => View::url('facturation/filtre?' . http_build_query(array_filter($baseParams + ['page' => $page])))
            ) . '</div>';
        }

        return '<div class="finea-shell">'
            . '<div class="finea-container" style="max-width:1400px; margin:0 auto; padding:1.5rem 1rem;">'
            . $header
            . '<div style="margin: 1.5rem 0;">' . $filterForm . '</div>'
            . $kpisCardsHtml
            . '<div style="margin-top: 1.5rem;">' . $tableSection . '</div>'
            . $paginationHtml
            . '</div>'
            . '</div>';
    }

    /** @param array<int, array<string, mixed>> $results */
    private static function filtreResultsTable(array $results): string
    {
        if (empty($results)) {
            return Ui::emptyState('Aucune facture trouvée', 'Aucune facture ne correspond aux critères de recherche sélectionnés.');
        }

        $rows = '';
        foreach ($results as $row) {
            $dateTime = new \DateTime((string) ($row['date_emission'] ?? 'now'));
            $code = strtoupper((string) ($row['trajet_code'] ?? $row['col_trajet'] ?? 'AUTRE'));
            
            $isCargo = in_array($code, ['LB-CI', 'LB-FR', 'S-FR', 'S-CI', 'LB-CA', 'F-SN']) || str_starts_with($code, 'GP-');
            $isRapide = in_array($code, ['CA-CI', 'CA-FR']) || str_starts_with($code, 'CR-');
            $isDhl = str_contains($code, 'DHL') || str_starts_with((string)($row['numero_tracking'] ?? ''), 'DHL');

            $catStyle = 'background:#f1f5f9; color:#475569; border:1px solid #e2e8f0;';
            $catIcon = '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle></svg>';
            if ($isCargo) {
                $catStyle = 'background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe;';
                $catIcon = '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17.8 19.2L16 11l3.5-3.5C21 6 21.5 4 21 3.5c-.5-.5-2.5 0-4 1.5L13.5 8.5 5.3 6.7c-.5-.1-1.1.1-1.4.6l-.6.9c-.3.4-.2 1 .2 1.3L8 13l-3 3-2-1c-.4-.2-.9-.1-1.2.2l-.6.6c-.3.3-.3.8 0 1.1l2.5 2.5c.3.3.8.3 1.1 0l.6-.6c.3-.3.4-.8.2-1.2l-1-2 3-3 3.5 4.5c.3.4.9.5 1.3.2l.9-.6c.5-.3.7-.9.6-1.4z"></path></svg>';
            } elseif ($isRapide) {
                $catStyle = 'background:#fdf4ff; color:#a21caf; border:1px solid #f5d0fe;';
                $catIcon = '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>';
            } elseif ($isDhl) {
                $catStyle = 'background:#fefce8; color:#a16207; border:1px solid #fef08a;';
                $catIcon = '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>';
            }

            $restant = (float) ($row['montant_restant'] ?? 0);
            $encaisse = (float) ($row['montant_encaisse'] ?? 0);
            $total = (float) ($row['montant_total'] ?? 0);
            
            $statutBadge = '<span style="display:inline-block; padding:2px 8px; border-radius:9999px; font-size:0.75rem; font-weight:700; background:#fee2e2; color:#dc2626;">Impayé</span>';
            if ($restant <= 0 || ($row['facture_statut'] ?? '') === 'payee') {
                $statutBadge = '<span style="display:inline-block; padding:2px 8px; border-radius:9999px; font-size:0.75rem; font-weight:700; background:#dcfce7; color:#15803d;">Payé</span>';
            } elseif ($encaisse > 0) {
                $statutBadge = '<span style="display:inline-block; padding:2px 8px; border-radius:9999px; font-size:0.75rem; font-weight:700; background:#fef3c7; color:#d97706;">Partiel</span>';
            }

            $rows .= '<tr style="border-bottom:1px solid #f1f5f9;">'
                . '<td><a href="' . View::url('colisage/parcels/' . ($row['colis_id'] ?? 0) . '/facture') . '" style="color:#0284c7; font-weight:700; text-decoration:none;">' . View::e((string) $row['numero_facture']) . '</a></td>'
                . '<td style="font-family:monospace; font-weight:700; color:#0f172a;">' . View::e((string) ($row['numero_tracking'] ?? '—')) . '</td>'
                . '<td><span style="display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:4px; font-weight:700; font-size:0.75rem; ' . $catStyle . '">' . $catIcon . ' ' . View::e($code) . '</span></td>'
                . '<td><strong>' . View::e((string) $row['client_name']) . '</strong>' . (!empty($row['client_phone']) ? '<br><small style="color:#64748b;">' . View::e((string) $row['client_phone']) . '</small>' : '') . '</td>'
                . '<td>' . View::e((string) $row['agence_name']) . '</td>'
                . '<td style="color:#475569; white-space:nowrap;">' . $dateTime->format('d/m/Y') . '</td>'
                . '<td style="text-align:right;">' . number_format((float) $row['poids_total'], 1, ',', ' ') . ' kg<br><small style="color:#94a3b8;">' . (int) $row['nombre_colis'] . ' colis</small></td>'
                . '<td style="text-align:right; font-weight:700;">' . number_format($total, 0, ',', ' ') . ' ' . View::e((string) ($row['devise'] ?? 'XOF')) . '</td>'
                . '<td style="text-align:right; color:#15803d; font-weight:700;">' . number_format($encaisse, 0, ',', ' ') . '</td>'
                . '<td style="text-align:right; font-weight:900; color:' . ($restant > 0 ? '#dc2626' : '#15803d') . ';">' . number_format($restant, 0, ',', ' ') . '</td>'
                . '<td style="text-align:center;">' . $statutBadge . '</td>'
                . '<td><a href="' . View::url('facturation/factures/' . $row['facture_id'] . '/modifier') . '" class="finea-button finea-button--secondary finea-button-sm" style="padding:4px 8px; font-size:0.75rem; text-decoration:none; border-radius:4px;">Détail</a></td>'
                . '</tr>';
        }

        return '<div class="finea-table-wrapper" style="background:#fff; border-radius:10px; border:1px solid #e2e8f0; overflow:hidden;"><table class="finea-table" style="width:100%; font-size:0.85rem;"><thead><tr style="background:#f8fafc; border-bottom:2px solid #e2e8f0; color:#475569; text-transform:uppercase; font-size:0.75rem;">'
            . '<th>N° Facture</th><th>N° Tracking</th><th>Catégorie / Trajet</th>'
            . '<th>Client & Contact</th><th>Agence</th><th>Date</th><th>Poids / Colis</th><th>Montant Total</th><th>Encaissé</th><th style="color:#dc2626;">Reste Impayé</th><th>Statut</th><th>Action</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }

    /**
     * Écran de consultation / modification d'une facture verrouillée (Règle point 4).
     * Un agent standard voit un contenu strictement lecture seule ; un Responsable/Admin
     * (canModify=true) voit le formulaire de modification avec traçabilité obligatoire.
     *
     * @param array<string, mixed> $facture
     * @param array<int, array<string, mixed>> $auditLog
     */
    public static function factureEditPage(array $facture, bool $canModify, array $auditLog): string
    {
        $locked = (int) ($facture['locked'] ?? 1) === 1;
        $statusBadge = $locked
            ? Ui::badge('Verrouillée', 'primary')
            : Ui::badge('Non verrouillée', 'neutral');

        $header = Ui::pageHeader(
            'Facture ' . $facture['numero_facture'],
            $canModify
                ? 'Vous disposez du rôle Responsable : toute modification sera tracée dans le journal d\'audit.'
                : 'Cette facture est verrouillée : seul un Responsable peut la modifier.',
            [
                'eyebrow' => 'Facturation • Détail facture',
                'class' => 'rh-hero-white',
                'actions' => [
                    $statusBadge,
                    Ui::button('Retour au filtre', ['href' => 'facturation/filtre', 'variant' => 'secondary']),
                ],
            ]
        );

        $trajetDisp = !empty($facture['trajet_code'])
            ? $facture['trajet_code'] . ' (' . $facture['trajet_libelle'] . ')'
            : ($facture['col_trajet'] ?? 'N/A');

        $summaryHtml = '<div class="rh-form-grid-3">'
            . '<div><span class="rh-eyebrow">Client</span><h3 style="margin:0;">' . View::e($facture['client_name']) . '</h3><small>' . View::e($facture['client_phone'] ?? '') . '</small></div>'
            . '<div><span class="rh-eyebrow">Agence</span><h3 style="margin:0;">' . View::e($facture['agence_name']) . '</h3></div>'
            . '<div><span class="rh-eyebrow">Trajet & Transport</span><h3 style="margin:0;">' . View::e((string) $trajetDisp) . '</h3></div>'
            . '<div><span class="rh-eyebrow">N° Tracking Colis</span><h3 style="margin:0;">' . View::e($facture['numero_tracking']) . '</h3></div>'
            . '<div><span class="rh-eyebrow">Créée par</span><h3 style="margin:0;">' . View::e($facture['created_by_name'] ?? $facture['agent_name']) . '</h3></div>'
            . '<div><span class="rh-eyebrow">Date & Heure de création</span><h3 style="margin:0;">' . View::e(date('d/m/Y H:i', strtotime((string) $facture['date_emission']))) . '</h3></div>'
            . '</div>';

        $mainContent = $canModify
            ? self::factureEditForm($facture)
            : self::factureReadOnlySummary($facture);

        $auditSection = Ui::section('Journal d\'audit — Traçabilité des modifications', self::factureAuditTable($auditLog));

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . '<div style="margin: 1.5rem 0;">' . Ui::section('Résumé de la facture', $summaryHtml) . '</div>'
            . '<div style="margin-bottom: 1.5rem;">' . $mainContent . '</div>'
            . $auditSection
            . '</div>'
            . '</div>';
    }

    /** @param array<string, mixed> $facture */
    private static function factureEditForm(array $facture): string
    {
        $fields = \App\Helpers\Csrf::input()
            . '<div class="rh-form-grid-3">'
            . Form::input('montant_total', ['label' => 'Montant total', 'type' => 'number', 'step' => '0.01', 'value' => (string) $facture['montant_total']])
            . Form::input('montant_encaisse', ['label' => 'Montant encaissé', 'type' => 'number', 'step' => '0.01', 'value' => (string) $facture['montant_encaisse']])
            . Form::select('statut', [
                ['value' => 'emise', 'label' => 'Émise'],
                ['value' => 'partiellement_payee', 'label' => 'Partiellement payée'],
                ['value' => 'payee', 'label' => 'Payée'],
                ['value' => 'annulee', 'label' => 'Annulée'],
            ], (string) $facture['statut'], ['label' => 'Statut'])
            . '</div>'
            . '<div style="margin-top:1rem;">' . Ui::button('Enregistrer la modification (tracée)', ['type' => 'submit', 'variant' => 'accent']) . '</div>';

        $form = '<form method="post" action="' . View::url('facturation/factures/' . $facture['id'] . '/modifier') . '">' . $fields . '</form>';

        return Ui::section('Modifier la facture', $form, 'Réservé au rôle Responsable — chaque champ modifié est enregistré (ancienne valeur → nouvelle valeur).');
    }

    /** @param array<string, mixed> $facture */
    private static function factureReadOnlySummary(array $facture): string
    {
        $content = '<div class="rh-form-grid-3">'
            . '<div><span class="rh-eyebrow">Montant total</span><h3 style="margin:0;">' . number_format((float) $facture['montant_total'], 0, ',', ' ') . ' ' . View::e($facture['devise']) . '</h3></div>'
            . '<div><span class="rh-eyebrow">Montant encaissé</span><h3 style="margin:0;">' . number_format((float) $facture['montant_encaisse'], 0, ',', ' ') . ' ' . View::e($facture['devise']) . '</h3></div>'
            . '<div><span class="rh-eyebrow">Statut</span><h3 style="margin:0;">' . View::e($facture['statut']) . '</h3></div>'
            . '</div>';

        return Ui::section(
            'Facture verrouillée',
            $content . Ui::emptyState('Modification non autorisée', 'Cette facture ne peut plus être modifiée par l\'agent qui l\'a créée. Contactez un Responsable si une correction est nécessaire.')
        );
    }

    /** @param array<int, array<string, mixed>> $auditLog */
    private static function factureAuditTable(array $auditLog): string
    {
        if (empty($auditLog)) {
            return Ui::emptyState('Aucune modification enregistrée', 'Cette facture n\'a jamais été modifiée depuis sa création.');
        }

        $rows = '';
        foreach ($auditLog as $log) {
            $rows .= '<tr>'
                . '<td>' . View::e(date('d/m/Y H:i', strtotime((string) $log['date_modification']))) . '</td>'
                . '<td>' . View::e($log['modifie_par_name']) . '</td>'
                . '<td>' . View::e($log['champ_modifie']) . '</td>'
                . '<td>' . View::e((string) $log['ancienne_valeur']) . '</td>'
                . '<td>' . View::e((string) $log['nouvelle_valeur']) . '</td>'
                . '</tr>';
        }

        return '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>Date & Heure</th><th>Modifié par</th><th>Champ</th><th>Ancienne valeur</th><th>Nouvelle valeur</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }
}
