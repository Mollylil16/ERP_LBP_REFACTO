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
            $taux = is_object($rayon) ? $rayon->tauxOccupation() : (float) ($rayon['taux_occupation'] ?? 0);
            $code = is_object($rayon) ? $rayon->codeRayon : (string) ($rayon['code_rayon'] ?? '');
            $nom = is_object($rayon) ? $rayon->nomRayon : (string) ($rayon['nom_rayon'] ?? '');
            $occupee = is_object($rayon) ? $rayon->capaciteOccupee : (int) ($rayon['capacite_occupee'] ?? 0);
            $max = is_object($rayon) ? $rayon->capaciteMax : (int) ($rayon['capacite_max'] ?? 50);
            $libres = is_object($rayon) ? $rayon->placesDisponibles() : max(0, $max - $occupee);
            $statut = is_object($rayon) ? $rayon->statut : (string) ($rayon['statut'] ?? 'ACTIF');

            $tone = 'success';
            $statusText = 'Disponible';

            if ($taux >= 100 || $statut === 'PLEIN') {
                $tone = 'danger';
                $statusText = 'Saturé';
            } elseif ($taux >= 80) {
                $tone = 'warning';
                $statusText = 'Quasi-Plein';
            } elseif ($statut === 'MAINTENANCE') {
                $tone = 'neutral';
                $statusText = 'Maintenance';
            }

            $gaugeColor = match($tone) {
                'danger' => '#ef4444',
                'warning' => '#f59e0b',
                'neutral' => '#94a3b8',
                default => '#10b981',
            };

            $progressBar = '<div style="background: rgba(0,0,0,0.06); border-radius: 999px; height: 8px; width: 100%; overflow: hidden; margin: 0.75rem 0;">'
                . '<div style="background: ' . $gaugeColor . '; width: ' . min(100, $taux) . '%; height: 100%; transition: width 0.3s ease; border-radius: 999px;"></div>'
                . '</div>';

            $visualSlots = '<div style="display: flex; gap: 3px; margin-top: 0.5rem;" title="Aperçu des casiers (' . $occupee . '/' . $max . ')">';
            $numBlocks = 10;
            $filledBlocks = (int) round(($taux / 100) * $numBlocks);
            for ($b = 0; $b < $numBlocks; $b++) {
                $blockColor = ($b < $filledBlocks) ? $gaugeColor : 'rgba(0,0,0,0.08)';
                $visualSlots .= '<div style="flex: 1; height: 5px; border-radius: 2px; background: ' . $blockColor . ';"></div>';
            }
            $visualSlots .= '</div>';

            $badge = Ui::badge($statusText, $tone);
            $content = '<div style="display:flex; justify-content:space-between; align-items:flex-start;">'
                . '<div><p class="rh-eyebrow" style="margin-bottom:0.2rem;">' . View::e($nom) . '</p><h3 style="margin:0;">' . View::e($code) . '</h3></div>'
                . $badge
                . '</div>'
                . $progressBar
                . '<div style="display:flex; justify-content:space-between; font-size:0.85rem; color:#64748b; margin-top:0.4rem;">'
                . '<span>Occupé : <strong>' . $occupee . ' / ' . $max . '</strong> colis</span>'
                . '<span>Libres : <strong>' . $libres . '</strong></span>'
                . '</div>'
                . $visualSlots;

            $itemsHtml .= Ui::section($code, $content, '', ['class' => 'rh-card-section']);
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

            $deleteBtn = '<form method="post" action="' . View::url('logistique/rayons/' . $rayon->id . '/supprimer') . '" style="display:inline;" onsubmit="return confirm(\'Êtes-vous sûr de vouloir supprimer ce rayon ?\');">'
                . Form::hidden('_csrf_token', \App\Helpers\Csrf::token())
                . Ui::button('Supprimer', ['type' => 'submit', 'variant' => 'danger', 'class' => 'finea-button-sm'])
                . '</form>';

            $typeBadge = is_object($rayon) ? Ui::badge($rayon->badgeLabel(), 'primary') : Ui::badge($rayon['type_rayon'] ?? 'STANDARD', 'primary');

            $rowsHtml .= '<tr>'
                . '<td><strong>' . View::e($rayon->codeRayon) . '</strong></td>'
                . '<td>' . View::e($rayon->nomRayon) . '<br>' . $typeBadge . '</td>'
                . '<td>' . View::e($rayon->agenceNom ?? 'Agence') . '</td>'
                . '<td>' . $rayon->capaciteOccupee . ' / ' . $rayon->capaciteMax . ' colis (' . $taux . '%)' . ($rayon->poidsMaxAutorise ? '<br><small style="color:#64748b;">Poids max: ' . $rayon->poidsMaxAutorise . ' kg</small>' : '') . '</td>'
                . '<td>' . $badge . '</td>'
                . '<td>' . $deleteBtn . '</td>'
                . '</tr>';
        }

        $tableHtml = '<table class="finea-table">'
            . '<thead><tr><th>Code Rayon</th><th>Nom & Typologie</th><th>Agence</th><th>Capacité</th><th>Statut</th><th>Actions</th></tr></thead>'
            . '<tbody>' . ($rowsHtml !== '' ? $rowsHtml : '<tr><td colspan="6">' . Ui::emptyState('Aucun rayon répertorié') . '</td></tr>') . '</tbody>'
            . '</table>';

        $mainSection = Ui::section('Liste des Rayons configurés', $tableHtml);

        // Modal Form using Form components
        $siteOptions = array_map(fn($s) => ['value' => (string) $s['id'], 'label' => $s['name']], $sites);
        $fieldsHtml = Form::hidden('_csrf_token', \App\Helpers\Csrf::token())
            . Form::selectSearch('agence_id', $siteOptions, '1', ['label' => 'Agence / Entrepôt', 'required' => true])
            . Form::input('code_rayon', ['label' => 'Code du Rayon (Ex: RAY-A1)', 'placeholder' => 'RAY-A1', 'required' => true])
            . Form::input('nom_rayon', ['label' => 'Nom descriptif', 'placeholder' => 'Rayon A1 - Colis Légers', 'required' => true])
            . Form::selectSearch('type_rayon', [
                ['value' => 'STANDARD', 'label' => 'STANDARD (Colis standards)'],
                ['value' => 'EXPRESS', 'label' => 'EXPRESS (DHL / Colis Rapides)'],
                ['value' => 'CARGO_LOURD', 'label' => 'CARGO LOURD (>30kg / Maritime)'],
                ['value' => 'FRAGILE', 'label' => 'FRAGILE (Manipulation délicate)'],
                ['value' => 'SECU_VALEUR', 'label' => 'SÉCURISÉ / VALEUR (Colis Assurés / Haute Valeur)'],
            ], 'STANDARD', ['label' => 'Typologie du Rayon (Affectation Intelligente)', 'required' => true])
            . Form::input('poids_max_autorise', ['label' => 'Poids max. autorisé par colis (kg - optionnel)', 'type' => 'number', 'step' => '0.1', 'placeholder' => 'Ex: 50.0'])
            . Form::input('capacite_max', ['label' => 'Capacité maximale (nombre de colis)', 'type' => 'number', 'value' => '50', 'required' => true])
            . Form::selectSearch('statut', [
                ['value' => 'ACTIF', 'label' => 'ACTIF'],
                ['value' => 'PLEIN', 'label' => 'PLEIN'],
                ['value' => 'MAINTENANCE', 'label' => 'MAINTENANCE'],
            ], 'ACTIF', ['label' => 'Statut']);

        $modalHtml = Ui::modal('modal-add-rayon', 'Ajouter un nouveau Rayon', $fieldsHtml, View::url('logistique/rayons/enregistrer'), [
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

        $fieldsHtml = Form::hidden('_csrf_token', \App\Helpers\Csrf::token())
        . Form::input('delai_gratuit_jours', [
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

    /**
     * Vue transversale "Suivi Colisage" (Logistique) : agence + date, tous types d'envoi confondus,
     * strictement sans montant (Règle point 2 du cahier des charges).
     *
     * @param array<int, array<string, mixed>> $sites
     * @param array<int, array<string, mixed>> $parcels
     * @param array<string, mixed> $kpis
     */
    public static function colisageSuiviPage(array $sites, string $selectedDate, int $selectedAgenceId, array $parcels, array $kpis): string
    {
        $exportQuery = http_build_query(['agence_id' => $selectedAgenceId, 'date' => $selectedDate]);

        $header = Ui::pageHeader(
            'Suivi Colisage Agences',
            'Vue de consultation globale par agence et par date, tous types d\'envoi confondus. Les montants financiers sont volontairement masqués sur ce sous-module logistique.',
            [
                'eyebrow' => 'Logistique • Suivi transversal',
                'class' => 'rh-hero-white',
                'actions' => [
                    Ui::button('Imprimer / PDF (sans montant)', ['href' => 'logistique/colisage/export-pdf?' . $exportQuery, 'variant' => 'secondary']),
                    Ui::button('Exporter Excel (sans montant)', ['href' => 'logistique/colisage/export-excel?' . $exportQuery, 'variant' => 'accent']),
                ],
            ]
        );

        $siteOpts = [['value' => '0', 'label' => '-- Toutes les agences --']];
        foreach ($sites as $s) {
            $siteOpts[] = ['value' => (string) $s['id'], 'label' => $s['name'] . (!empty($s['code']) ? ' (' . $s['code'] . ')' : '')];
        }

        $filterForm = '<form method="get" action="' . View::url('logistique/colisage') . '" class="rh-form-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)) auto; align-items:end;">'
            . Form::select('agence_id', $siteOpts, (string) $selectedAgenceId, ['label' => 'Agence de saisie'])
            . Form::input('date', ['label' => 'Date de saisie', 'type' => 'date', 'value' => $selectedDate])
            . '<div>' . Ui::button('Filtrer', ['type' => 'submit', 'variant' => 'primary']) . '</div>'
            . '</form>';

        $kpisHtml = Dashboard::kpis([
            ['label' => 'Nombre de dossiers', 'value' => number_format((int) ($kpis['totalSaisies'] ?? 0), 0, ',', ' ')],
            ['label' => 'Nombre total de colis', 'value' => number_format((int) ($kpis['totalNombreColis'] ?? 0), 0, ',', ' '), 'tone' => 'primary'],
            ['label' => 'Poids total (tonnage)', 'value' => number_format((float) ($kpis['totalPoids'] ?? 0), 2, ',', ' ') . ' kg', 'tone' => 'success'],
        ]);

        $tableHtml = self::colisageSuiviTable($parcels);
        $tableSection = Ui::section(
            'Liste des colis enregistrés le ' . date('d/m/Y', strtotime($selectedDate)),
            $tableHtml,
            count($parcels) . ' entrée(s)'
        );

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . '<div style="margin: 1.5rem 0;">' . Ui::section('Filtres', $filterForm) . '</div>'
            . $kpisHtml
            . '<div style="margin-top: 1.5rem;">' . $tableSection . '</div>'
            . '</div>'
            . '</div>';
    }

    /** @param array<int, array<string, mixed>> $parcels */
    private static function colisageSuiviTable(array $parcels): string
    {
        if (empty($parcels)) {
            return Ui::emptyState(
                'Aucun colis trouvé pour cette sélection',
                'Aucune saisie n\'a été enregistrée pour l\'agence et la date sélectionnées.'
            );
        }

        $rows = '';
        foreach ($parcels as $p) {
            $trajetDisplay = !empty($p['trajet_code'])
                ? ($p['trajet_code'] . ' (' . $p['trajet_libelle'] . ')')
                : ($p['col_trajet'] ?: ($p['type_expediteur'] ?? 'Non spécifié'));
            $heure = date('H:i', strtotime((string) $p['created_at']));

            $rows .= '<tr>'
                . '<td><a href="' . View::url('colisage/parcels/' . $p['id']) . '"><strong>' . View::e($p['numero_tracking']) . '</strong></a></td>'
                . '<td>' . $heure . '</td>'
                . '<td>' . View::e($p['expediteur_name'] ?: 'Passager / Standard') . '</td>'
                . '<td>' . View::e($p['destinataire_name'] ?: 'Non renseigné') . (!empty($p['destinataire_phone']) ? '<br><small>' . View::e($p['destinataire_phone']) . '</small>' : '') . '</td>'
                . '<td style="text-align:center;"><strong>' . (int) $p['nombre_colis'] . '</strong></td>'
                . '<td style="text-align:right;"><strong>' . number_format((float) $p['poids_total'], 2, ',', ' ') . '</strong></td>'
                . '<td>' . Ui::badge(str_replace('_', ' ➔ ', (string) $trajetDisplay), 'primary') . '</td>'
                . '<td><div>' . View::e($p['agence_depart_name'] ?: 'Départ non défini') . '</div><small>➔ ' . View::e($p['agence_arrivee_name'] ?: 'Destination non définie') . '</small></td>'
                . '<td>' . View::e($p['agent_name']) . '</td>'
                . '<td>' . Ui::badge((string) $p['statut'], 'neutral') . '</td>'
                . '</tr>';
        }

        return '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>N° Tracking</th><th>Heure</th><th>Expéditeur</th><th>Destinataire & Contact</th>'
            . '<th>Colis</th><th>Poids (kg)</th><th>Trajet / Envoi</th><th>Agence Départ / Arrivée</th><th>Agent Saisisseur</th><th>Statut</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }
}
