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
            $dashboardModule['label'],
            "Gestion intégrée de la logistique : affectation automatique dans les rayons, capacité des stocks et suivi des délais de gardiennage.",
            [
                'eyebrow' => $dashboardModule['code'] . ' Dashboard',
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

        return '<div class="finea-shell logistique-dashboard">'
            . '<div class="finea-container">'
            . $header
            . '<div class="rh-dashboard-grid" style="margin-top: 2rem;">'
            . '<div class="rh-dashboard-main">'
            . $kpis
            . '<div style="margin-top: 2rem;">'
            . '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1rem;">'
            . '<div>'
            . '<h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; margin: 0;">Aperçu des Rayons & Capacité de Stockage</h3>'
            . '<p style="color: #64748b; font-size: 0.875rem; margin-top: 0.2rem;">Taux d\'occupation et affectation automatique des colis.</p>'
            . '</div>'
            . Ui::button('Gérer les rayons', ['href' => 'logistique/rayons', 'variant' => 'secondary'])
            . '</div>'
            . $stockOverview
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
     * Composant affichant les cartes des rayons de stock avec jauges de capacité.
     *
     * @param array<int, Rayon> $rayons
     */
    public static function rayonsStockOverviewComponent(array $rayons): string
    {
        if (empty($rayons)) {
            return '<div class="finea-empty-state" style="padding: 2rem; text-align: center; background: #f8fafc; border-radius: 8px; border: 1px dashed #cbd5e1;">'
                . '<p style="color: #64748b; margin: 0;">Aucun rayon configuré pour le moment.</p>'
                . '<a href="' . View::url('logistique/rayons') . '" class="finea-btn finea-btn-accent" style="margin-top: 1rem; display: inline-block;">+ Créer un premier rayon</a>'
                . '</div>';
        }

        $html = '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.25rem;">';

        foreach ($rayons as $rayon) {
            $taux = $rayon->tauxOccupation();
            $badgeColor = '#10b981'; // vert
            $badgeText = 'Disponible';

            if ($taux >= 100 || $rayon->statut === 'PLEIN') {
                $badgeColor = '#ef4444'; // rouge
                $badgeText = 'Saturé';
            } elseif ($taux >= 80) {
                $badgeColor = '#f59e0b'; // orange
                $badgeText = 'Quasi-Plein';
            } elseif ($rayon->statut === 'MAINTENANCE') {
                $badgeColor = '#64748b'; // gris
                $badgeText = 'Maintenance';
            }

            $html .= '<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">'
                . '<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">'
                . '<div>'
                . '<span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #475569; letter-spacing: 0.05em;">' . htmlspecialchars($rayon->codeRayon) . '</span>'
                . '<h4 style="font-size: 1rem; font-weight: 600; color: #0f172a; margin: 0.2rem 0 0 0;">' . htmlspecialchars($rayon->nomRayon) . '</h4>'
                . '</div>'
                . '<span style="background: ' . $badgeColor . '15; color: ' . $badgeColor . '; padding: 0.25rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600;">'
                . $badgeText
                . '</span>'
                . '</div>'

                . '<div style="margin: 1rem 0;">'
                . '<div style="display: flex; justify-content: space-between; font-size: 0.85rem; color: #475569; margin-bottom: 0.4rem;">'
                . '<span>Occupation</span>'
                . '<strong>' . $rayon->capaciteOccupee . ' / ' . $rayon->capaciteMax . ' colis (' . $taux . '%)</strong>'
                . '</div>'
                . '<div style="width: 100%; background: #e2e8f0; height: 8px; border-radius: 4px; overflow: hidden;">'
                . '<div style="width: ' . min(100, $taux) . '%; background: ' . $badgeColor . '; height: 100%; border-radius: 4px; transition: width 0.3s;"></div>'
                . '</div>'
                . '</div>'

                . '<div style="display: flex; justify-content: space-between; align-items: center; pt: 0.5rem; border-top: 1px solid #f1f5f9; font-size: 0.8rem; color: #64748b;">'
                . '<span>Agence: ' . htmlspecialchars($rayon->agenceNom ?? 'Principale') . '</span>'
                . '<span style="font-weight: 500; color: #0f172a;">' . $rayon->placesDisponibles() . ' libres</span>'
                . '</div>'
                . '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Page complète de gestion des rayons logistiques.
     *
     * @param array<int, Rayon> $rayons
     * @param array<int, array<string, mixed>> $sites
     */
    public static function rayonsListPage(array $rayons, array $sites, ?string $successMsg = null, ?string $errorMsg = null): string
    {
        $header = Ui::pageHeader(
            'Gestion des Rayons & Capacités',
            'Définition des rayons d\'entrepôt par agence, capacité maximale et affectation dynamique des colis.',
            [
                'eyebrow' => 'Emplacements & Stockage Logistique',
                'class' => 'rh-hero-white',
            ]
        );

        $alerts = '';
        if ($successMsg) {
            $alerts .= '<div style="background:#dcfce7; color:#15803d; padding:1rem; border-radius:8px; margin-bottom:1.5rem; border:1px solid #bbf7d0;">'
                . htmlspecialchars($successMsg) . '</div>';
        }
        if ($errorMsg) {
            $alerts .= '<div style="background:#fee2e2; color:#b91c1c; padding:1rem; border-radius:8px; margin-bottom:1.5rem; border:1px solid #fecaca;">'
                . htmlspecialchars($errorMsg) . '</div>';
        }

        $overview = self::rayonsStockOverviewComponent($rayons);
        $createForm = self::rayonFormModal($sites);

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . $alerts
            . '<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-top: 2rem;">'
            . '<div>'
            . '<h3 style="font-size: 1.1rem; font-weight: 700; color: #0f172a; margin-bottom: 1rem;">Rayons d\'Entrepôt & Taux d\'Occupation</h3>'
            . $overview
            . '</div>'
            . '<div>'
            . '<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">'
            . '<h3 style="font-size: 1.05rem; font-weight: 700; color: #0f172a; margin-top: 0; margin-bottom: 1rem;">+ Ajouter un Rayon</h3>'
            . $createForm
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';
    }

    /**
     * Formulaire d'ajout / édition d'un rayon.
     */
    public static function rayonFormModal(array $sites, ?Rayon $rayon = null): string
    {
        $actionUrl = View::url('logistique/rayons/enregistrer');
        $siteOpts = [];
        foreach ($sites as $s) {
            $siteOpts[] = ['value' => (string) $s['id'], 'label' => $s['name'] ?? 'Agence #' . $s['id']];
        }

        return '<form method="post" action="' . $actionUrl . '">'
            . '<input type="hidden" name="id" value="' . ($rayon ? $rayon->id : 0) . '">'

            . Form::select('agence_id', 'Agence / Entrepôt', $siteOpts, (string) ($rayon ? $rayon->agenceId : ($sites[0]['id'] ?? 1)))
            . Form::input('code_rayon', 'Code du Rayon', 'text', $rayon ? $rayon->codeRayon : '', ['placeholder' => 'ex: RAY-A1', 'required' => true])
            . Form::input('nom_rayon', 'Nom / Description du Rayon', 'text', $rayon ? $rayon->nomRayon : '', ['placeholder' => 'ex: Etagère A1 - Petits paquets', 'required' => true])
            . Form::input('capacite_max', 'Capacité Maximale (Nombre de colis)', 'number', (string) ($rayon ? $rayon->capaciteMax : 50), ['min' => 1, 'required' => true])

            . Form::select('statut', 'Statut du Rayon', [
                ['value' => 'ACTIF', 'label' => 'ACTIF (Recevoir automatiquement)'],
                ['value' => 'PLEIN', 'label' => 'PLEIN (Saturé)'],
                ['value' => 'MAINTENANCE', 'label' => 'MAINTENANCE (Inactif)'],
            ], $rayon ? $rayon->statut : 'ACTIF')

            . '<div style="margin-top: 1.5rem; text-align: right;">'
            . Ui::button($rayon ? 'Enregistrer les modifications' : 'Créer le Rayon', ['type' => 'submit', 'variant' => 'accent'])
            . '</div>'
            . '</form>';
    }

    /**
     * Page de paramétrage des délais de gardiennage et tarification des pénalités.
     */
    public static function parametresPage(LogistiqueSettings $settings, array $sites, ?string $successMsg = null): string
    {
        $header = Ui::pageHeader(
            'Délais de Récupération & Gardiennage',
            'Configuration du délai gratuit de retrait des colis et tarification des frais supplémentaires appliqués lors du dépassement.',
            [
                'eyebrow' => 'Règles de Stockage & Pénalités Logistiques',
                'class' => 'rh-hero-white',
            ]
        );

        $alerts = '';
        if ($successMsg) {
            $alerts .= '<div style="background:#dcfce7; color:#15803d; padding:1rem; border-radius:8px; margin-bottom:1.5rem; border:1px solid #bbf7d0;">'
                . htmlspecialchars($successMsg) . '</div>';
        }

        $formContent = self::delaiFraisConfigComponent($settings);

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . $alerts
            . '<div style="max-width: 700px; margin: 2rem auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.04);">'
            . $formContent
            . '</div>'
            . '</div>'
            . '</div>';
    }

    /**
     * Composant de formulaire pour modifier les paramètres de gardiennage.
     */
    public static function delaiFraisConfigComponent(LogistiqueSettings $settings): string
    {
        return '<form method="post" action="' . View::url('logistique/parametres/enregistrer') . '">'
            . '<h3 style="font-size: 1.1rem; font-weight: 700; color: #0f172a; margin-top: 0; margin-bottom: 1.5rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem;">'
            . 'Paramètres Globaux de Stockage'
            . '</h3>'

            . '<div style="margin-bottom: 1.25rem;">'
            . Form::input('delai_gratuit_jours', 'Délai de Récupération Gratuit (en jours)', 'number', (string) $settings->delaiGratuitJours, [
                'min' => 0,
                'required' => true,
                'help' => 'Nombre de jours calendaires accordés au destinataire pour retirer son colis sans pénalité.'
            ])
            . '</div>'

            . '<div style="margin-bottom: 1.25rem;">'
            . Form::input('frais_gardiennage_par_jour', 'Tarif des Frais de Gardiennage (par jour de dépassement en XOF)', 'number', (string) $settings->fraisGardiennageParJour, [
                'min' => 0,
                'step' => '50',
                'required' => true,
                'help' => 'Montant ajouté automatiquement à la facture de retrait pour chaque jour de retard.'
            ])
            . '</div>'

            . '<div style="margin-bottom: 1.5rem; background: #f8fafc; padding: 1rem; border-radius: 8px; border: 1px solid #e2e8f0;">'
            . '<label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer; font-weight: 600; color: #0f172a;">'
            . '<input type="checkbox" name="auto_assign_rayon" value="1" ' . ($settings->autoAssignRayon ? 'checked' : '') . ' style="width: 18px; height: 18px; accent-color: #2563eb;">'
            . 'Activer l\'affectation automatique des colis dans les rayons lors de la réception'
            . '</label>'
            . '<p style="color: #64748b; font-size: 0.85rem; margin: 0.4rem 0 0 2rem;">'
            . 'Le système placera automatiquement chaque nouveau colis dans le premier rayon actif disposant de places libres.'
            . '</p>'
            . '</div>'

            . '<div style="text-align: right; margin-top: 2rem;">'
            . Ui::button('Enregistrer les paramètres', ['type' => 'submit', 'variant' => 'accent'])
            . '</div>'
            . '</form>';
    }

    /**
     * Badge d'affichage des pénalités / gardiennage pour une fiche colis.
     *
     * @param array{
     *     delaiGratuitJours: int,
     *     dateArrivee: ?string,
     *     dateLimiteRetrait: string,
     *     joursRetard: int,
     *     fraisParJour: float,
     *     totalFraisGardiennage: float,
     *     estHorsDelai: bool
     * } $gardiennageInfo
     */
    public static function colisGardiennageBadge(array $gardiennageInfo): string
    {
        if (!$gardiennageInfo['estHorsDelai']) {
            return '<div style="display: inline-flex; align-items: center; gap: 0.5rem; background: #f0fdf4; color: #166534; padding: 0.4rem 0.75rem; border-radius: 6px; border: 1px solid #bbf7d0; font-size: 0.85rem;">'
                . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'
                . 'Dans le délai gratuit (Limite: ' . date('d/m/Y', strtotime($gardiennageInfo['dateLimiteRetrait'])) . ')'
                . '</div>';
        }

        return '<div style="display: inline-flex; flex-direction: column; gap: 0.2rem; background: #fef2f2; color: #991b1b; padding: 0.6rem 0.85rem; border-radius: 8px; border: 1px solid #fecaca; font-size: 0.85rem;">'
            . '<div style="display: flex; align-items: center; gap: 0.4rem; font-weight: 700;">'
            . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>'
            . 'Délai de garde dépassé (+' . $gardiennageInfo['joursRetard'] . ' jour' . ($gardiennageInfo['joursRetard'] > 1 ? 's' : '') . ')'
            . '</div>'
            . '<div>'
            . 'Frais de gardiennage à percevoir : <strong>' . number_format($gardiennageInfo['totalFraisGardiennage'], 0, ',', ' ') . ' XOF</strong>'
            . '</div>'
            . '</div>';
    }
}
