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
            ['label' => 'Annuaire Clients', 'href' => 'crm/clients', 'icon' => '<svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>', 'variant' => 'accent'],
            ['label' => 'Recherche Call Center (Rayons)', 'href' => 'call-center/recherche-colis', 'icon' => '<svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>', 'variant' => 'primary'],
            ['label' => 'Suivi des Colis', 'href' => 'colisage/parcels', 'icon' => '<svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>', 'variant' => 'secondary'],
        ], [
            'title' => 'Actions rapides',
            'class' => 'finea-section-card',
        ]);

        $openDirectoryBtn = Ui::button('+ Nouveau Client', ['href' => 'crm/clients/nouveau', 'variant' => 'accent']);
        $introContent = Ui::emptyState(
            'Annuaire Clients & Suivi Commercial',
            'Consultez, recherchez et créez des fiches client/prospect, suivez vos interactions (appels, emails, visites) et votre pipeline d\'opportunités.'
        ) . '<div style="margin-top: 1rem; display:flex; gap:0.75rem;">' . $openDirectoryBtn . Ui::button('Voir l\'annuaire', ['href' => 'crm/clients', 'variant' => 'secondary']) . '</div>';

        $mainSection = Ui::section('Gestion Client', $introContent);

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
     * Annuaire clients : liste/recherche des clients réels (lbp_clients) avec statut CRM.
     *
     * @param array<int, array<string, mixed>> $clients
     * @param array<string, string> $filters
     * @param array{currentPage: int, totalPages: int, itemsPerPage: int, totalItems: int} $pagination
     */
    public static function clientsListPage(array $clients, array $filters, array $pagination): string
    {
        $header = Ui::pageHeader(
            'Annuaire Clients',
            'Clients et prospects enregistrés — ' . $pagination['totalItems'] . ' fiche(s) au total.',
            [
                'eyebrow' => 'CRM',
                'class' => 'rh-hero-white',
                'actions' => [Ui::button('+ Nouveau Client', ['href' => 'crm/clients/nouveau', 'variant' => 'accent'])],
            ]
        );

        $statusOpts = array_merge([['value' => '', 'label' => 'Tous les statuts']], self::crmStatusOptions());

        $filterForm = '<form method="get" action="' . View::url('crm/clients') . '" class="rh-form-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); align-items:end;">'
            . Form::input('q', ['label' => 'Recherche (nom, téléphone, email)', 'value' => $filters['q']])
            . Form::select('crm_status', $statusOpts, $filters['crm_status'], ['label' => 'Statut CRM'])
            . '<div>' . Ui::button('Filtrer', ['type' => 'submit', 'variant' => 'primary']) . '</div>'
            . '</form>';

        $tableHtml = self::clientsTable($clients);

        $paginationHtml = '';
        if ($pagination['totalPages'] > 1) {
            $baseParams = $filters;
            $paginationHtml = '<div style="margin-top: 1.5rem;">' . Rh::pagination(
                $pagination['currentPage'],
                $pagination['totalPages'],
                static fn(int $page): string => View::url('crm/clients?' . http_build_query($baseParams + ['page' => $page]))
            ) . '</div>';
        }

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . '<div style="margin-bottom: 1.5rem;">' . Ui::section('Filtres', $filterForm) . '</div>'
            . Ui::section('Résultats', $tableHtml)
            . $paginationHtml
            . '</div>'
            . '</div>';
    }

    /** @param array<int, array<string, mixed>> $clients */
    private static function clientsTable(array $clients): string
    {
        if (empty($clients)) {
            return Ui::emptyState('Aucun client trouvé', 'Aucun client ne correspond aux critères sélectionnés.');
        }

        $rows = '';
        foreach ($clients as $c) {
            $rows .= '<tr>'
                . '<td><a href="' . View::url('crm/clients/' . $c['id']) . '"><strong>' . View::e((string) $c['name']) . '</strong></a></td>'
                . '<td>' . View::e((string) ($c['phone'] ?? '—')) . '</td>'
                . '<td>' . View::e((string) ($c['email'] ?? '—')) . '</td>'
                . '<td>' . View::e((string) ($c['secteur_activite'] ?? '—')) . '</td>'
                . '<td style="text-align:center;">' . Ui::badge(ucfirst((string) $c['crm_status']), self::crmStatusTone((string) $c['crm_status'])) . '</td>'
                . '<td>' . View::e((string) ($c['commercial_owner_name'] ?? '—')) . '</td>'
                . '<td>' . Ui::button('Voir', ['href' => 'crm/clients/' . $c['id'], 'variant' => 'secondary']) . '</td>'
                . '</tr>';
        }

        return '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>Nom</th><th>Téléphone</th><th>Email</th><th>Secteur</th><th>Statut</th><th>Commercial</th><th>Action</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }

    /**
     * Formulaire de création d'un client/prospect.
     *
     * @param array<int, array<string, mixed>> $commercialOwners
     */
    public static function clientCreatePage(array $commercialOwners): string
    {
        $header = Ui::pageHeader(
            'Nouveau Client / Prospect',
            'Créer une fiche client ou prospect dans l\'annuaire CRM.',
            ['eyebrow' => 'CRM', 'class' => 'rh-hero-white']
        );

        $ownerOpts = [['value' => '', 'label' => '-- Aucun --']];
        foreach ($commercialOwners as $o) {
            $ownerOpts[] = ['value' => (string) $o['id'], 'label' => $o['full_name']];
        }

        $form = \App\Helpers\Csrf::input()
            . '<div class="rh-form-grid-3">'
            . Form::input('name', ['label' => 'Nom complet', 'required' => true])
            . Form::input('phone', ['label' => 'Téléphone'])
            . Form::input('email', ['label' => 'Email', 'type' => 'email'])
            . Form::input('address', ['label' => 'Adresse'])
            . Form::select('type', [
                ['value' => 'standard', 'label' => 'Standard'],
                ['value' => 'corporate', 'label' => 'Entreprise'],
            ], 'standard', ['label' => 'Type de client'])
            . Form::select('crm_status', self::crmStatusOptions(), 'prospect', ['label' => 'Statut CRM'])
            . Form::input('secteur_activite', ['label' => 'Secteur d\'activité'])
            . Form::select('commercial_owner_id', $ownerOpts, '', ['label' => 'Commercial en charge'])
            . '</div>'
            . Form::textarea('notes_commerciales', ['label' => 'Notes commerciales'])
            . '<div style="margin-top:1rem;">' . Ui::button('Créer la fiche', ['type' => 'submit', 'variant' => 'accent']) . '</div>';

        $formHtml = '<form method="post" action="' . View::url('crm/clients/enregistrer') . '">' . $form . '</form>';

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . Ui::section('Informations client', $formHtml)
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
        $header = Ui::pageHeader(
            (string) $client['name'],
            'Fiche client CRM — ' . View::e((string) ($client['phone'] ?? '')) . ' · ' . View::e((string) ($client['email'] ?? '')),
            [
                'eyebrow' => 'CRM',
                'class' => 'rh-hero-white',
                'actions' => [Ui::badge(ucfirst((string) $client['crm_status']), self::crmStatusTone((string) $client['crm_status']))],
            ]
        );

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
            . '<div style="margin-top:1rem;">' . Ui::button('Enregistrer', ['type' => 'submit', 'variant' => 'accent']) . '</div>';
        $crmFormHtml = '<form method="post" action="' . View::url('crm/clients/' . $client['id'] . '/modifier') . '">' . $crmForm . '</form>';

        $colisHtml = self::clientColisTable($colis);
        $facturesHtml = self::clientFacturesTable($factures);
        $interactionsHtml = self::interactionForm((int) $client['id']) . self::interactionsTable($interactions);
        $opportunitiesHtml = self::opportunityForm((int) $client['id']) . self::opportunitiesTable($opportunities, (int) $client['id']);

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . '<div style="margin-bottom: 1.5rem;">' . Ui::section('Informations CRM', $crmFormHtml) . '</div>'
            . '<div style="margin-bottom: 1.5rem;">' . Ui::section('Colis (' . count($colis) . ')', $colisHtml) . '</div>'
            . '<div style="margin-bottom: 1.5rem;">' . Ui::section('Factures (' . count($factures) . ')', $facturesHtml) . '</div>'
            . '<div style="margin-bottom: 1.5rem;">' . Ui::section('Interactions (' . count($interactions) . ')', $interactionsHtml) . '</div>'
            . Ui::section('Opportunités (' . count($opportunities) . ')', $opportunitiesHtml)
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
                . '<td><a href="' . View::url('colisage/parcels/' . $c['id']) . '"><strong>' . View::e((string) $c['numero_tracking']) . '</strong></a></td>'
                . '<td>' . Ui::badge((string) $c['statut'], 'neutral') . '</td>'
                . '<td style="text-align:center;">' . (int) $c['nombre_colis'] . '</td>'
                . '<td style="text-align:right;">' . number_format((float) $c['montant_total'], 0, ',', ' ') . ' ' . View::e((string) $c['devise']) . '</td>'
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
                . '<td><a href="' . View::url('finance/factures/' . $f['id']) . '"><strong>' . View::e((string) $f['numero_facture']) . '</strong></a></td>'
                . '<td>' . Ui::badge((string) $f['statut'], $f['statut'] === 'payee' ? 'success' : 'warning') . '</td>'
                . '<td style="text-align:right;">' . number_format((float) $f['montant_total'], 0, ',', ' ') . ' ' . View::e((string) $f['devise']) . '</td>'
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
                ['value' => 'appel', 'label' => 'Appel'],
                ['value' => 'email', 'label' => 'Email'],
                ['value' => 'visite', 'label' => 'Visite'],
                ['value' => 'whatsapp', 'label' => 'WhatsApp'],
                ['value' => 'autre', 'label' => 'Autre'],
            ], 'appel', ['label' => 'Canal'])
            . Form::input('subject', ['label' => 'Objet', 'required' => true])
            . Form::input('next_action_date', ['label' => 'Prochaine action (date)', 'type' => 'date'])
            . '</div>'
            . Form::textarea('notes', ['label' => 'Notes'])
            . '<div style="margin-top:1rem;">' . Ui::button('Ajouter l\'interaction', ['type' => 'submit', 'variant' => 'secondary']) . '</div>';

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
                . '<td><strong>' . View::e((string) $i['subject']) . '</strong>' . (!empty($i['notes']) ? '<br><small>' . View::e((string) $i['notes']) . '</small>' : '') . '</td>'
                . '<td>' . View::e((string) ($i['user_name'] ?? '—')) . '</td>'
                . '<td>' . ($i['next_action_date'] ? View::e(date('d/m/Y', strtotime((string) $i['next_action_date']))) : '—') . '</td>'
                . '</tr>';
        }

        return '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>Date</th><th>Canal</th><th>Objet</th><th>Par</th><th>Prochaine action</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }

    private static function opportunityForm(int $clientId): string
    {
        $fields = \App\Helpers\Csrf::input()
            . '<div class="rh-form-grid-3">'
            . Form::input('title', ['label' => 'Titre de l\'opportunité', 'required' => true])
            . Form::input('estimated_amount', ['label' => 'Montant estimé', 'type' => 'number', 'step' => '0.01'])
            . Form::select('currency', [
                ['value' => 'XOF', 'label' => 'XOF'],
                ['value' => 'EUR', 'label' => 'EUR'],
                ['value' => 'USD', 'label' => 'USD'],
            ], 'XOF', ['label' => 'Devise'])
            . Form::select('stage', self::opportunityStageOptions(), 'qualification', ['label' => 'Étape'])
            . Form::input('expected_close_date', ['label' => 'Clôture prévue', 'type' => 'date'])
            . '</div>'
            . '<div style="margin-top:1rem;">' . Ui::button('Créer l\'opportunité', ['type' => 'submit', 'variant' => 'secondary']) . '</div>';

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
                . '<td style="text-align:right;">' . $montant . '</td>'
                . '<td style="text-align:center;">' . Ui::badge((int) $o['probability'] . '%', self::opportunityStageTone((string) $o['stage'])) . '</td>'
                . '<td>' . ($o['expected_close_date'] ? View::e(date('d/m/Y', strtotime((string) $o['expected_close_date']))) : '—') . '</td>'
                . '<td>' . $stageSelect . '</td>'
                . '</tr>';
        }

        return '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>Titre</th><th>Montant estimé</th><th>Probabilité</th><th>Clôture prévue</th><th>Étape</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }
}
