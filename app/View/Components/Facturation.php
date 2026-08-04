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
        string $selectedTrajet,
        bool $canSeeAllAgencies,
        array $sites,
        array $trajets,
        array $results,
        array $kpis,
        array $pagination
    ): string {
        $months = View::monthNames();
        $years = range((int) date('Y') - 2, (int) date('Y') + 1);

        $exportQuery = http_build_query([
            'start_month' => $startMonth,
            'start_year' => $startYear,
            'end_month' => $endMonth,
            'end_year' => $endYear,
            'agence_id' => $selectedAgenceId,
            'trajet' => $selectedTrajet,
        ]);

        $header = Ui::pageHeader(
            'Filtre par Période, Agence & Trajet',
            'Analyse détaillée des factures émises, montants encaissés et statut d\'audit.',
            [
                'eyebrow' => 'Facturation • Recherche multi-critères',
                'class' => 'rh-hero-white',
                'actions' => [
                    Ui::button('Export PDF (avec montants)', ['href' => 'facturation/filtre/export-pdf?' . $exportQuery, 'variant' => 'danger']),
                    Ui::button('Export Excel (avec montants)', ['href' => 'facturation/filtre/export-excel?' . $exportQuery, 'variant' => 'accent']),
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
            $agenceOpts[] = ['value' => (string) $s['id'], 'label' => $s['name']];
        }

        $trajetOpts = [['value' => 'all', 'label' => 'Tous les trajets']];
        foreach ($trajets as $t) {
            $trajetOpts[] = ['value' => $t['code'], 'label' => $t['code'] . ' — ' . $t['libelle'] . ' (' . strtoupper((string) $t['type_transport']) . ')'];
        }

        $filterForm = '<form method="get" action="' . View::url('facturation/filtre') . '" class="rh-form-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); align-items:end;">'
            . '<div><label class="rh-eyebrow" style="display:block; margin-bottom:0.35rem;">Période Début (Mois / Année)</label>'
            . '<div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem;">'
            . Form::rawSelect('start_month', $monthOpts($startMonth))
            . Form::rawSelect('start_year', $yearOpts($startYear))
            . '</div></div>'
            . '<div><label class="rh-eyebrow" style="display:block; margin-bottom:0.35rem;">Période Fin (Mois / Année)</label>'
            . '<div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem;">'
            . Form::rawSelect('end_month', $monthOpts($endMonth))
            . Form::rawSelect('end_year', $yearOpts($endYear))
            . '</div></div>'
            . Form::select('agence_id', $agenceOpts, (string) $selectedAgenceId, ['label' => 'Agence d\'expédition', 'disabled' => !$canSeeAllAgencies])
            . Form::select('trajet', $trajetOpts, $selectedTrajet, ['label' => 'Trajet spécifique'])
            . '<div>' . Ui::button('Filtrer les factures', ['type' => 'submit', 'variant' => 'primary']) . '</div>'
            . '</form>';

        $kpisHtml = Dashboard::kpis([
            ['label' => 'Nombre de factures', 'value' => number_format((int) $kpis['totalCount'], 0, ',', ' ')],
            ['label' => 'Chiffre d\'affaires total', 'value' => number_format((float) $kpis['totalMontantXof'], 0, ',', ' ') . ' XOF', 'tone' => 'success'],
            ['label' => 'Poids total expédié', 'value' => number_format((float) $kpis['totalPoids'], 2, ',', ' ') . ' kg'],
            ['label' => 'Nombre de colis total', 'value' => number_format((int) $kpis['totalColis'], 0, ',', ' ') . ' colis'],
        ]);

        $tableSection = Ui::section('Résultats de la recherche', self::filtreResultsTable($results));

        $paginationHtml = '';
        if (($pagination['totalPages'] ?? 1) > 1) {
            $baseParams = [
                'start_month' => $startMonth,
                'start_year' => $startYear,
                'end_month' => $endMonth,
                'end_year' => $endYear,
                'agence_id' => $selectedAgenceId,
                'trajet' => $selectedTrajet,
            ];
            $paginationHtml = '<div style="margin-top: 1.5rem;">' . Rh::pagination(
                (int) $pagination['currentPage'],
                (int) $pagination['totalPages'],
                static fn(int $page): string => View::url('facturation/filtre?' . http_build_query($baseParams + ['page' => $page]))
            ) . '</div>';
        }

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . '<div style="margin: 1.5rem 0;">' . Ui::section('Filtres', $filterForm) . '</div>'
            . $kpisHtml
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
            $dateTime = new \DateTime($row['date_emission']);
            $trajetDisp = !empty($row['trajet_code']) ? $row['trajet_code'] . ' (' . ($row['trajet_libelle'] ?? '') . ')' : ($row['col_trajet'] ?? 'N/A');
            $modifsCount = (int) ($row['modifications_count'] ?? 0);
            $statusBadge = $modifsCount > 0
                ? Ui::badge('Modifiée (' . $modifsCount . ')', 'warning')
                : Ui::badge('Verrouillée', 'primary');

            $rows .= '<tr>'
                . '<td><a href="' . View::url('colisage/parcels/' . $row['colis_id'] . '/facture') . '"><strong>' . View::e($row['numero_facture']) . '</strong></a></td>'
                . '<td>' . $dateTime->format('d/m/Y') . '<br><small>' . $dateTime->format('H:i') . '</small></td>'
                . '<td><strong>' . View::e($row['agent_name']) . '</strong></td>'
                . '<td>' . View::e($row['agence_name']) . '</td>'
                . '<td><strong>' . View::e((string) $trajetDisp) . '</strong><br><small>' . View::e((string) ($row['trajet_type_transport'] ?? $row['type_expediteur'])) . '</small></td>'
                . '<td>' . View::e($row['client_name']) . '<br><small>' . View::e($row['client_phone'] ?? '') . '</small></td>'
                . '<td style="text-align:right;">' . number_format((float) $row['poids_total'], 2, ',', ' ') . ' kg<br><small>' . (int) $row['nombre_colis'] . ' colis</small></td>'
                . '<td style="text-align:right;"><strong>' . number_format((float) $row['montant_total'], 0, ',', ' ') . ' ' . View::e($row['devise']) . '</strong></td>'
                . '<td style="text-align:center;">' . $statusBadge . '</td>'
                . '<td>' . Ui::button('Voir / Modifier', ['href' => 'facturation/factures/' . $row['facture_id'] . '/modifier', 'variant' => 'secondary']) . '</td>'
                . '</tr>';
        }

        return '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>N° Facture</th><th>Date & Heure</th><th>Agent Créateur</th><th>Agence</th><th>Trajet & Transport</th>'
            . '<th>Client</th><th>Poids / Colis</th><th>Montant Total</th><th>Statut</th><th>Action</th>'
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
