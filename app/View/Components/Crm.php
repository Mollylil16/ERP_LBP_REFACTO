<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use App\View\Components\Dashboard;
use App\View\Components\Ui;
use App\View\Components\Form;

final class Crm
{
    public static function dashboardPage(array $dashboardModule): string
    {
        $header = Dashboard::header(
            $dashboardModule['label'] ?? 'CRM & Service Client',
            "Espace dédié au suivi client, à l'assistance téléphonique Call Center et à la localisation en temps réel des colis en rayon.",
            [
                'eyebrow' => ($dashboardModule['code'] ?? 'CRM') . ' Dashboard',
                'class' => 'rh-hero-white'
            ]
        );

        $actions = Dashboard::actions([
            ['label' => 'Recherche Call Center (Rayons)', 'href' => 'crm/callcenter', 'icon' => '<svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>', 'variant' => 'accent'],
            ['label' => 'Suivi des Colis', 'href' => 'colisage/parcels', 'icon' => '<svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>', 'variant' => 'primary'],
        ], [
            'title' => 'Assistance & Support',
            'class' => 'finea-section-card',
        ]);

        $openCallCenterBtn = Ui::button('+ Ouvrir la recherche Call Center', ['href' => 'crm/callcenter', 'variant' => 'accent']);
        $introContent = Ui::emptyState(
            'Outil Call Center & Assistance Directe',
            'Accédez à l\'outil de visualisation en temps réel de la position des colis en rayon pour renseigner les clients rapidement au téléphone.'
        ) . '<div style="margin-top: 1rem;">' . $openCallCenterBtn . '</div>';

        $mainSection = Ui::section('Service Client & Rayons', $introContent);

        return '<div class="finea-shell crm-dashboard">'
            . '<div class="finea-container">'
            . $header
            . '<div class="rh-dashboard-grid" style="margin-top: 2rem;">'
            . '<div class="rh-dashboard-main">'
            . $mainSection
            . '</div>'
            . '<div class="rh-dashboard-side">'
            . $actions
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';
    }

    /**
     * Page de visualisation Call Center en temps réel.
     *
     * @param array<string, mixed>|null $searchResult
     * @param array<int, mixed> $rayonsOverview
     */
    public static function callCenterPage(?array $searchResult, array $rayonsOverview, string $searchQuery = ''): string
    {
        $header = Ui::pageHeader(
            'Call Center - Consultation en Temps Réel',
            'Visualisation instantanée de l\'emplacement des colis dans les rayons pour assistance téléphonique et renseignements clients.',
            [
                'eyebrow' => 'Support Client & Recherche Rayon',
                'class' => 'rh-hero-white',
            ]
        );

        $lookupComponent = self::callCenterRayonLookupComponent($searchResult, $rayonsOverview, $searchQuery);

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . '<div style="margin-top: 2rem;">'
            . $lookupComponent
            . '</div>'
            . '</div>'
            . '</div>';
    }

    /**
     * Composant de recherche Call Center avec position exacte en rayon et statut via Ui et Form components.
     *
     * @param array<string, mixed>|null $colis
     * @param array<int, mixed> $rayons
     */
    public static function callCenterRayonLookupComponent(?array $colis, array $rayons, string $searchQuery = ''): string
    {
        $searchInput = Form::input('q', [
            'value' => $searchQuery,
            'placeholder' => 'Entrez N° de tracking, Téléphone ou Nom du destinataire...',
            'fieldClass' => 'finea-field--grow',
        ]);
        $submitBtn = Ui::button('Rechercher', ['type' => 'submit', 'variant' => 'accent']);

        $searchForm = '<form method="get" action="' . View::url('crm/callcenter') . '" class="rh-compact-form" style="display:flex; gap:0.75rem; margin-bottom: 2rem;">'
            . $searchInput
            . $submitBtn
            . '</form>';

        $resultCard = '';
        if ($searchQuery !== '') {
            if ($colis !== null) {
                $tone = $colis['statut'] === 'RETIRÉ' ? 'neutral' : ($colis['statut'] === 'RÉCEPTIONNÉ' ? 'success' : 'info');
                $statusBadge = Ui::badge($colis['statut'], $tone);
                $rayonText = !empty($colis['code_rayon']) ? View::e($colis['code_rayon']) . ' (' . View::e($colis['nom_rayon'] ?? '') . ')' : 'Non affecté / En transit';

                $fraisGardiennage = (float) ($colis['frais_gardiennage_appliques'] ?? 0);
                $gardiennageBadge = Logistique::colisGardiennageBadge($fraisGardiennage);

                $detailsHtml = '<div class="rh-dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">'
                    . '<div><p class="rh-eyebrow">Position en Rayon</p><h4>📍 ' . $rayonText . '</h4></div>'
                    . '<div><p class="rh-eyebrow">Destinataire</p><h4>' . View::e($colis['destinataire_nom'] ?? 'Client') . '</h4><small>' . View::e($colis['destinataire_phone'] ?? '') . '</small></div>'
                    . '<div><p class="rh-eyebrow">Arrivée en agence</p><h4>' . ($colis['date_arrivee_agence'] ? date('d/m/Y H:i', strtotime($colis['date_arrivee_agence'])) : 'Non renseignée') . '</h4></div>'
                    . '<div><p class="rh-eyebrow">Gardiennage</p><h4>' . $gardiennageBadge . '</h4></div>'
                    . '</div>';

                $resultCard = Ui::section(
                    'Colis N° ' . View::e($colis['numero_tracking']),
                    $detailsHtml,
                    'Statut : ' . $statusBadge,
                    ['class' => 'rh-card-section']
                );
            } else {
                $resultCard = Ui::emptyState(
                    'Aucun résultat',
                    'Aucun colis ne correspond à la recherche "' . View::e($searchQuery) . '".'
                );
            }
        }

        $rayonsOverviewHtml = Logistique::rayonsStockOverviewComponent($rayons);
        $rayonsSection = Ui::section('Vue globale de la capacité des rayons (Lecture Seule Call Center)', $rayonsOverviewHtml);

        return $searchForm
            . ($resultCard !== '' ? '<div style="margin-bottom: 2rem;">' . $resultCard . '</div>' : '')
            . $rayonsSection;
    }
}
