<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\Csrf;
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

        $quickLinks = '<div class="rh-form-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">'
            . Ui::button('Supervision du Personnel', ['href' => View::url('pilotage-dg/personnel'), 'variant' => 'secondary'])
            . Ui::button('Centre de Validation', ['href' => View::url('pilotage-dg/validations'), 'variant' => 'secondary'])
            . Ui::button('Anomalies & Fraude', ['href' => View::url('pilotage-dg/anomalies'), 'variant' => 'secondary'])
            . Ui::button('Journal d\'Audit', ['href' => View::url('pilotage-dg/audit'), 'variant' => 'secondary'])
            . Ui::button('Charte Informatique (Protection DG)', ['href' => View::url('rh/charte-informatique'), 'variant' => 'accent'])
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
    public static function personnelPage(array $employees, array $alerts, array $topHonnetes = []): string
    {
        $header = Ui::pageHeader(
            'Supervision du Personnel & Méritocratie',
            'Score d\'intégrité, assiduité, performance et tableau d\'honneur des employés modèles.',
            ['eyebrow' => 'Pilotage DG', 'class' => 'rh-hero-white']
        );

        $honnetesHtml = self::topHonnetesTable($topHonnetes);
        $alertsHtml = self::personnelAlerts($alerts);
        $tableHtml = self::personnelTable($employees);

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . '<div style="margin-bottom: 1.5rem;">' . Ui::section('Tableau d\'Honneur DG - Employés Modèles & Primes Recommandées', $honnetesHtml) . '</div>'
            . '<div style="margin-bottom: 1.5rem;">' . Ui::section('Alertes personnel (' . count($alerts) . ')', $alertsHtml) . '</div>'
            . Ui::section('Effectif actif & Score d\'Intégrité (' . count($employees) . ')', $tableHtml)
            . '</div>'
            . '</div>';
    }

    /** @param array<int, array<string, mixed>> $topHonnetes */
    private static function topHonnetesTable(array $topHonnetes): string
    {
        if (empty($topHonnetes)) {
            return Ui::emptyState('Aucun résultat', 'Aucun employé modélisé pour le moment.');
        }

        $rows = '';
        foreach ($topHonnetes as $idx => $emp) {
            $rank = $idx + 1;
            $medaille = match ($rank) {
                1 => 'Rang 1',
                2 => 'Rang 2',
                3 => 'Rang 3',
                default => '#' . $rank,
            };
            $score = (int) ($emp['score_integrite'] ?? 85);
            $rows .= '<tr>'
                . '<td><strong style="color:#2563eb;">' . $medaille . '</strong></td>'
                . '<td><strong>' . View::e((string) $emp['full_name']) . '</strong><br><small style="color:#64748b;">' . View::e((string)($emp['function_name'] ?? 'Agent')) . '</small></td>'
                . '<td>' . View::e((string) ($emp['site_name'] ?? 'Agence')) . '</td>'
                . '<td style="text-align:center;"><strong style="color:#10b981; font-size:1.1rem;">' . $score . ' / 100 PTS</strong></td>'
                . '<td style="text-align:right;">' . Ui::badge('Récompense / Prime Recommandée', 'success') . '</td>'
                . '</tr>';
        }

        return '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>Classement</th><th>Employé Modèle</th><th>Agence</th><th style="text-align:center;">Score d\'Intégrité</th><th style="text-align:right;">Décision DG</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
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
                'Discipline', 'Écart de Caisse', 'Colis Suspects', 'Rapprochement Caisse' => 'danger',
                'Factures Modifiées', 'Rapport Caisse Incohérent' => 'warning',
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

            $score = (int) ($emp['score_integrite'] ?? 85);
            $scoreTone = $score >= 80 ? 'success' : ($score >= 60 ? 'warning' : 'danger');

            $gpsStatut = $emp['gps_statut'] ?? 'inconnu';
            $gpsDist = $emp['gps_distance_km'] ?? null;
            $gpsBadge = match ($gpsStatut) {
                'sur_site' => Ui::badge('SUR SITE', 'success'),
                'proximite' => Ui::badge('PROXIMITÉ (' . round((float)$gpsDist, 1) . ' km)', 'warning'),
                'hors_site' => Ui::badge('HORS SITE (' . round((float)$gpsDist, 1) . ' km)', 'danger'),
                default => Ui::badge('Non géolocalisé', 'neutral'),
            };

            $rows .= '<tr>'
                . '<td><strong>' . View::e((string) $emp['full_name']) . '</strong></td>'
                . '<td>' . View::e((string) ($emp['site_name'] ?? '—')) . '</td>'
                . '<td>' . View::e((string) ($emp['function_name'] ?? '—')) . '</td>'
                . '<td style="text-align:center;">' . $gpsBadge . '</td>'
                . '<td style="text-align:center;">' . Ui::badge($score . ' / 100', $scoreTone) . '</td>'
                . '<td style="text-align:center;">' . Ui::badge($tauxDisplay, $tauxTone) . '</td>'
                . '<td style="text-align:center;">' . View::e($evalDisplay) . '</td>'
                . '<td style="text-align:center;">' . (int) $emp['nb_mesures_disciplinaires'] . '</td>'
                . '</tr>';
        }

        return '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>Employé</th><th>Agence</th><th>Fonction</th><th style="text-align:center;">Localisation GPS Agence</th><th style="text-align:center;">Score Intégrité</th><th style="text-align:center;">Présence 30j</th><th style="text-align:center;">Éval. globale</th><th style="text-align:center;">Discipline</th>'
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
                . '<td>' . self::decisionForm('pilotage-dg/validations/workflow', (int) $w['id']) . '</td>'
                . '</tr>';
        }

        return '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>Processus</th><th>Employé</th><th>Étape</th><th>Soumis le</th><th>Action</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }

    /**
     * Boutons Approuver / Rejeter postant vers le Centre de Validation.
     * La decision est ensuite deleguee a la logique metier du module concerne.
     */
    private static function decisionForm(string $action, int $recordId, bool $avecCommentaire = false): string
    {
        $champCommentaire = $avecCommentaire
            ? '<input type="text" name="comment" placeholder="Motif (facultatif)" style="padding:5px 8px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.78rem; width:150px;">'
            : '';

        $bouton = static fn(string $decision, string $libelle, string $couleur): string =>
            '<button type="submit" name="decision" value="' . $decision . '" style="padding:5px 11px; background:' . $couleur . '; color:#fff; border:none; border-radius:6px; font-weight:700; font-size:0.78rem; cursor:pointer;">' . $libelle . '</button>';

        return '<form method="post" action="' . View::url($action . '/' . $recordId) . '" class="js-protect-form" style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">'
            . Form::hidden('_csrf_token', Csrf::token())
            . $champCommentaire
            . $bouton('approve', 'Approuver', '#059669')
            . $bouton('reject', 'Rejeter', '#dc2626')
            . '</form>';
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
                . '<td>' . self::decisionForm('pilotage-dg/validations/demande', (int) $r['id'], true) . '</td>'
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
                . '<td>'
                . '<form method="post" action="' . View::url('finance/depenses/' . (int) $r['id'] . '/valider') . '" class="js-protect-form" style="display:flex; gap:6px; align-items:center;">'
                . Form::hidden('_csrf_token', Csrf::token())
                . '<button type="submit" name="decision" value="approuver" style="padding:5px 11px; background:#059669; color:#fff; border:none; border-radius:6px; font-weight:700; font-size:0.78rem; cursor:pointer;">Approuver</button>'
                . '<button type="submit" name="decision" value="rejeter" style="padding:5px 11px; background:#dc2626; color:#fff; border:none; border-radius:6px; font-weight:700; font-size:0.78rem; cursor:pointer;">Rejeter</button>'
                . '</form>'
                . '<small style="color:#94a3b8; font-size:0.7rem;">Traité par le module Finance (double contrôle et écritures comptables)</small>'
                . '</td>'
                . '</tr>';
        }

        return '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>Prestataire</th><th>Motif</th><th>Montant</th><th>Demandé le</th><th>Action</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }

    /**
     * Anomalies & anti-fraude : signalements classés par degré de gravité, écarts de caisse,
     * agents à modifications répétées, agences à impayés anormaux.
     *
     * @param array<int, array<string, mixed>> $ecartsCaisse
     * @param array<int, array<string, mixed>> $agentsSuspects
     * @param array<int, array<string, mixed>> $agencesImpayes
     * @param array<int, array<string, mixed>> $signalements
     * @param array<int, array<string, mixed>> $colisSuspects
     * @param array<int, array<string, mixed>> $rapprochementIndependant
     */
    public static function anomaliesPage(array $ecartsCaisse, array $agentsSuspects, array $agencesImpayes, array $signalements = [], array $colisSuspects = [], array $rapprochementIndependant = [], int $aTraiter = 0, int $traites = 0): string
    {
        $nbGraves = count(array_filter($signalements, fn($s) => (int) $s['degre'] >= 3 && !($s['clos'] ?? false)));

        $header = Ui::pageHeader(
            'Anomalies & Anti-Fraude',
            $aTraiter . ' signalement(s) à traiter dont ' . $nbGraves . ' grave(s) ou très grave(s)'
                . ($traites > 0 ? ', ' . $traites . ' déjà traité(s) ou classé(s) sans suite' : '') . '.',
            ['eyebrow' => 'Pilotage DG', 'class' => 'rh-hero-white']
        );

        $signalementsHtml = self::signalementsTable($signalements);
        $rapprochementHtml = self::rapprochementIndependantTable($rapprochementIndependant);
        $ecartsHtml = self::ecartsCaisseTable($ecartsCaisse);
        $agentsHtml = self::agentsSuspectsTable($agentsSuspects);
        $agencesHtml = self::agencesImpayesTable($agencesImpayes);
        $colisHtml = self::colisSuspectsTable($colisSuspects);

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . '<div style="margin-bottom: 1.5rem;">' . Ui::section('Signalements par degré de gravité (' . count($signalements) . ')', $signalementsHtml) . '</div>'
            . '<div style="margin-bottom: 1.5rem;">' . Ui::section('Détail — Rapprochement de caisse indépendant (90 derniers jours)', $rapprochementHtml) . '</div>'
            . '<div style="margin-bottom: 1.5rem;">' . Ui::section('Détail — Écarts de caisse (états journaliers)', $ecartsHtml) . '</div>'
            . '<div style="margin-bottom: 1.5rem;">' . Ui::section('Détail — Agents à modifications de factures répétées', $agentsHtml) . '</div>'
            . '<div style="margin-bottom: 1.5rem;">' . Ui::section('Détail — Agents à colis systématiquement sous-déclarés', $colisHtml) . '</div>'
            . Ui::section('Détail — Agences à taux d\'impayés élevé', $agencesHtml)
            . '</div>'
            . '</div>';
    }

    /** @param array<int, array<string, mixed>> $rows */
    private static function rapprochementIndependantTable(array $rows): string
    {
        if (empty($rows)) {
            return Ui::emptyState('Aucune donnée', 'Aucun état journalier avec comptage physique sur les 90 derniers jours.');
        }

        $body = '';
        foreach ($rows as $r) {
            $declare = (float) $r['total_encaisse_xof'];
            $physique = (float) $r['solde_physique_declare'];
            $calcule = (float) $r['montant_espece_calcule'];
            $ecartPhysique = $physique - $calcule;
            $tone = abs($ecartPhysique) >= 50000 ? 'danger' : (abs($ecartPhysique) >= 10000 ? 'warning' : 'success');

            $body .= '<tr>'
                . '<td>' . View::e((string) ($r['agence_name'] ?? 'Agence')) . '</td>'
                . '<td>' . View::e((string) ($r['chef_agence_name'] ?? '—')) . '</td>'
                . '<td>' . View::e(date('d/m/Y', strtotime((string) $r['date_jour']))) . '</td>'
                . '<td style="text-align:right;">' . number_format($declare, 0, ',', ' ') . '</td>'
                . '<td style="text-align:right;">' . number_format($calcule, 0, ',', ' ') . '</td>'
                . '<td style="text-align:right;">' . number_format($physique, 0, ',', ' ') . '</td>'
                . '<td style="text-align:center;">' . Ui::badge(number_format($ecartPhysique, 0, ',', ' ') . ' XOF', $tone) . '</td>'
                . '</tr>';
        }

        return '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>Agence</th><th>Chef d\'agence</th><th>Date</th>'
            . '<th style="text-align:right;">Déclaré (rapport)</th><th style="text-align:right;">Calculé (registre)</th><th style="text-align:right;">Physique (comptage)</th>'
            . '<th style="text-align:center;">Écart Physique − Registre</th>'
            . '</tr></thead><tbody>' . $body . '</tbody></table></div>';
    }

    /** @param array<int, array<string, mixed>> $colisSuspects */
    private static function colisSuspectsTable(array $colisSuspects): string
    {
        if (empty($colisSuspects)) {
            return Ui::emptyState('Aucun signal', 'Aucun agent ne présente un prix moyen au kg significativement inférieur à la moyenne (180 derniers jours, min. 5 colis).');
        }

        $rows = '';
        foreach ($colisSuspects as $cs) {
            $ratio = $cs['ratio_vs_moyenne'] ?? null;
            if ($ratio === null) {
                continue;
            }
            $tone = $ratio < 50 ? 'danger' : ($ratio < 70 ? 'warning' : 'success');
            $rows .= '<tr>'
                . '<td><strong>' . View::e((string) ($cs['user_name'] ?? ('Utilisateur #' . $cs['user_id']))) . '</strong></td>'
                . '<td style="text-align:center;">' . (int) $cs['nb_colis'] . '</td>'
                . '<td style="text-align:right;">' . number_format((float) $cs['prix_kg_moyen_agent'], 0, ',', ' ') . ' XOF/kg</td>'
                . '<td style="text-align:center;">' . Ui::badge($ratio . '% de la moyenne', $tone) . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            return Ui::emptyState('Aucun signal', 'Aucun agent ne présente un prix moyen au kg significativement inférieur à la moyenne (180 derniers jours, min. 5 colis).');
        }

        return '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>Agent</th><th>Nb colis (180j)</th><th style="text-align:right;">Prix moyen/kg</th><th style="text-align:center;">Vs moyenne des pairs</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }

    /** @param array<int, array<string, mixed>> $signalements */
    private static function signalementsTable(array $signalements): string
    {
        if (empty($signalements)) {
            return Ui::emptyState('Aucun signalement', 'Aucune anomalie détectée sur les données actuelles.');
        }

        $rows = '';
        foreach ($signalements as $s) {
            $cle = (string) ($s['id'] ?? '');
            $clos = (bool) ($s['clos'] ?? false);
            $statut = (string) ($s['statut_traitement'] ?? 'nouveau');

            $rows .= '<tr' . ($clos ? ' style="opacity:.55;"' : '') . '>'
                . '<td>' . Ui::badge((string) $s['gravite'], (string) $s['badgeTone']) . self::etatTraitement($statut, $s) . '</td>'
                . '<td>' . View::e((string) $s['type']) . '</td>'
                . '<td><strong>' . View::e((string) $s['employee']) . '</strong><br><small style="color:#64748b;">' . View::e((string) $s['agence']) . '</small></td>'
                . '<td>' . View::e((string) $s['description']) . '</td>'
                . '<td style="text-align:right;">' . ((float) $s['montant'] > 0 ? number_format((float) $s['montant'], 0, ',', ' ') . ' XOF' : '—') . '</td>'
                . '<td>' . View::e(date('d/m/Y', strtotime((string) $s['date']))) . '</td>'
                . '<td>' . self::actionsTraitement($cle, $clos) . '</td>'
                . '</tr>';
        }

        return '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>Gravité</th><th>Type</th><th>Agent / Agence</th><th>Description</th><th style="text-align:right;">Montant</th><th>Date</th><th>Traitement</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }

    /**
     * Etat de traitement affiche sous la gravite.
     *
     * @param array<string, mixed> $signalement
     */
    private static function etatTraitement(string $statut, array $signalement): string
    {
        if ($statut === 'nouveau') {
            return '';
        }

        $libelle = match ($statut) {
            'traite' => 'Traité',
            'classe' => 'Classé sans suite',
            default => 'Vu',
        };

        $par = $signalement['traitement_par'] ?? null;
        $date = $signalement['traitement_date'] ?? null;
        $infobulle = trim(($par !== null ? 'Par ' . (string) $par : '')
            . ($date !== null ? ' le ' . date('d/m/Y', strtotime((string) $date)) : ''));

        $commentaire = $signalement['traitement_commentaire'] ?? null;

        return '<br><small style="color:#64748b; font-weight:700;" title="' . View::e($infobulle) . '">' . View::e($libelle) . '</small>'
            . ($commentaire !== null ? '<br><small style="color:#94a3b8; font-style:italic;">' . View::e((string) $commentaire) . '</small>' : '');
    }

    /**
     * Boutons de traitement d'un signalement.
     */
    private static function actionsTraitement(string $cle, bool $clos): string
    {
        if ($cle === '') {
            return '';
        }

        $bouton = static function (string $statut, string $libelle, string $fond, string $couleur) use ($cle): string {
            return '<form method="post" action="' . View::url('pilotage-dg/anomalies/traiter') . '" class="js-protect-form" style="display:inline;">'
                . Form::hidden('_csrf_token', Csrf::token())
                . Form::hidden('cle', $cle)
                . Form::hidden('statut', $statut)
                . '<button type="submit" style="padding:4px 9px; margin:0 3px 3px 0; background:' . $fond . '; color:' . $couleur . '; border:1px solid ' . $couleur . '33; border-radius:6px; font-size:.72rem; font-weight:700; cursor:pointer;">' . $libelle . '</button>'
                . '</form>';
        };

        if ($clos) {
            return $bouton('rouvrir', 'Rouvrir', '#f1f5f9', '#0f172a');
        }

        return $bouton('traite', 'Traité', '#ecfdf5', '#047857')
            . $bouton('classe', 'Sans suite', '#f1f5f9', '#475569');
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
            // Le chiffre d affaires en euros est desormais separe : il s affiche a part
            // plutot que d etre fondu dans le total en francs, comme auparavant.
            $montantTotalEur = (float) ($a['montant_total_eur'] ?? 0);
            $taux = $montantTotal > 0 ? round(($montantImpaye / $montantTotal) * 100, 1) : 0.0;
            $tone = $taux >= 30 ? 'danger' : ($taux >= 15 ? 'warning' : 'success');

            $rows .= '<tr>'
                . '<td><strong>' . View::e((string) $a['agence_name']) . '</strong></td>'
                . '<td style="text-align:center;">' . (int) $a['nb_factures'] . '</td>'
                . '<td style="text-align:right;">' . number_format($montantTotal, 0, ',', ' ') . ' XOF'
                . ($montantTotalEur > 0 ? '<br><small style="color:#64748b;">' . number_format($montantTotalEur, 2, ',', ' ') . ' EUR</small>' : '')
                . '</td>'
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
            . Form::select('entity_type', $entityOpts, (string) ($filters['entity_type'] ?? ''), ['label' => 'Module / Entité'])
            . Form::input('start_date', ['label' => 'Du', 'type' => 'date', 'value' => (string) ($filters['start_date'] ?? '')])
            . Form::input('end_date', ['label' => 'Au', 'type' => 'date', 'value' => (string) ($filters['end_date'] ?? '')])
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

    /**
     * Rend la difference entre old_values et new_values d'une entree d'audit.
     * Sans ce detail, le journal disait qui avait modifie quoi, mais pas ce qui avait
     * change : un montant passe de 100 000 a 10 000 y etait indiscernable d'une
     * correction de libelle.
     *
     * @param array<string, mixed> $log
     */
    private static function auditDiff(array $log): string
    {
        $decode = static function ($raw): array {
            if (is_array($raw)) {
                return $raw;
            }
            if (!is_string($raw) || trim($raw) === '') {
                return [];
            }
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        };

        $old = $decode($log['old_values'] ?? null);
        $new = $decode($log['new_values'] ?? null);

        if ($old === [] && $new === []) {
            return '<span style="color:#94a3b8;">—</span>';
        }

        $format = static function ($v): string {
            if ($v === null) {
                return '∅';
            }
            if (is_bool($v)) {
                return $v ? 'oui' : 'non';
            }
            if (is_scalar($v)) {
                return mb_strimwidth((string) $v, 0, 40, '…');
            }
            return '…';
        };

        $lignes = [];
        foreach (array_keys($new + $old) as $champ) {
            $avant = $old[$champ] ?? null;
            $apres = $new[$champ] ?? null;

            if ($avant === $apres) {
                continue;
            }
            if (is_array($avant) || is_array($apres)) {
                continue;
            }

            $lignes[] = '<div style="white-space:nowrap;"><strong>' . View::e((string) $champ) . '</strong> : '
                . '<span style="color:#b91c1c;">' . View::e($format($avant)) . '</span>'
                . ' <span style="color:#94a3b8;">→</span> '
                . '<span style="color:#15803d;">' . View::e($format($apres)) . '</span></div>';

            if (count($lignes) >= 6) {
                $lignes[] = '<div style="color:#94a3b8;">…</div>';
                break;
            }
        }

        if ($lignes === []) {
            return '<span style="color:#94a3b8;">Aucun champ modifié</span>';
        }

        return '<div style="font-size:0.75rem; line-height:1.5;">' . implode('', $lignes) . '</div>';
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
                . '<td>' . self::auditDiff($log) . '</td>'
                . '<td>' . View::e((string) ($log['ip_address'] ?? '—')) . '</td>'
                . '</tr>';
        }

        return '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>Date & Heure</th><th>Utilisateur</th><th>Action</th><th>Entité</th><th>Modifications</th><th>IP</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }
}
