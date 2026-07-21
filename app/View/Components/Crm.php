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

        return '<div class="finea-shell crm-dashboard">'
            . '<div class="finea-container">'
            . $header
            . '<div class="rh-dashboard-grid" style="margin-top: 2rem;">'
            . '<div class="rh-dashboard-main">'
            . '<div class="finea-empty-state" style="padding: 2rem; background: #ffffff; border-radius: 10px; border: 1px solid #e2e8f0;">'
            . '<h3 style="margin-top: 0; color: #0f172a;">Outil Call Center & Assistance Directe</h3>'
            . '<p style="color: #64748b;">Accédez à l\'outil de visualisation en temps réel de la position des colis en rayon pour renseigner les clients rapidement au téléphone.</p>'
            . '<a href="' . View::url('crm/callcenter') . '" class="finea-btn finea-btn-accent">+ Ouvrir la recherche Call Center</a>'
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
     * Composant de recherche Call Center avec position exacte en rayon et statut.
     *
     * @param array<string, mixed>|null $colis
     * @param array<int, mixed> $rayons
     */
    public static function callCenterRayonLookupComponent(?array $colis, array $rayons, string $searchQuery = ''): string
    {
        $searchForm = '<form method="get" action="' . View::url('crm/callcenter') . '" style="display: flex; gap: 0.75rem; margin-bottom: 2rem;">'
            . '<input type="text" name="q" value="' . htmlspecialchars($searchQuery) . '" placeholder="Entrez N° de tracking, Téléphone ou Nom du destinataire..." style="flex: 1; padding: 0.75rem 1rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem;">'
            . '<button type="submit" class="finea-btn finea-btn-accent" style="padding: 0 1.5rem; font-weight: 600;">Rechercher</button>'
            . '</form>';

        $resultCard = '';
        if ($searchQuery !== '') {
            if ($colis !== null) {
                $statusColor = $colis['statut'] === 'RETIRÉ' ? '#64748b' : ($colis['statut'] === 'RÉCEPTIONNÉ' ? '#10b981' : '#2563eb');
                $rayonText = !empty($colis['code_rayon']) ? htmlspecialchars($colis['code_rayon']) . ' (' . htmlspecialchars($colis['nom_rayon'] ?? '') . ')' : 'Non affecté / En transit';

                $resultCard = '<div style="background: #ffffff; border: 2px solid #2563eb; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">'
                    . '<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">'
                    . '<div>'
                    . '<span style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: #64748b;">N° Tracking</span>'
                    . '<h3 style="font-size: 1.3rem; font-weight: 800; color: #0f172a; margin: 0.1rem 0 0 0;">' . htmlspecialchars($colis['numero_tracking']) . '</h3>'
                    . '</div>'
                    . '<span style="background: ' . $statusColor . '15; color: ' . $statusColor . '; padding: 0.35rem 0.85rem; border-radius: 9999px; font-size: 0.85rem; font-weight: 700;">'
                    . htmlspecialchars($colis['statut'])
                    . '</span>'
                    . '</div>'

                    . '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; background: #f8fafc; padding: 1.25rem; border-radius: 8px; border: 1px solid #e2e8f0;">'
                    . '<div>'
                    . '<span style="font-size: 0.75rem; color: #64748b; font-weight: 600;">POSITION EN RAYON</span>'
                    . '<p style="font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 0.2rem 0 0 0;">📍 ' . $rayonText . '</p>'
                    . '</div>'
                    . '<div>'
                    . '<span style="font-size: 0.75rem; color: #64748b; font-weight: 600;">DESTINATAIRE</span>'
                    . '<p style="font-size: 0.95rem; font-weight: 600; color: #0f172a; margin: 0.2rem 0 0 0;">' . htmlspecialchars($colis['destinataire_nom'] ?? 'Client') . '</p>'
                    . '<small style="color: #64748b;">' . htmlspecialchars($colis['destinataire_phone'] ?? '') . '</small>'
                    . '</div>'
                    . '<div>'
                    . '<span style="font-size: 0.75rem; color: #64748b; font-weight: 600;">DATE D\'ARRIVÉE</span>'
                    . '<p style="font-size: 0.95rem; font-weight: 600; color: #0f172a; margin: 0.2rem 0 0 0;">' . ($colis['date_arrivee_agence'] ? date('d/m/Y H:i', strtotime($colis['date_arrivee_agence'])) : 'Non renseignée') . '</p>'
                    . '</div>'
                    . '<div>'
                    . '<span style="font-size: 0.75rem; color: #64748b; font-weight: 600;">GARDIENNAGE APPLIQUÉ</span>'
                    . '<p style="font-size: 0.95rem; font-weight: 700; color: #b91c1c; margin: 0.2rem 0 0 0;">' . number_format((float) ($colis['frais_gardiennage_appliques'] ?? 0), 0, ',', ' ') . ' XOF</p>'
                    . '</div>'
                    . '</div>'
                    . '</div>';
            } else {
                $resultCard = '<div style="background: #fff1f2; color: #9f1239; padding: 1.25rem; border-radius: 8px; border: 1px solid #fecdd3; margin-bottom: 2rem;">'
                    . 'Aucun colis correspondant à la recherche "<strong>' . htmlspecialchars($searchQuery) . '</strong>".'
                    . '</div>';
            }
        }

        $rayonsListHtml = Logistique::rayonsStockOverviewComponent($rayons);

        return $searchForm
            . $resultCard
            . '<div style="margin-top: 2rem;">'
            . '<h3 style="font-size: 1.1rem; font-weight: 700; color: #0f172a; margin-bottom: 1rem;">Vue globale de la capacité des rayons (Lecture Seule Call Center)</h3>'
            . $rayonsListHtml
            . '</div>';
    }
}
