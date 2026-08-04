<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

final class PilotageDg
{
    /**
     * Vue Exécutive Globale : KPIs cross-module + répartition du CA par agence.
     *
     * @param array<string, mixed> $module
     */
    public static function dashboardPage(array $module): string
    {
        $header = Ui::pageHeader(
            (string) $module['label'],
            'Vue exécutive transverse : activité, finance, personnel et alertes — mis à jour en temps réel.',
            ['eyebrow' => 'Pilotage DG', 'class' => 'rh-hero-white']
        );

        $kpis = Dashboard::kpis((array) ($module['kpis'] ?? []));

        $quickLinks = '<div class="rh-form-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">'
            . Ui::button('Supervision du Personnel', ['href' => 'pilotage-dg/personnel', 'variant' => 'secondary'])
            . Ui::button('Centre de Validation', ['href' => 'pilotage-dg/validations', 'variant' => 'secondary'])
            . Ui::button('Anomalies & Fraude', ['href' => 'pilotage-dg/anomalies', 'variant' => 'secondary'])
            . Ui::button('Journal d\'Audit', ['href' => 'pilotage-dg/audit', 'variant' => 'secondary'])
            . '</div>';

        $agenceStats = (array) ($module['agenceStats'] ?? []);
        $agenceTable = self::agenceStatsTable($agenceStats);

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . $kpis
            . '<div style="margin-top: 1.5rem;">' . Ui::section('Accès rapide', $quickLinks) . '</div>'
            . '<div style="margin-top: 1.5rem;">' . Ui::section('Chiffre d\'affaires par agence (mois en cours)', $agenceTable) . '</div>'
            . '</div>'
            . '</div>';
    }

    /** @param array<int, array<string, mixed>> $stats */
    private static function agenceStatsTable(array $stats): string
    {
        if (empty($stats)) {
            return Ui::emptyState('Aucune donnée', 'Aucune agence active ou aucune facture ce mois-ci.');
        }

        $rows = '';
        foreach ($stats as $row) {
            $caTotal = (float) ($row['ca_total'] ?? 0);
            $impaye = (float) ($row['impaye'] ?? 0);
            $tauxImpaye = $caTotal > 0 ? round(($impaye / $caTotal) * 100, 1) : 0.0;
            $tone = $tauxImpaye >= 30 ? 'warning' : 'success';

            $rows .= '<tr>'
                . '<td><strong>' . View::e((string) $row['agence_name']) . '</strong></td>'
                . '<td style="text-align:center;">' . (int) $row['nb_factures'] . '</td>'
                . '<td style="text-align:right;">' . number_format($caTotal, 0, ',', ' ') . ' XOF</td>'
                . '<td style="text-align:right;">' . number_format($impaye, 0, ',', ' ') . ' XOF</td>'
                . '<td style="text-align:center;">' . Ui::badge($tauxImpaye . '%', $tone) . '</td>'
                . '</tr>';
        }

        return '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>Agence</th><th>Nb Factures</th><th>CA du mois</th><th>Impayé</th><th>Taux d\'impayé</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }

    /**
     * Supervision du Personnel : présence, évaluations, objectifs, discipline par employé actif.
     *
     * @param array<int, array<string, mixed>> $employees
     * @param array<int, array<string, string>> $alerts
     */
    public static function personnelPage(array $employees, array $alerts): string
    {
        $header = Ui::pageHeader(
            'Supervision du Personnel',
            'Assiduité, performance, objectifs et discipline — pour savoir qui fait quoi et comment le travail est effectué.',
            ['eyebrow' => 'Pilotage DG', 'class' => 'rh-hero-white']
        );

        $alertsHtml = self::personnelAlerts($alerts);
        $tableHtml = self::personnelTable($employees);

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . '<div style="margin-bottom: 1.5rem;">' . Ui::section('Alertes personnel (' . count($alerts) . ')', $alertsHtml) . '</div>'
            . Ui::section('Effectif actif (' . count($employees) . ')', $tableHtml)
            . '</div>'
            . '</div>';
    }

    /** @param array<int, array<string, string>> $alerts */
    private static function personnelAlerts(array $alerts): string
    {
        if (empty($alerts)) {
            return Ui::emptyState('Aucune alerte', 'Aucun signal d\'absentéisme, d\'objectifs non atteints ou de mesure disciplinaire répétée.');
        }

        $rows = '';
        foreach ($alerts as $alert) {
            $tone = match ($alert['type']) {
                'Absentéisme' => 'warning',
                'Discipline' => 'danger',
                default => 'neutral',
            };
            $rows .= '<tr>'
                . '<td>' . Ui::badge($alert['type'], $tone) . '</td>'
                . '<td><strong>' . View::e($alert['employee']) . '</strong></td>'
                . '<td>' . View::e($alert['detail']) . '</td>'
                . '</tr>';
        }

        return '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>Type</th><th>Employé</th><th>Détail</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }

    /** @param array<int, array<string, mixed>> $employees */
    private static function personnelTable(array $employees): string
    {
        if (empty($employees)) {
            return Ui::emptyState('Aucun employé actif', 'Aucun employé actif trouvé dans le module RH.');
        }

        $rows = '';
        foreach ($employees as $emp) {
            $taux = $emp['taux_presence'];
            $tauxDisplay = $taux !== null ? $taux . '%' : '—';
            $tauxTone = $taux !== null ? ($taux < 70 ? 'warning' : 'success') : 'neutral';

            $eval = $emp['derniere_evaluation'];
            $evalDisplay = $eval !== null ? number_format((float) $eval, 1, ',', ' ') . '/20' : '—';

            $progression = $emp['progression_objectifs'];
            $progressionDisplay = $progression !== null ? $progression . '%' : '—';

            $nbMesures = (int) $emp['nb_mesures_disciplinaires'];

            $rows .= '<tr>'
                . '<td><strong>' . View::e((string) $emp['full_name']) . '</strong></td>'
                . '<td>' . View::e((string) ($emp['function_name'] ?? '—')) . '</td>'
                . '<td>' . View::e((string) ($emp['service_name'] ?? '—')) . '</td>'
                . '<td>' . View::e((string) ($emp['site_name'] ?? '—')) . '</td>'
                . '<td style="text-align:center;">' . Ui::badge($tauxDisplay, $tauxTone) . '</td>'
                . '<td style="text-align:center;">' . View::e($evalDisplay) . '</td>'
                . '<td style="text-align:center;">' . View::e($progressionDisplay) . '</td>'
                . '<td style="text-align:center;">' . ($nbMesures > 0 ? Ui::badge((string) $nbMesures, 'danger') : '0') . '</td>'
                . '</tr>';
        }

        return '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>Employé</th><th>Fonction</th><th>Service</th><th>Site</th>'
            . '<th>Présence (30j)</th><th>Dernière éval.</th><th>Objectifs</th><th>Mesures disc. (12m)</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }

    /**
     * Centre de Validation : tout ce qui attend une décision du DG, tous modules confondus.
     *
     * @param array<int, array<string, mixed>> $workflows
     * @param array<int, array<string, mixed>> $legalRequests
     * @param array<int, array<string, mixed>> $paymentRequests
     */
    public static function validationsPage(array $workflows, array $legalRequests, array $paymentRequests): string
    {
        $total = count($workflows) + count($legalRequests) + count($paymentRequests);

        $header = Ui::pageHeader(
            'Centre de Validation',
            'Tout ce qui attend votre décision, centralisé en un seul endroit — ' . $total . ' élément(s) en attente.',
            ['eyebrow' => 'Pilotage DG', 'class' => 'rh-hero-white']
        );

        $workflowsHtml = self::workflowsTable($workflows);
        $legalHtml = self::legalRequestsTable($legalRequests);
        $paymentsHtml = self::paymentRequestsTable($paymentRequests);

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . '<div style="margin-bottom: 1.5rem;">' . Ui::section('Workflows RH en attente (' . count($workflows) . ')', $workflowsHtml) . '</div>'
            . '<div style="margin-bottom: 1.5rem;">' . Ui::section('Demandes légales du personnel en attente (' . count($legalRequests) . ')', $legalHtml) . '</div>'
            . Ui::section('Demandes de paiement prestataires en attente (' . count($paymentRequests) . ')', $paymentsHtml)
            . '</div>'
            . '</div>';
    }

    /** @param array<int, array<string, mixed>> $workflows */
    private static function workflowsTable(array $workflows): string
    {
        if (empty($workflows)) {
            return Ui::emptyState('Aucun workflow en attente', 'Tous les workflows RH ont été traités.');
        }

        $rows = '';
        foreach ($workflows as $w) {
            $rows .= '<tr>'
                . '<td>' . View::e((string) $w['process_type']) . '</td>'
                . '<td>' . View::e((string) ($w['employee_name'] ?? '—')) . '</td>'
                . '<td>' . View::e((string) $w['current_step']) . '</td>'
                . '<td>' . View::e(date('d/m/Y H:i', strtotime((string) $w['created_at']))) . '</td>'
                . '<td>' . Ui::button('Traiter', ['href' => 'rh/validations', 'variant' => 'secondary']) . '</td>'
                . '</tr>';
        }

        return '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>Processus</th><th>Employé</th><th>Étape</th><th>Soumis le</th><th>Action</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }

    /** @param array<int, array<string, mixed>> $requests */
    private static function legalRequestsTable(array $requests): string
    {
        if (empty($requests)) {
            return Ui::emptyState('Aucune demande en attente', 'Toutes les demandes légales du personnel ont été traitées.');
        }

        $rows = '';
        foreach ($requests as $r) {
            $rows .= '<tr>'
                . '<td>' . View::e((string) $r['request_type']) . '</td>'
                . '<td>' . View::e((string) ($r['employee_name'] ?? '—')) . '</td>'
                . '<td>' . Ui::badge((string) $r['status'], 'warning') . '</td>'
                . '<td>' . View::e(date('d/m/Y H:i', strtotime((string) $r['submitted_at']))) . '</td>'
                . '<td>' . Ui::button('Traiter', ['href' => 'rh/validations', 'variant' => 'secondary']) . '</td>'
                . '</tr>';
        }

        return '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>Type</th><th>Employé</th><th>Statut</th><th>Soumis le</th><th>Action</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }

    /** @param array<int, array<string, mixed>> $requests */
    private static function paymentRequestsTable(array $requests): string
    {
        if (empty($requests)) {
            return Ui::emptyState('Aucune demande en attente', 'Toutes les demandes de paiement prestataires ont été traitées.');
        }

        $rows = '';
        foreach ($requests as $r) {
            $rows .= '<tr>'
                . '<td>' . View::e((string) ($r['prestataire_name'] ?? '—')) . '</td>'
                . '<td>' . View::e((string) $r['motif']) . '</td>'
                . '<td style="text-align:right;">' . number_format((float) $r['montant'], 0, ',', ' ') . ' ' . View::e((string) $r['devise']) . '</td>'
                . '<td>' . View::e(date('d/m/Y H:i', strtotime((string) $r['date_demande']))) . '</td>'
                . '<td>' . Ui::button('Traiter', ['href' => 'finance/depenses', 'variant' => 'secondary']) . '</td>'
                . '</tr>';
        }

        return '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>Prestataire</th><th>Motif</th><th>Montant</th><th>Demandé le</th><th>Action</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }

    /**
     * Anomalies & anti-fraude : écarts de caisse, agents à modifications répétées, agences à impayés anormaux.
     *
     * @param array<int, array<string, mixed>> $ecartsCaisse
     * @param array<int, array<string, mixed>> $agentsSuspects
     * @param array<int, array<string, mixed>> $agencesImpayes
     */
    public static function anomaliesPage(array $ecartsCaisse, array $agentsSuspects, array $agencesImpayes): string
    {
        $header = Ui::pageHeader(
            'Anomalies & Anti-Fraude',
            'Écarts de caisse, agents à modifications répétées et agences à taux d\'impayés anormal.',
            ['eyebrow' => 'Pilotage DG', 'class' => 'rh-hero-white']
        );

        $ecartsHtml = self::ecartsCaisseTable($ecartsCaisse);
        $agentsHtml = self::agentsSuspectsTable($agentsSuspects);
        $agencesHtml = self::agencesImpayesTable($agencesImpayes);

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . '<div style="margin-bottom: 1.5rem;">' . Ui::section('Écarts de caisse (états journaliers)', $ecartsHtml) . '</div>'
            . '<div style="margin-bottom: 1.5rem;">' . Ui::section('Agents à modifications de factures répétées', $agentsHtml) . '</div>'
            . Ui::section('Agences à taux d\'impayés élevé', $agencesHtml)
            . '</div>'
            . '</div>';
    }

    /** @param array<int, array<string, mixed>> $ecarts */
    private static function ecartsCaisseTable(array $ecarts): string
    {
        if (empty($ecarts)) {
            return Ui::emptyState('Aucun écart', 'Aucun état journalier ne présente d\'écart de caisse non expliqué.');
        }

        $rows = '';
        foreach ($ecarts as $e) {
            $ecart = (float) $e['ecart_caisse'];
            $rows .= '<tr>'
                . '<td>' . View::e((string) $e['agence_name']) . '</td>'
                . '<td>' . View::e((string) ($e['chef_agence_name'] ?? '—')) . '</td>'
                . '<td>' . View::e(date('d/m/Y', strtotime((string) $e['date_jour']))) . '</td>'
                . '<td style="text-align:right;">' . Ui::badge(number_format($ecart, 0, ',', ' ') . ' XOF', $ecart > 0 ? 'success' : 'danger') . '</td>'
                . '<td>' . View::e((string) ($e['explication_ecart'] ?? '— Non expliqué —')) . '</td>'
                . '</tr>';
        }

        return '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>Agence</th><th>Chef d\'agence</th><th>Date</th><th>Écart</th><th>Explication</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }

    /** @param array<int, array<string, mixed>> $agents */
    private static function agentsSuspectsTable(array $agents): string
    {
        if (empty($agents)) {
            return Ui::emptyState('Aucun signal', 'Aucun agent n\'a modifié une facture verrouillée 3 fois ou plus.');
        }

        $rows = '';
        foreach ($agents as $a) {
            $rows .= '<tr>'
                . '<td><strong>' . View::e((string) ($a['user_name'] ?? ('Utilisateur #' . $a['modifie_par']))) . '</strong></td>'
                . '<td style="text-align:center;">' . Ui::badge((string) $a['nb_modifications'], 'warning') . '</td>'
                . '</tr>';
        }

        return '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>Utilisateur</th><th>Nombre de modifications</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }

    /** @param array<int, array<string, mixed>> $agences */
    private static function agencesImpayesTable(array $agences): string
    {
        if (empty($agences)) {
            return Ui::emptyState('Aucune donnée', 'Aucune facture trouvée pour calculer un taux d\'impayé par agence.');
        }

        $rows = '';
        foreach ($agences as $a) {
            $montantTotal = (float) $a['montant_total'];
            $montantImpaye = (float) $a['montant_impaye'];
            $taux = $montantTotal > 0 ? round(($montantImpaye / $montantTotal) * 100, 1) : 0.0;
            $tone = $taux >= 30 ? 'danger' : ($taux >= 15 ? 'warning' : 'success');

            $rows .= '<tr>'
                . '<td><strong>' . View::e((string) $a['agence_name']) . '</strong></td>'
                . '<td style="text-align:center;">' . (int) $a['nb_factures'] . '</td>'
                . '<td style="text-align:right;">' . number_format($montantTotal, 0, ',', ' ') . ' XOF</td>'
                . '<td style="text-align:right;">' . number_format($montantImpaye, 0, ',', ' ') . ' XOF</td>'
                . '<td style="text-align:center;">' . Ui::badge($taux . '%', $tone) . '</td>'
                . '</tr>';
        }

        return '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>Agence</th><th>Nb Factures</th><th>Montant Total</th><th>Montant Impayé</th><th>Taux d\'impayé</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }

    /**
     * Journal d'audit transverse filtrable (lbp_audit_logs).
     *
     * @param array<int, array<string, mixed>> $logs
     * @param array<int, string> $entityTypes
     * @param array{currentPage: int, totalPages: int, itemsPerPage: int, totalItems: int} $pagination
     * @param array<string, string> $filters
     */
    public static function auditPage(array $logs, array $entityTypes, array $pagination, array $filters): string
    {
        $header = Ui::pageHeader(
            'Journal d\'Audit Transverse',
            'Qui a fait quoi, quand, sur quel module — ' . $pagination['totalItems'] . ' entrée(s) au total.',
            ['eyebrow' => 'Pilotage DG', 'class' => 'rh-hero-white']
        );

        $entityOpts = [['value' => '', 'label' => 'Tous les modules']];
        foreach ($entityTypes as $type) {
            $entityOpts[] = ['value' => $type, 'label' => $type];
        }

        $filterForm = '<form method="get" action="' . View::url('pilotage-dg/audit') . '" class="rh-form-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); align-items:end;">'
            . Form::select('entity_type', $entityOpts, $filters['entity_type'], ['label' => 'Module / Entité'])
            . Form::input('start_date', ['label' => 'Du', 'type' => 'date', 'value' => $filters['start_date']])
            . Form::input('end_date', ['label' => 'Au', 'type' => 'date', 'value' => $filters['end_date']])
            . '<div>' . Ui::button('Filtrer', ['type' => 'submit', 'variant' => 'primary']) . '</div>'
            . '</form>';

        $tableHtml = self::auditLogTable($logs);

        $paginationHtml = '';
        if ($pagination['totalPages'] > 1) {
            $baseParams = $filters;
            $paginationHtml = '<div style="margin-top: 1.5rem;">' . Rh::pagination(
                $pagination['currentPage'],
                $pagination['totalPages'],
                static fn(int $page): string => View::url('pilotage-dg/audit?' . http_build_query($baseParams + ['page' => $page]))
            ) . '</div>';
        }

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . '<div style="margin-bottom: 1.5rem;">' . Ui::section('Filtres', $filterForm) . '</div>'
            . Ui::section('Historique des actions', $tableHtml)
            . $paginationHtml
            . '</div>'
            . '</div>';
    }

    /** @param array<int, array<string, mixed>> $logs */
    private static function auditLogTable(array $logs): string
    {
        if (empty($logs)) {
            return Ui::emptyState('Aucune entrée', 'Aucune action ne correspond aux filtres sélectionnés.');
        }

        $rows = '';
        foreach ($logs as $log) {
            $rows .= '<tr>'
                . '<td>' . View::e(date('d/m/Y H:i', strtotime((string) $log['created_at']))) . '</td>'
                . '<td>' . View::e((string) ($log['user_name'] ?? ('Utilisateur #' . $log['user_id']))) . '</td>'
                . '<td>' . Ui::badge((string) $log['action'], 'neutral') . '</td>'
                . '<td>' . View::e((string) $log['entity_type']) . ' #' . (int) $log['entity_id'] . '</td>'
                . '<td>' . View::e((string) ($log['ip_address'] ?? '—')) . '</td>'
                . '</tr>';
        }

        return '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>Date & Heure</th><th>Utilisateur</th><th>Action</th><th>Entité</th><th>IP</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }
}
