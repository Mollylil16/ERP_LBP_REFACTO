<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use App\Models\Logistique\Rayon;
use App\Models\Logistique\LogistiqueSettings;
use App\View\Components\Dashboard;
use App\View\Components\Ui;
use App\View\Components\Form;
use App\View\Pages\Logistique\DashboardPage;

final class Logistique
{
    public static function dashboardPage(DashboardPage $page, array $dashboardModule, array $rayons = [], array $settings = []): string
    {
        $header = Dashboard::header(
            $dashboardModule['label'] ?? 'Logistique',
            "Gestion intégrée de la logistique : affectation automatique dans les rayons, capacité des stocks et suivi des délais de gardiennage.",
            [
                'eyebrow' => ($dashboardModule['code'] ?? 'LOG') . ' Dashboard',
                'class' => 'rh-hero-white'
            ]
        );

        $kpis = Dashboard::kpis($page->kpis);

        $quickActionsList = array_merge($page->quickActions, [
            ['label' => 'Gestion des Rayons', 'href' => 'logistique/rayons', 'icon' => '<svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/></svg>', 'variant' => 'primary'],
            ['label' => 'Délais & Gardiennage', 'href' => 'logistique/parametres', 'icon' => '<svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>', 'variant' => 'secondary'],
        ]);

        $actions = Dashboard::actions($quickActionsList, [
            'title' => 'Actions Logistiques',
            'class' => 'finea-section-card',
        ]);

        $stockOverview = self::rayonsStockOverviewComponent($rayons);
        $section = Ui::section('Aperçu des Rayons & Capacité de Stockage', $stockOverview, 'Taux d\'occupation et affectation automatique des colis.');

        return '<div class="finea-shell logistique-dashboard">'
            . '<div class="finea-container">'
            . $header
            . '<div class="rh-dashboard-grid" style="margin-top: 2rem;">'
            . '<div class="rh-dashboard-main">'
            . $kpis
            . '<div style="margin-top: 2rem;">'
            . $section
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
     * Composant des cartes de capacité par rayon avec Ui::badge et Ui::section.
     *
     * @param array<int, Rayon> $rayons
     */
    public static function rayonsStockOverviewComponent(array $rayons): string
    {
        if (empty($rayons)) {
            return Ui::emptyState(
                'Aucun rayon configuré',
                'Créez les premiers rayons de votre entrepôt pour activer l\'affectation automatique.'
            );
        }

        $itemsHtml = '';
        foreach ($rayons as $rayon) {
            $taux = $rayon->tauxOccupation();
            $tone = 'success';
            $statusText = 'Disponible';

            if ($taux >= 100 || $rayon->statut === 'PLEIN') {
                $tone = 'danger';
                $statusText = 'Saturé';
            } elseif ($taux >= 80) {
                $tone = 'warning';
                $statusText = 'Quasi-Plein';
            } elseif ($rayon->statut === 'MAINTENANCE') {
                $tone = 'neutral';
                $statusText = 'Maintenance';
            }

            $badge = Ui::badge($statusText, $tone);
            $content = '<p class="rh-eyebrow">' . View::e($rayon->nomRayon) . '</p>'
                . '<h3>' . View::e($rayon->codeRayon) . '</h3>'
                . '<p>Capacité : <strong>' . $rayon->capaciteOccupee . ' / ' . $rayon->capaciteMax . ' colis</strong> (' . $taux . '%)</p>'
                . '<p>Places libres : <strong>' . $rayon->placesDisponibles() . '</strong></p>'
                . $badge;

            $itemsHtml .= Ui::section($rayon->codeRayon, $content, $rayon->nomRayon, ['class' => 'rh-card-section']);
        }

        return '<div class="rh-dashboard-grid" style="grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem;">' . $itemsHtml . '</div>';
    }

    /**
     * Page de gestion des rayons avec tableau et modal de création via Ui & Form components.
     *
     * @param array<int, Rayon> $rayons
     * @param array<int, array<string, mixed>> $sites
     */
    public static function rayonsListPage(array $rayons, array $sites, ?string $successMsg = null, ?string $errorMsg = null): string
    {
        $header = Ui::pageHeader(
            'Gestion des Rayons & Emplacements',
            'Définition des capacités par rayon et surveillance de l\'occupation des entrepôts.',
            [
                'eyebrow' => 'Module Logistique',
                'class' => 'rh-hero-white',
                'actions' => [
                    Ui::button('+ Nouveau Rayon', [
                        'variant' => 'accent',
                        'type' => 'button',
                        'onclick' => "document.getElementById('modal-add-rayon').style.display='flex'",
                    ]),
                ],
            ]
        );

        $alertsHtml = '';
        if ($successMsg) {
            $alertsHtml .= Ui::badge($successMsg, 'success', ['class' => 'finea-alert-success']);
        }
        if ($errorMsg) {
            $alertsHtml .= Ui::badge($errorMsg, 'danger', ['class' => 'finea-alert-danger']);
        }

        // Table Rows
        $rowsHtml = '';
        foreach ($rayons as $rayon) {
            $taux = $rayon->tauxOccupation();
            $tone = $taux >= 100 ? 'danger' : ($taux >= 80 ? 'warning' : 'success');
            $badge = Ui::badge($rayon->statut, $tone);

            $deleteBtn = Ui::button('Supprimer', [
                'href' => 'logistique/rayons/' . $rayon->id . '/supprimer',
                'variant' => 'danger',
                'onclick' => "return confirm('Êtes-vous sûr de vouloir supprimer ce rayon ?')",
            ]);

            $rowsHtml .= '<tr>'
                . '<td><strong>' . View::e($rayon->codeRayon) . '</strong></td>'
                . '<td>' . View::e($rayon->nomRayon) . '</td>'
                . '<td>' . View::e($rayon->agenceNom ?? 'Agence') . '</td>'
                . '<td>' . $rayon->capaciteOccupee . ' / ' . $rayon->capaciteMax . ' colis (' . $taux . '%)</td>'
                . '<td>' . $badge . '</td>'
                . '<td>' . $deleteBtn . '</td>'
                . '</tr>';
        }

        $tableHtml = '<table class="finea-table">'
            . '<thead><tr><th>Code Rayon</th><th>Nom</th><th>Agence</th><th>Capacité</th><th>Statut</th><th>Actions</th></tr></thead>'
            . '<tbody>' . ($rowsHtml !== '' ? $rowsHtml : '<tr><td colspan="6">' . Ui::emptyState('Aucun rayon répertorié') . '</td></tr>') . '</tbody>'
            . '</table>';

        $mainSection = Ui::section('Liste des Rayons configurés', $tableHtml);

        // Modal Form using Form components
        $siteOptions = array_map(fn($s) => ['value' => (string) $s['id'], 'label' => $s['name']], $sites);
        $fieldsHtml = Form::selectSearch('agence_id', $siteOptions, '1', ['label' => 'Agence / Entrepôt', 'required' => true])
            . Form::input('code_rayon', ['label' => 'Code du Rayon (Ex: RAY-A1)', 'placeholder' => 'RAY-A1', 'required' => true])
            . Form::input('nom_rayon', ['label' => 'Nom descriptif', 'placeholder' => 'Rayon A1 - Colis Légers', 'required' => true])
            . Form::input('capacite_max', ['label' => 'Capacité maximale (nombre de colis)', 'type' => 'number', 'value' => '50', 'required' => true])
            . Form::selectSearch('statut', [
                ['value' => 'ACTIF', 'label' => 'ACTIF'],
                ['value' => 'PLEIN', 'label' => 'PLEIN'],
                ['value' => 'MAINTENANCE', 'label' => 'MAINTENANCE'],
            ], 'ACTIF', ['label' => 'Statut']);

        $modalHtml = Ui::modal('modal-add-rayon', 'Ajouter un nouveau Rayon', $fieldsHtml, View::url('logistique/rayons'), [
            'btnLabel' => 'Créer le rayon',
            'btnVariant' => 'accent',
        ]);

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . $alertsHtml
            . '<div style="margin-top: 2rem;">'
            . $mainSection
            . '</div>'
            . $modalHtml
            . '</div>'
            . '</div>';
    }

    /**
     * Page de paramétrage des délais et gardiennage utilisant Form et Ui components.
     *
     * @param array<int, array<string, mixed>> $sites
     */
    public static function parametresPage(LogistiqueSettings $settings, array $sites, ?string $successMsg = null): string
    {
        $header = Ui::pageHeader(
            'Délais de Récupération & Gardiennage',
            'Configuration de la période gratuite de stockage et calcul des pénalités financières après dépassement.',
            [
                'eyebrow' => 'Paramétrage Logistique',
                'class' => 'rh-hero-white',
            ]
        );

        $alertHtml = $successMsg ? Ui::badge($successMsg, 'success', ['class' => 'finea-alert-success']) : '';

        $fieldsHtml = Form::input('delai_gratuit_jours', [
            'label' => 'Délai gratuit de garde (en jours)',
            'type' => 'number',
            'value' => (string) $settings->delaiGratuitJours,
            'hint' => 'Nombre de jours pendant lesquels le colis est stocké gratuitement.',
            'required' => true,
        ])
        . Form::input('frais_gardiennage_par_jour', [
            'label' => 'Frais de gardiennage par jour supplémentaire (XOF)',
            'type' => 'number',
            'step' => '50',
            'value' => (string) $settings->fraisGardiennageParJour,
            'hint' => 'Pénalité financière appliquée par jour de retard après le délai gratuit.',
            'required' => true,
        ])
        . Form::field(
            'Affectation automatique',
            '<label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">'
            . '<input type="checkbox" name="auto_assign_rayon" value="1" ' . ($settings->autoAssignRayon ? 'checked' : '') . '>'
            . '<span>Activer l\'affectation automatique des colis dans le premier rayon disponible lors de la réception.</span>'
            . '</label>'
        )
        . Ui::button('Enregistrer les paramètres', ['variant' => 'accent', 'type' => 'submit']);

        $formHtml = '<form method="post" action="' . View::url('logistique/parametres') . '">' . $fieldsHtml . '</form>';
        $section = Ui::section('Règles de Stockage & Gardiennage', $formHtml);

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . $alertHtml
            . '<div style="margin-top: 2rem;">'
            . $section
            . '</div>'
            . '</div>'
            . '</div>';
    }

    /**
     * Badge d'avertissement de frais de gardiennage.
     */
    public static function colisGardiennageBadge(float $montantFrais): string
    {
        if ($montantFrais <= 0) {
            return Ui::badge('Délai gratuit respecté', 'success');
        }

        return Ui::badge('Frais de garde : +' . number_format($montantFrais, 0, ',', ' ') . ' XOF', 'danger');
    }
}
