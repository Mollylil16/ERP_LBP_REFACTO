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
        $header = '<div class="crm-hero" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-radius: 20px; padding: 35px 40px; color: #ffffff; margin-bottom: 28px; position: relative; overflow: hidden; box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.4); border: 1px solid rgba(255, 255, 255, 0.08);">'
            . '<div style="position: absolute; top: -60px; right: -60px; width: 250px; height: 250px; background: radial-gradient(circle, rgba(37, 99, 235, 0.3) 0%, rgba(0,0,0,0) 70%); border-radius: 50%; pointer-events: none;"></div>'
            . '<span style="display: inline-flex; align-items: center; gap: 8px; background: rgba(37, 99, 235, 0.2); color: #60a5fa; border: 1px solid rgba(96, 165, 250, 0.3); padding: 6px 16px; border-radius: 30px; font-size: 0.82rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 14px;">'
            . '<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>'
            . 'CRM & Relation Client LBP'
            . '</span>'
            . '<h1 style="font-size: 2.2rem; font-weight: 800; margin: 0 0 8px 0; letter-spacing: -0.5px; color: #ffffff;">Gestion Commerciale & Portefeuille Clients</h1>'
            . '<p style="font-size: 1.05rem; color: #94a3b8; max-width: 650px; margin: 0 0 24px 0; line-height: 1.6;">Suivi personnalisé des comptes importateurs, gestion du pipeline d\'opportunités, historique des interactions et relances téléphoniques Call Center.</p>'
            . '<div style="display: flex; gap: 14px; flex-wrap: wrap;">'
            . Ui::button('+ Nouveau Client / Prospect', ['href' => 'crm/clients/nouveau', 'variant' => 'accent', 'style' => 'background: #2563eb; padding: 12px 24px; border-radius: 10px; font-weight: 700; color: #ffffff; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;'])
            . Ui::button('Consulter l\'Annuaire Clients ➔', ['href' => 'crm/clients', 'variant' => 'secondary', 'style' => 'background: rgba(255, 255, 255, 0.1); color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.2); padding: 12px 24px; border-radius: 10px; font-weight: 600; text-decoration: none; backdrop-filter: blur(8px);'])
            . '</div>'
            . '</div>';

        $actions = Dashboard::actions([
            ['label' => 'Annuaire Clients & Prospects', 'href' => 'crm/clients', 'icon' => '<svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>', 'variant' => 'accent'],
            ['label' => 'Recherche Call Center (Rayons)', 'href' => 'call-center/recherche-colis', 'icon' => '<svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>', 'variant' => 'primary'],
            ['label' => 'Suivi des Colis Client', 'href' => 'colisage/parcels', 'icon' => '<svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>', 'variant' => 'secondary'],
        ], [
            'title' => 'Accès & Opérations Rapides',
            'class' => 'finea-section-card',
        ]);

        $introContent = Ui::emptyState(
            'Portefeuille Commercial & Suivi des Comptes',
            'Consultez, recherchez et créez des fiches client/prospect, suivez vos interactions (appels, emails, visites) et gérez votre pipeline commercial.'
        ) . '<div style="margin-top: 1.2rem; display:flex; gap:0.85rem;">' 
          . Ui::button('+ Ajouter un client', ['href' => 'crm/clients/nouveau', 'variant' => 'accent']) 
          . Ui::button('Ouvrir l\'annuaire', ['href' => 'crm/clients', 'variant' => 'secondary']) 
          . '</div>';

        $mainSection = Ui::section('Gestion Client & CRM', $introContent);

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

    /** @return array<int, array{value: string, label: string}> */
    private static function crmStatusOptions(): array
    {
        return [
            ['value' => 'prospect', 'label' => 'Prospect'],
            ['value' => 'actif', 'label' => 'Client actif'],
            ['value' => 'dormant', 'label' => 'Dormant'],
            ['value' => 'perdu', 'label' => 'Perdu'],
        ];
    }

    /** @return array<int, array{value: string, label: string}> */
    private static function opportunityStageOptions(): array
    {
        return [
            ['value' => 'qualification', 'label' => 'Qualification'],
            ['value' => 'proposition', 'label' => 'Proposition'],
            ['value' => 'negociation', 'label' => 'Négociation'],
            ['value' => 'gagnee', 'label' => 'Gagnée'],
            ['value' => 'perdue', 'label' => 'Perdue'],
        ];
    }

    private static function crmStatusTone(string $status): string
    {
        return match ($status) {
            'actif' => 'success',
            'prospect' => 'primary',
            'dormant' => 'warning',
            'perdu' => 'danger',
            default => 'neutral',
        };
    }

    private static function opportunityStageTone(string $stage): string
    {
        return match ($stage) {
            'gagnee' => 'success',
            'perdue' => 'danger',
            'negociation' => 'warning',
            default => 'neutral',
        };
    }

    /**
     * Annuaire clients : liste/recherche des clients réels (lbp_clients) sous forme de CARDS modernes.
     *
     * @param array<int, array<string, mixed>> $clients
     * @param array<string, string> $filters
     * @param array{currentPage: int, totalPages: int, itemsPerPage: int, totalItems: int} $pagination
     */
    public static function clientsListPage(array $clients, array $filters, array $pagination): string
    {
        $header = '<div class="crm-hero" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-radius: 18px; padding: 32px 36px; color: #ffffff; margin-bottom: 24px; position: relative; overflow: hidden; box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.4); border: 1px solid rgba(255, 255, 255, 0.08); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;">'
            . '<div>'
            . '<span style="display: inline-flex; align-items: center; gap: 6px; background: rgba(37, 99, 235, 0.2); color: #60a5fa; border: 1px solid rgba(96, 165, 250, 0.3); padding: 4px 14px; border-radius: 20px; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px;">'
            . '<svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" stroke-width="2" fill="none"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>'
            . 'Annuaire CRM moderne'
            . '</span>'
            . '<h1 style="font-size: 2rem; font-weight: 800; margin: 8px 0 4px 0; color: #ffffff;">Portefeuille Clients & Prospects</h1>'
            . '<p style="color: #94a3b8; font-size: 0.95rem; margin: 0;">Affichage en cartes interactives — <strong>' . (int)$pagination['totalItems'] . '</strong> fiche(s) enregistrée(s).</p>'
            . '</div>'
            . Ui::button('+ Nouveau Client / Prospect', ['href' => 'crm/clients/nouveau', 'variant' => 'accent', 'style' => 'background: #2563eb; padding: 12px 24px; border-radius: 10px; font-weight: 700; color: #ffffff; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;'])
            . '</div>';

        $statusOpts = array_merge([['value' => '', 'label' => 'Tous les statuts']], self::crmStatusOptions());

        $filterForm = '<form method="get" action="' . View::url('crm/clients') . '" class="rh-form-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); align-items:end; gap: 14px;">'
            . Form::input('q', ['label' => 'Recherche (nom, téléphone, email)', 'value' => $filters['q'], 'placeholder' => 'Tapez un nom, numéro...'])
            . Form::select('crm_status', $statusOpts, $filters['crm_status'], ['label' => 'Statut CRM'])
            . '<div>' . Ui::button('Filtrer les cartes', ['type' => 'submit', 'variant' => 'primary', 'style' => 'width: 100%; height: 44px; font-weight: 700;']) . '</div>'
            . '</form>';

        $cardsHtml = self::clientsCardsGrid($clients);

        $paginationHtml = '';
        if ($pagination['totalPages'] > 1) {
            $baseParams = $filters;
            $paginationHtml = '<div style="margin-top: 2rem;">' . Rh::pagination(
                $pagination['currentPage'],
                $pagination['totalPages'],
                static fn(int $page): string => View::url('crm/clients?' . http_build_query($baseParams + ['page' => $page]))
            ) . '</div>';
        }

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . '<div style="margin-bottom: 1.5rem;">' . Ui::section('Filtres de recherche', $filterForm) . '</div>'
            . $cardsHtml
            . $paginationHtml
            . '</div>'
            . '</div>';
    }

    /**
     * Grille de Cartes Client Ultra-Moderne
     * @param array<int, array<string, mixed>> $clients
     */
    private static function clientsCardsGrid(array $clients): string
    {
        if (empty($clients)) {
            return Ui::emptyState('Aucun client trouvé', 'Aucun client ne correspond aux critères sélectionnés. Cliquez sur "+ Nouveau Client" pour créer le premier compte.');
        }

        $cards = '<div class="crm-client-cards-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;">';

        foreach ($clients as $c) {
            $name = (string) ($c['name'] ?? 'Client');
            $initials = mb_strtoupper(mb_substr($name, 0, 2));
            $phone = (string) ($c['phone'] ?? '');
            $email = (string) ($c['email'] ?? '');
            $secteur = (string) ($c['secteur_activite'] ?? 'Standard');
            $status = (string) ($c['crm_status'] ?? 'prospect');
            $owner = (string) ($c['commercial_owner_name'] ?? 'Équipe LBP');
            $type = (string) ($c['type'] ?? 'standard');

            $cards .= '<article class="crm-client-card" style="background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 24px; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.05); transition: all 0.25s ease; display: flex; flex-direction: column; justify-content: space-between; position: relative;">'
                . '<div>'
                . '<div style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 16px; gap: 10px;">'
                . '<div style="display: flex; align-items: center; gap: 14px;">'
                . '<div style="width: 48px; height: 48px; border-radius: 14px; background: linear-gradient(135deg, #1d2b57 0%, #2563eb 100%); color: #ffffff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.05rem; flex-shrink: 0; box-shadow: 0 6px 16px rgba(37, 99, 235, 0.25);">' . View::e($initials) . '</div>'
                . '<div>'
                . '<a href="' . View::url('crm/clients/' . $c['id']) . '" style="text-decoration: none; color: #0f172a;"><h3 style="font-size: 1.08rem; font-weight: 800; margin: 0 0 2px 0; color: #1d2b57; line-height: 1.3;">' . View::e($name) . '</h3></a>'
                . '<span style="font-size: 0.76rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">' . View::e(ucfirst($type)) . '</span>'
                . '</div>'
                . '</div>'
                . Ui::badge(ucfirst($status), self::crmStatusTone($status))
                . '</div>'

                . '<div style="background: #f8fafc; border-radius: 12px; padding: 14px; margin-bottom: 18px; display: flex; flex-direction: column; gap: 8px; font-size: 0.88rem;">'
                . ($phone !== '' ? '<div style="display: flex; align-items: center; gap: 10px; color: #334155;"><svg viewBox="0 0 24 24" width="15" height="15" stroke="#2563eb" stroke-width="2" fill="none"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg><a href="tel:' . View::e($phone) . '" style="color: #0f172a; font-weight: 600; text-decoration: none;">' . View::e($phone) . '</a></div>' : '')
                . ($email !== '' ? '<div style="display: flex; align-items: center; gap: 10px; color: #334155;"><svg viewBox="0 0 24 24" width="15" height="15" stroke="#2563eb" stroke-width="2" fill="none"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg><a href="mailto:' . View::e($email) . '" style="color: #2563eb; text-decoration: none; word-break: break-all;">' . View::e($email) . '</a></div>' : '')
                . '<div style="display: flex; align-items: center; gap: 10px; color: #64748b;"><svg viewBox="0 0 24 24" width="15" height="15" stroke="#64748b" stroke-width="2" fill="none"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg><span>' . View::e($secteur) . '</span></div>'
                . '</div>'
                . '</div>'

                . '<div style="display: flex; align-items: center; justify-content: space-between; padding-top: 14px; border-top: 1px solid #f1f5f9;">'
                . '<span style="font-size: 0.8rem; color: #64748b;">Responsable : <strong style="color: #0f172a;">' . View::e($owner) . '</strong></span>'
                . Ui::button('Consulter ➔', ['href' => 'crm/clients/' . $c['id'], 'variant' => 'accent', 'style' => 'background: #1d2b57; color: #fff; font-size: 0.82rem; font-weight: 700; border-radius: 8px; padding: 8px 16px;'])
                . '</div>'
                . '</article>';
        }

        return $cards . '</div>';
    }

    /**
     * Formulaire de création d'un client/prospect.
     *
     * @param array<int, array<string, mixed>> $commercialOwners
     */
    public static function clientCreatePage(array $commercialOwners): string
    {
        $header = '<div class="crm-hero" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-radius: 18px; padding: 32px 36px; color: #ffffff; margin-bottom: 24px; border: 1px solid rgba(255, 255, 255, 0.08);">'
            . '<span style="display: inline-flex; align-items: center; gap: 6px; background: rgba(37, 99, 235, 0.2); color: #60a5fa; border: 1px solid rgba(96, 165, 250, 0.3); padding: 4px 14px; border-radius: 20px; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px;">'
            . 'CRM • Nouveau Client'
            . '</span>'
            . '<h1 style="font-size: 2rem; font-weight: 800; margin: 8px 0 4px 0; color: #ffffff;">Créer une Fiche Client / Prospect</h1>'
            . '<p style="color: #94a3b8; font-size: 0.95rem; margin: 0;">Renseignez les coordonnées pour démarrer le suivi commercial et l\'affectation aux expéditions.</p>'
            . '</div>';

        $ownerOpts = [['value' => '', 'label' => '-- Aucun commercial --']];
        foreach ($commercialOwners as $o) {
            $ownerOpts[] = ['value' => (string) $o['id'], 'label' => $o['full_name']];
        }

        $form = \App\Helpers\Csrf::input()
            . '<div class="rh-form-grid-3">'
            . Form::input('name', ['label' => 'Nom complet / Raison sociale', 'required' => true, 'placeholder' => 'ex: Kouassi Jean'])
            . Form::input('phone', ['label' => 'Téléphone', 'placeholder' => '+225 07...'])
            . Form::input('email', ['label' => 'Adresse email', 'type' => 'email', 'placeholder' => 'client@domaine.com'])
            . Form::input('address', ['label' => 'Adresse / Ville', 'placeholder' => 'Abidjan Cocody'])
            . Form::select('type', [
                ['value' => 'standard', 'label' => 'Particulier / Standard'],
                ['value' => 'corporate', 'label' => 'Entreprise / Corporate'],
            ], 'standard', ['label' => 'Type de client'])
            . Form::select('crm_status', self::crmStatusOptions(), 'prospect', ['label' => 'Statut CRM'])
            . Form::input('secteur_activite', ['label' => 'Secteur d\'activité', 'placeholder' => 'ex: Import Textile, Médical...'])
            . Form::select('commercial_owner_id', $ownerOpts, '', ['label' => 'Commercial en charge'])
            . '</div>'
            . Form::textarea('notes_commerciales', ['label' => 'Notes commerciales & particularités'])
            . '<div style="margin-top:1.5rem; display:flex; gap:12px;">' 
            . Ui::button('Enregistrer la fiche', ['type' => 'submit', 'variant' => 'accent', 'style' => 'background: #2563eb; color: #fff; font-weight: 700; padding: 12px 28px;']) 
            . Ui::button('Annuler', ['href' => 'crm/clients', 'variant' => 'secondary']) 
            . '</div>';

        $formHtml = '<form method="post" action="' . View::url('crm/clients/enregistrer') . '">' . $form . '</form>';

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . Ui::section('Informations de la Fiche Client', $formHtml)
            . '</div>'
            . '</div>';
    }

    /**
     * Fiche client détaillée : infos CRM, historique colis/factures, interactions, opportunités.
     *
     * @param array<string, mixed> $client
     * @param array<int, array<string, mixed>> $colis
     * @param array<int, array<string, mixed>> $factures
     * @param array<int, array<string, mixed>> $interactions
     * @param array<int, array<string, mixed>> $opportunities
     * @param array<int, array<string, mixed>> $commercialOwners
     */
    public static function clientDetailPage(
        array $client,
        array $colis,
        array $factures,
        array $interactions,
        array $opportunities,
        array $commercialOwners
    ): string {
        $header = '<div class="crm-hero" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-radius: 18px; padding: 32px 36px; color: #ffffff; margin-bottom: 24px; border: 1px solid rgba(255, 255, 255, 0.08); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;">'
            . '<div>'
            . '<span style="display: inline-flex; align-items: center; gap: 6px; background: rgba(37, 99, 235, 0.2); color: #60a5fa; border: 1px solid rgba(96, 165, 250, 0.3); padding: 4px 14px; border-radius: 20px; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px;">'
            . 'Fiche Client CRM'
            . '</span>'
            . '<h1 style="font-size: 2rem; font-weight: 800; margin: 8px 0 4px 0; color: #ffffff;">' . View::e((string)$client['name']) . '</h1>'
            . '<p style="color: #94a3b8; font-size: 0.95rem; margin: 0;">📞 ' . View::e((string) ($client['phone'] ?? 'Non renseigné')) . ' · ✉️ ' . View::e((string) ($client['email'] ?? 'Non renseigné')) . '</p>'
            . '</div>'
            . Ui::badge(ucfirst((string) $client['crm_status']), self::crmStatusTone((string) $client['crm_status']))
            . '</div>';

        $ownerOpts = [['value' => '', 'label' => '-- Aucun --']];
        foreach ($commercialOwners as $o) {
            $ownerOpts[] = ['value' => (string) $o['id'], 'label' => $o['full_name']];
        }

        $crmForm = \App\Helpers\Csrf::input()
            . '<div class="rh-form-grid-3">'
            . Form::select('crm_status', self::crmStatusOptions(), (string) $client['crm_status'], ['label' => 'Statut CRM'])
            . Form::input('secteur_activite', ['label' => 'Secteur d\'activité', 'value' => (string) ($client['secteur_activite'] ?? '')])
            . Form::select('commercial_owner_id', $ownerOpts, (string) ($client['commercial_owner_id'] ?? ''), ['label' => 'Commercial en charge'])
            . '</div>'
            . Form::textarea('notes_commerciales', ['label' => 'Notes commerciales', 'value' => (string) ($client['notes_commerciales'] ?? '')])
            . '<div style="margin-top:1rem;">' . Ui::button('Enregistrer les modifications', ['type' => 'submit', 'variant' => 'accent', 'style' => 'background: #2563eb; color: #fff; font-weight: 700;']) . '</div>';
        $crmFormHtml = '<form method="post" action="' . View::url('crm/clients/' . $client['id'] . '/modifier') . '">' . $crmForm . '</form>';

        $colisHtml = self::clientColisTable($colis);
        $facturesHtml = self::clientFacturesTable($factures);
        $interactionsHtml = self::interactionForm((int) $client['id']) . self::interactionsTable($interactions);
        $opportunitiesHtml = self::opportunityForm((int) $client['id']) . self::opportunitiesTable($opportunities, (int) $client['id']);

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . '<div style="margin-bottom: 1.5rem;">' . Ui::section('Informations CRM', $crmFormHtml) . '</div>'
            . '<div style="margin-bottom: 1.5rem;">' . Ui::section('Historique Colis (' . count($colis) . ')', $colisHtml) . '</div>'
            . '<div style="margin-bottom: 1.5rem;">' . Ui::section('Factures & Règlement (' . count($factures) . ')', $facturesHtml) . '</div>'
            . '<div style="margin-bottom: 1.5rem;">' . Ui::section('Interactions & Relances Call Center (' . count($interactions) . ')', $interactionsHtml) . '</div>'
            . Ui::section('Pipeline Opportunités (' . count($opportunities) . ')', $opportunitiesHtml)
            . '</div>'
            . '</div>';
    }

    /** @param array<int, array<string, mixed>> $colis */
    private static function clientColisTable(array $colis): string
    {
        if (empty($colis)) {
            return Ui::emptyState('Aucun colis', 'Ce client n\'a expédié ni reçu aucun colis.');
        }

        $rows = '';
        foreach ($colis as $c) {
            $rows .= '<tr>'
                . '<td><a href="' . View::url('colisage/parcels/' . $c['id']) . '" style="text-decoration:none;"><strong style="color:#2563eb;">' . View::e((string) $c['numero_tracking']) . '</strong></a></td>'
                . '<td>' . Ui::badge((string) $c['statut'], 'neutral') . '</td>'
                . '<td style="text-align:center;">' . (int) $c['nombre_colis'] . '</td>'
                . '<td style="text-align:right;"><strong>' . number_format((float) $c['montant_total'], 0, ',', ' ') . ' ' . View::e((string) $c['devise']) . '</strong></td>'
                . '<td>' . View::e(date('d/m/Y', strtotime((string) $c['created_at']))) . '</td>'
                . '</tr>';
        }

        return '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>N° Tracking</th><th>Statut</th><th>Nb Colis</th><th>Montant</th><th>Date</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }

    /** @param array<int, array<string, mixed>> $factures */
    private static function clientFacturesTable(array $factures): string
    {
        if (empty($factures)) {
            return Ui::emptyState('Aucune facture', 'Ce client n\'a aucune facture enregistrée.');
        }

        $rows = '';
        foreach ($factures as $f) {
            $rows .= '<tr>'
                . '<td><a href="' . View::url('finance/factures/' . $f['id']) . '" style="text-decoration:none;"><strong style="color:#2563eb;">' . View::e((string) $f['numero_facture']) . '</strong></a></td>'
                . '<td>' . Ui::badge((string) $f['statut'], $f['statut'] === 'payee' ? 'success' : 'warning') . '</td>'
                . '<td style="text-align:right;"><strong>' . number_format((float) $f['montant_total'], 0, ',', ' ') . ' ' . View::e((string) $f['devise']) . '</strong></td>'
                . '<td style="text-align:right;">' . number_format((float) $f['montant_restant'], 0, ',', ' ') . ' ' . View::e((string) $f['devise']) . '</td>'
                . '<td>' . View::e(date('d/m/Y', strtotime((string) $f['date_emission']))) . '</td>'
                . '</tr>';
        }

        return '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>N° Facture</th><th>Statut</th><th>Montant Total</th><th>Restant Dû</th><th>Date</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }

    private static function interactionForm(int $clientId): string
    {
        $fields = \App\Helpers\Csrf::input()
            . '<div class="rh-form-grid-3">'
            . Form::select('channel', [
                ['value' => 'appel', 'label' => 'Appel téléphonique'],
                ['value' => 'email', 'label' => 'Email commercial'],
                ['value' => 'visite', 'label' => 'Visite agence / rendez-vous'],
                ['value' => 'whatsapp', 'label' => 'Message WhatsApp'],
                ['value' => 'autre', 'label' => 'Autre canal'],
            ], 'appel', ['label' => 'Canal'])
            . Form::input('subject', ['label' => 'Objet de l\'échange', 'required' => true, 'placeholder' => 'ex: Demande de tarif groupage'])
            . Form::input('next_action_date', ['label' => 'Prochaine relance (date)', 'type' => 'date'])
            . '</div>'
            . Form::textarea('notes', ['label' => 'Compte-rendu & notes'])
            . '<div style="margin-top:1rem;">' . Ui::button('+ Consigner l\'interaction', ['type' => 'submit', 'variant' => 'secondary', 'style' => 'font-weight:700;']) . '</div>';

        return '<form method="post" action="' . View::url('crm/clients/' . $clientId . '/interactions') . '" style="margin-bottom:1.5rem; padding-bottom:1.5rem; border-bottom:1px solid rgba(0,0,0,0.08);">' . $fields . '</form>';
    }

    /** @param array<int, array<string, mixed>> $interactions */
    private static function interactionsTable(array $interactions): string
    {
        if (empty($interactions)) {
            return Ui::emptyState('Aucune interaction', 'Aucun appel, email ou visite enregistré pour ce client.');
        }

        $rows = '';
        foreach ($interactions as $i) {
            $rows .= '<tr>'
                . '<td>' . View::e(date('d/m/Y H:i', strtotime((string) $i['interaction_at']))) . '</td>'
                . '<td>' . Ui::badge(ucfirst((string) $i['channel']), 'neutral') . '</td>'
                . '<td><strong>' . View::e((string) $i['subject']) . '</strong>' . (!empty($i['notes']) ? '<br><small style="color:#64748b;">' . View::e((string) $i['notes']) . '</small>' : '') . '</td>'
                . '<td>' . View::e((string) ($i['user_name'] ?? '—')) . '</td>'
                . '<td>' . ($i['next_action_date'] ? View::e(date('d/m/Y', strtotime((string) $i['next_action_date']))) : '—') . '</td>'
                . '</tr>';
        }

        return '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>Date & Heure</th><th>Canal</th><th>Objet & Compte-rendu</th><th>Auteur</th><th>Prochaine relance</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }

    private static function opportunityForm(int $clientId): string
    {
        $fields = \App\Helpers\Csrf::input()
            . '<div class="rh-form-grid-3">'
            . Form::input('title', ['label' => 'Titre de l\'opportunité', 'required' => true, 'placeholder' => 'ex: Contrat groupage maritime 20 conteneurs'])
            . Form::input('estimated_amount', ['label' => 'Montant estimé', 'type' => 'number', 'step' => '0.01', 'placeholder' => '5 000 000'])
            . Form::select('currency', [
                ['value' => 'XOF', 'label' => 'XOF (Franc CFA)'],
                ['value' => 'EUR', 'label' => 'EUR (Euros)'],
                ['value' => 'USD', 'label' => 'USD (Dollars)'],
            ], 'XOF', ['label' => 'Devise'])
            . Form::select('stage', self::opportunityStageOptions(), 'qualification', ['label' => 'Étape'])
            . Form::input('expected_close_date', ['label' => 'Clôture prévue', 'type' => 'date'])
            . '</div>'
            . '<div style="margin-top:1rem;">' . Ui::button('+ Créer l\'opportunité', ['type' => 'submit', 'variant' => 'secondary', 'style' => 'font-weight:700;']) . '</div>';

        return '<form method="post" action="' . View::url('crm/clients/' . $clientId . '/opportunites') . '" style="margin-bottom:1.5rem; padding-bottom:1.5rem; border-bottom:1px solid rgba(0,0,0,0.08);">' . $fields . '</form>';
    }

    /** @param array<int, array<string, mixed>> $opportunities */
    private static function opportunitiesTable(array $opportunities, int $clientId): string
    {
        if (empty($opportunities)) {
            return Ui::emptyState('Aucune opportunité', 'Aucun dossier commercial en cours pour ce client.');
        }

        $rows = '';
        foreach ($opportunities as $o) {
            $stageSelect = '<form method="post" action="' . View::url('crm/opportunites/' . $o['id'] . '/etape') . '" style="display:flex; gap:0.4rem; align-items:center;">'
                . \App\Helpers\Csrf::input()
                . '<input type="hidden" name="client_id" value="' . (int) $clientId . '">'
                . Form::rawSelect('stage', self::opportunityStageOptions(), (string) $o['stage'])
                . Ui::button('Mettre à jour', ['type' => 'submit', 'variant' => 'secondary', 'class' => 'finea-button-sm'])
                . '</form>';

            $montant = $o['estimated_amount'] !== null ? number_format((float) $o['estimated_amount'], 0, ',', ' ') . ' ' . View::e((string) $o['currency']) : '—';

            $rows .= '<tr>'
                . '<td><strong>' . View::e((string) $o['title']) . '</strong></td>'
                . '<td style="text-align:right;"><strong>' . $montant . '</strong></td>'
                . '<td style="text-align:center;">' . Ui::badge((int) $o['probability'] . '%', self::opportunityStageTone((string) $o['stage'])) . '</td>'
                . '<td>' . ($o['expected_close_date'] ? View::e(date('d/m/Y', strtotime((string) $o['expected_close_date']))) : '—') . '</td>'
                . '<td>' . $stageSelect . '</td>'
                . '</tr>';
        }

        return '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>Titre</th><th>Montant estimé</th><th>Probabilité</th><th>Clôture prévue</th><th>Étape commerciale</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }
}
