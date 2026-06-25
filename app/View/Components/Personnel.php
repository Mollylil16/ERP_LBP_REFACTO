<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use App\View\Pages\Rh\PersonnelIndexPage;
use App\View\Components\Ui;
use App\View\Components\Rh;
use App\View\Components\Form;
use App\View\Components\EmployeeCard;

final class Personnel
{
    public static function personnelPage(PersonnelIndexPage $page): string
    {
        $statsHtml = '<div class="rh-header-stats">'
            . '<a href="' . View::url('rh/personnel?scope=all') . '" class="rh-header-stat rh-stat-total">'
            . '<span class="rh-stat-label">Effectif</span>'
            . '<strong class="rh-stat-value">' . number_format((float) ($page->stats['total'] ?? 0), 0, ',', ' ') . '</strong>'
            . '</a>'
            . '<a href="' . View::url('rh/personnel?scope=active') . '" class="rh-header-stat rh-stat-active">'
            . '<span class="rh-stat-label">En poste</span>'
            . '<strong class="rh-stat-value">' . number_format((float) ($page->stats['active'] ?? 0), 0, ',', ' ') . '</strong>'
            . '</a>'
            . '<a href="' . View::url('rh/personnel?scope=inactive') . '" class="rh-header-stat rh-stat-inactive">'
            . '<span class="rh-stat-label">Sorties</span>'
            . '<strong class="rh-stat-value">' . number_format((float) ($page->stats['inactive'] ?? 0), 0, ',', ' ') . '</strong>'
            . '</a>'
            . '</div>';

        $actionHtml = $page->canCreate ? Ui::button('Intégrer un collaborateur', [
            'href' => 'rh/personnel/nouveau',
            'variant' => 'accent',
        ]) : '';

        $header = Ui::pageHeader(
            'Liste du personnel',
            'Rechercher, filtrer et ouvrir les dossiers individuels des collaborateurs.',
            [
                'eyebrow' => 'Annuaire RH',
                'class' => 'rh-hero-white',
                'actions' => [
                    $statsHtml,
                    $actionHtml,
                ],
            ]
        );

        $restrictedData = Rh::restrictedData($page->restrictedTables);

        // Filters
        $q = Form::input('q', [
            'label' => 'Recherche',
            'value' => (string) ($page->filters['q'] ?? ''),
            'placeholder' => 'Nom, matricule ou e-mail',
        ]);
        $service = Form::selectSearch('service_id', $page->filterOptions['services'], $page->filters['service_id'] ?? '', ['label' => 'Service']);
        $function = Form::selectSearch('function_id', $page->filterOptions['functions'], $page->filters['function_id'] ?? '', ['label' => 'Fonction']);
        $status = Form::selectSearch('status_id', $page->filterOptions['statuses'], $page->filters['status_id'] ?? '', ['label' => 'Statut']);
        $gender = Form::selectSearch('gender', [
            ['value' => '', 'label' => 'Tous les sexes'],
            ['value' => 'male', 'label' => 'Masculin'],
            ['value' => 'female', 'label' => 'Féminin'],
            ['value' => 'other', 'label' => 'Autre'],
        ], $page->filters['gender'] ?? '', ['label' => 'Sexe']);
        $site = Form::selectSearch('site', $page->filterOptions['sites'], $page->filters['site'] ?? '', ['label' => 'Site']);

        $filterGrid = '<div class="rh-personnel-filter-grid">' . $q . $service . $function . $status . $gender . $site . '</div>';

        // Filter Actions
        $searchBtn = '<button type="submit" class="rh-filter-btn rh-filter-btn--primary">'
            . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="rh-btn-icon"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>'
            . 'Rechercher'
            . '</button>';

        $pdfQuery = http_build_query(array_filter($page->filters));
        $pdfBtn = '<a href="' . View::url('rh/personnel/export-pdf?' . $pdfQuery) . '" class="rh-filter-btn rh-filter-btn--pdf" target="_blank">'
            . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" class="rh-btn-icon"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>'
            . 'Export PDF'
            . '</a>';

        $excelQuery = http_build_query(array_filter($page->filters));
        $excelBtn = '<a href="' . View::url('rh/personnel/export-excel?' . $excelQuery) . '" class="rh-filter-btn rh-filter-btn--excel">'
            . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" class="rh-btn-icon"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="3" x2="9" y2="21"></line><line x1="15" y1="3" x2="15" y2="21"></line><line x1="3" y1="9" x2="21" y2="9"></line><line x1="3" y1="15" x2="21" y2="15"></line></svg>'
            . 'Export Excel'
            . '</a>';

        $orgBtn = '<a href="' . View::url('rh/personnel/organigramme') . '" class="rh-filter-btn rh-filter-btn--org">'
            . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" class="rh-btn-icon"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>'
            . 'Organigramme'
            . '</a>';

        $resetBtn = '<a href="' . View::url('rh/personnel') . '" class="rh-filter-btn rh-filter-btn--reset">'
            . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" class="rh-btn-icon"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"></path></svg>'
            . 'Réinitialiser'
            . '</a>';

        $filterActions = '<div class="rh-personnel-filter-actions">' . $searchBtn . $pdfBtn . $excelBtn . $orgBtn . $resetBtn . '</div>';

        $form = '<form method="get" action="' . View::url('rh/personnel') . '" class="rh-personnel-filters">' . $filterGrid . $filterActions . '</form>';

        // Scope bar
        $plural = $page->total > 1 ? 's' : '';
        $countHtml = '<div class="rh-personnel-count"><strong>' . $page->total . '</strong> collaborateur' . $plural . ' trouvé' . $plural . '</div>';

        $currentScope = $page->filters['scope'] ?? 'active';
        $scopes = [
            'active' => 'Actifs',
            'all' => 'Tous',
            'inactive' => 'Sortis',
        ];
        $togglesHtml = '<div class="rh-scope-toggles">';
        foreach ($scopes as $key => $label) {
            $isActiveScope = ($currentScope === $key);
            $toggleFilters = $page->filters;
            $toggleFilters['scope'] = $key;
            unset($toggleFilters['page']);
            $toggleUrl = View::url('rh/personnel?' . http_build_query(array_filter($toggleFilters)));
            $activeClass = $isActiveScope ? 'is-active' : '';
            $togglesHtml .= '<a href="' . $toggleUrl . '" class="rh-scope-toggle ' . $activeClass . '">' . $label . '</a>';
        }
        $togglesHtml .= '</div>';

        $scopeBar = '<div class="rh-personnel-scope-bar">' . $countHtml . $togglesHtml . '</div>';

        $cardsHtml = '';
        if ($page->employees === []) {
            $cardsHtml = Rh::card(Ui::emptyState(
                'Aucun collaborateur',
                'Aucun dossier ne correspond aux critères sélectionnés.'
            ));
        } else {
            $cardsInner = '';
            foreach ($page->employees as $item) {
                $cardsInner .= EmployeeCard::render($item['employee'], $item['actions']);
            }
            $cardsHtml = '<section class="rh-personnel-card-grid" aria-label="Collaborateurs">' . $cardsInner . '</section>';
        }

        $pagination = Rh::paginationLinks($page->pagination);

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . $restrictedData
            . $form
            . $scopeBar
            . $cardsHtml
            . $pagination
            . '</div></div>';
    }
}
