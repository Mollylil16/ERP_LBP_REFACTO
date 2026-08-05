<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

final class CallCenter
{
    /**
     * Page de recherche d'un colis (tracking/téléphone/nom) avec sa position en rayon —
     * déplacée depuis le module CRM où elle n'avait pas sa place (aucun lien avec la
     * gestion de la relation client).
     *
     * @param array<string, mixed>|null $colis
     * @param array<int, mixed> $rayons
     */
    public static function colisLookupPage(?array $colis, array $rayons, string $searchQuery = ''): string
    {
        $header = Ui::pageHeader(
            'Recherche Colis & Position en Rayon',
            'Localisez instantanément un colis (numéro de tracking, téléphone ou nom du destinataire) pour renseigner un client au téléphone.',
            [
                'eyebrow' => 'Support Client & Recherche Rayon',
                'class' => 'rh-hero-white',
            ]
        );

        $lookupComponent = self::colisLookupComponent($colis, $rayons, $searchQuery);

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
     * @param array<string, mixed>|null $colis
     * @param array<int, mixed> $rayons
     */
    private static function colisLookupComponent(?array $colis, array $rayons, string $searchQuery = ''): string
    {
        $searchInput = Form::input('q', [
            'value' => $searchQuery,
            'placeholder' => 'Entrez N° de tracking, Téléphone ou Nom du destinataire...',
            'fieldClass' => 'finea-field--grow',
        ]);
        $submitBtn = Ui::button('Rechercher', ['type' => 'submit', 'variant' => 'accent']);

        $searchForm = '<form method="get" action="' . View::url('call-center/recherche-colis') . '" class="rh-compact-form" style="display:flex; gap:0.75rem; margin-bottom: 2rem;">'
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
                    . '<div><p class="rh-eyebrow">Position en Rayon</p><h4>' . $rayonText . '</h4></div>'
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
        $rayonsSection = Ui::section('Vue globale de la capacité des rayons', $rayonsOverviewHtml);

        return $searchForm
            . ($resultCard !== '' ? '<div style="margin-bottom: 2rem;">' . $resultCard . '</div>' : '')
            . $rayonsSection;
    }
}
