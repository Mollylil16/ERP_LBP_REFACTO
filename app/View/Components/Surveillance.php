<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

final class Surveillance
{
    /**
     * Rendu de la page de tableau de bord de surveillance DG.
     */
    public static function dashboardPage(
        array $stats,
        array $alerts,
        array $employees,
        array $trend,
        array $rules,
        array $filters,
        array $users,
        int $nbRecPending = 0
    ): string {
        $trainBtn = '<form method="post" action="' . View::url('surveillance/train-ml') . '" style="display:inline; margin-right:0.5rem;">'
            . '<input type="hidden" name="_csrf_token" value="' . View::e(\App\Helpers\Csrf::token()) . '">'
            . Ui::button('🔄 Ré-entraîner les modèles IA', ['type' => 'submit', 'variant' => 'accent', 'class' => 'finea-button-sm'])
            . '</form>';

        $header = Ui::pageHeader(
            'Centre de Surveillance & Intégrité',
            'Pilotage des risques de fraude interne, détection des comportements suspects et audit cryptographique de l\'ERP.',
            [
                'eyebrow' => 'Pilotage DG',
                'actions' => [
                    $trainBtn,
                    Ui::button('📁 Exporter PDF (Mensuel)', ['href' => 'surveillance/export-pdf', 'variant' => 'secondary', 'class' => 'finea-button-sm']),
                    Ui::button('📥 Exporter Excel (CSV)', ['href' => 'surveillance/export-excel?' . http_build_query($filters), 'variant' => 'secondary', 'class' => 'finea-button-sm']),
                ]
            ]
        );

        $kpiItems = [
            ['label' => 'Alertes non traitées', 'value' => (string) $stats['unresolved'], 'meta' => 'En attente de qualification DG', 'tone' => $stats['unresolved'] > 0 ? 'danger' : 'success'],
            ['label' => 'Signalements Très Graves', 'value' => (string) $stats['tres_grave'], 'meta' => 'Risque de fraude imminent', 'tone' => $stats['tres_grave'] > 0 ? 'danger' : 'neutral'],
            ['label' => 'Signalements Graves', 'value' => (string) $stats['grave'], 'meta' => 'Écart suspect détecté', 'tone' => $stats['grave'] > 0 ? 'warning' : 'neutral'],
            ['label' => 'Signalements Moyens', 'value' => (string) $stats['moyen'], 'meta' => 'Variance ou anomalie opérationnelle', 'tone' => 'neutral'],
        ];
        $kpis = Dashboard::kpis($kpiItems);

        $navTabs = self::renderNavTabs('dashboard', $nbRecPending);

        // Formulaire de filtres
        $filterForm = self::renderFilterForm($filters, $users, $rules);

        // Section principale avec grille à 2 colonnes
        $alertsSection = self::renderAlertsTable($alerts);
        $rankingSection = self::renderEmployeeRanking($employees);

        // Section graphique de tendance
        $trendSection = self::renderTrendChart($trend);

        $recAlert = '';
        if ($nbRecPending > 0) {
            $recAlert = '<div style="background:#fffbeb; border-left:4px solid #f59e0b; padding:1.25rem; border-radius:6px; margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center; box-shadow:0 1px 3px rgba(0,0,0,0.05);">'
                . '  <div>'
                . '    <strong style="color:#d97706; font-size:1rem; display:inline-flex; align-items:center; gap:6px;"><svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" style="vertical-align:middle; display:inline-block;"><rect x="4" y="4" width="16" height="16" rx="2" ry="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="1" x2="9" y2="4"/><line x1="15" y1="1" x2="15" y2="4"/><line x1="9" y1="20" x2="9" y2="23"/><line x1="15" y1="20" x2="15" y2="23"/><line x1="20" y1="9" x2="23" y2="9"/><line x1="20" y1="15" x2="23" y2="15"/><line x1="1" y1="9" x2="4" y2="9"/><line x1="1" y1="15" x2="4" y2="15"/></svg>' . $nbRecPending . ' recommandation(s) IA en attente</strong>'
                . '    <p style="margin:0.25rem 0 0 0; color:#475569; font-size:0.9rem;">Les modèles de machine learning ont formulé des préconisations de sécurité qui nécessitent la validation de la Direction.</p>'
                . '  </div>'
                . '  ' . Ui::button('Consulter les recommandations', ['href' => 'surveillance/recommandations', 'variant' => 'warning', 'class' => 'finea-button-sm'])
                . '</div>';
        }

        return '<div class="finea-shell">'
            . '<style>'
            . '  .finea-kpi-card.tone-danger { border-left: 4px solid #ef4444; background: #fef2f2; }'
            . '  .finea-kpi-card.tone-warning { border-left: 4px solid #f59e0b; background: #fffbeb; }'
            . '  .finea-kpi-card.tone-success { border-left: 4px solid #10b981; background: #ecfdf5; }'
            . '  .surveillance-grid { display: grid; grid-template-columns: 3fr 2fr; gap: 1.5rem; margin-top: 1.5rem; }'
            . '  @media (max-width: 1024px) { .surveillance-grid { grid-template-columns: 1fr; } }'
            . '  .trend-chart-container { margin-top: 1.5rem; }'
            . '  .filter-bar { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem; }'
            . '</style>'
            . '<div class="finea-container">'
            . $header
            . $navTabs
            . $recAlert
            . $kpis
            . '<div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; margin-top:1.5rem;">'
            . '  <div>' . Ui::section(View::html('<svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2.5" fill="none" style="vertical-align:middle; margin-right:8px; display:inline-block; color:#2563eb;"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg> Tendance mensuelle des alertes'), $trendSection) . '</div>'
            . '  <div>' . Ui::section(View::html('<svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2.5" fill="none" style="vertical-align:middle; margin-right:8px; display:inline-block; color:#ef4444;"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg> Heatmap des Anomalies Actives (Agence vs Service)'), self::renderHeatmap()) . '</div>'
            . '</div>'
            . '<div style="margin-top: 1.5rem;">' . $filterForm . '</div>'
            . '<div class="surveillance-grid">'
            . '  <div>' . Ui::section(View::html('<svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2.5" fill="none" style="vertical-align:middle; margin-right:8px; display:inline-block; color:#dc2626;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> Alertes d\'intégrité non résolues'), $alertsSection) . '</div>'
            . '  <div>' . Ui::section(View::html('<svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2.5" fill="none" style="vertical-align:middle; margin-right:8px; display:inline-block; color:#16a34a;"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg> Classement de Performance & Intégrité des employés'), $rankingSection) . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';
    }

    /**
     * Rendu des onglets de navigation du module.
     */
    public static function renderNavTabs(string $activeTab, int $nbRecPending = 0): string
    {
        $dashIcon = '<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" style="vertical-align:middle; margin-right:6px; display:inline-block;"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>';
        $recIcon = '<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" style="vertical-align:middle; margin-right:6px; display:inline-block;"><rect x="4" y="4" width="16" height="16" rx="2" ry="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="1" x2="9" y2="4"/><line x1="15" y1="1" x2="15" y2="4"/><line x1="9" y1="20" x2="9" y2="23"/><line x1="15" y1="20" x2="15" y2="23"/><line x1="20" y1="9" x2="23" y2="9"/><line x1="20" y1="15" x2="23" y2="15"/><line x1="1" y1="9" x2="4" y2="9"/><line x1="1" y1="15" x2="4" y2="15"/></svg>';
        $configIcon = '<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" style="vertical-align:middle; margin-right:6px; display:inline-block;"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>';
        $linkIcon = '<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" style="vertical-align:middle; margin-right:6px; display:inline-block;"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>';

        $recLabel = $recIcon . ' Recommandations IA';
        if ($nbRecPending > 0) {
            $recLabel .= ' <span style="background:#ef4444; color:#fff; padding:2px 6px; border-radius:10px; font-size:0.75rem; font-weight:700;">' . $nbRecPending . '</span>';
        }

        $tabs = [
            'dashboard' => ['label' => $dashIcon . ' Dashboard', 'url' => 'surveillance', 'is_html' => true],
            'recommandations' => ['label' => $recLabel, 'url' => 'surveillance/recommandations', 'is_html' => true],
            'config' => ['label' => $configIcon . ' Configuration des Règles', 'url' => 'surveillance/config', 'is_html' => true],
            'integrite' => ['label' => $linkIcon . ' Chaîne d\'Intégrité Audit', 'url' => 'surveillance/integrite', 'is_html' => true],
        ];

        $html = '<div class="finea-tabs" style="display:flex; border-bottom:1px solid #e2e8f0; margin-bottom:1.5rem; gap:1rem; padding-bottom:0.5rem;">';
        foreach ($tabs as $key => $tab) {
            $isActive = $key === $activeTab;
            $style = $isActive 
                ? 'color:var(--module-accent); border-bottom:2px solid var(--module-accent); font-weight:700;'
                : 'color:#64748b; font-weight:500;';
            $label = !empty($tab['is_html']) ? $tab['label'] : View::e($tab['label']);
            $html .= '<a href="' . View::url($tab['url']) . '" style="padding:0.5rem 1rem; text-decoration:none; font-size:0.95rem; ' . $style . '">' . $label . '</a>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Génère la heatmap des anomalies par agence et service.
     */
    private static function renderHeatmap(): string
    {
        $db = \App\Models\Database::getConnection();
        
        // Heatmap: croisement Agence (company_sites) / Service (departments) avec somme des alertes
        $data = $db->query("
            SELECT COALESCE(s.name, 'Agence Inconnue') AS site_name, 
                   COALESCE(dept.name, 'Service Inconnu') AS service_name, 
                   COUNT(a.id) AS count
            FROM lbp_alertes_integrite a
            LEFT JOIN users u ON a.user_id = u.id
            LEFT JOIN rh_employees e ON u.rh_employee_id = e.id
            LEFT JOIN company_sites s ON e.site_id = s.id
            LEFT JOIN rh_services dept ON e.service_id = dept.id
            WHERE a.statut IN ('nouvelle', 'en_cours', 'confirmee')
            GROUP BY s.name, dept.name
        ")->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        if (empty($data)) {
            return '<div style="padding:1.5rem; text-align:center; color:#64748b;">Aucune alerte active à cartographier.</div>';
        }

        $agences = [];
        $services = [];
        $matrix = [];
        $maxCount = 1;

        foreach ($data as $row) {
            $ag = $row['site_name'];
            $ser = $row['service_name'];
            $count = (int) $row['count'];
            
            $agences[$ag] = true;
            $services[$ser] = true;
            $matrix[$ag][$ser] = $count;
            if ($count > $maxCount) {
                $maxCount = $count;
            }
        }

        $agences = array_keys($agences);
        $services = array_keys($services);

        $headerCols = '<th>Agence</th>';
        foreach ($services as $ser) {
            $headerCols .= '<th>' . View::e($ser) . '</th>';
        }

        $rowsHtml = '';
        foreach ($agences as $ag) {
            $rowsHtml .= '<tr>';
            $rowsHtml .= '<td><strong>' . View::e($ag) . '</strong></td>';
            foreach ($services as $ser) {
                $count = $matrix[$ag][$ser] ?? 0;
                $intensity = $count > 0 ? min(0.9, 0.1 + ($count / $maxCount) * 0.8) : 0;
                $bg = $count > 0 ? "background: rgba(239, 68, 68, {$intensity}); color: #fff; font-weight:700;" : "background: #f8fafc; color:#cbd5e1;";
                $rowsHtml .= '<td style="text-align:center; ' . $bg . '">' . $count . '</td>';
            }
            $rowsHtml .= '</tr>';
        }

        return '<div class="finea-table-wrapper" style="margin-top:1rem;">'
            . '<table class="finea-table"><thead><tr>' . $headerCols . '</tr></thead><tbody>' . $rowsHtml . '</tbody></table>'
            . '</div>';
    }

    /**
     * Rendu du formulaire de filtres.
     */
    private static function renderFilterForm(array $filters, array $users, array $rules): string
    {
        $userOptions = '<option value="">Tous les collaborateurs</option>';
        foreach ($users as $u) {
            $selected = (int) $filters['user_id'] === (int) $u['id'] ? ' selected' : '';
            $userOptions .= '<option value="' . $u['id'] . '"' . $selected . '>' . View::e($u['full_name']) . '</option>';
        }

        $ruleOptions = '<option value="">Toutes les règles</option>';
        foreach ($rules as $r) {
            $selected = $filters['regle_code'] === $r['code'] ? ' selected' : '';
            $ruleOptions .= '<option value="' . View::e($r['code']) . '"' . $selected . '>' . View::e($r['titre']) . '</option>';
        }

        $statutOptions = '';
        $statuts = [
            '' => 'En attente DG (Nouvelle / En cours)',
            'nouvelle' => 'Nouvelle uniquement',
            'en_cours' => 'En cours uniquement',
            'justifiee' => 'Justifiées (Rejetées)',
            'confirmee' => 'Confirmées (Vol/Fraude avéré)',
        ];
        foreach ($statuts as $k => $v) {
            $selected = $filters['statut'] === $k ? ' selected' : '';
            $statutOptions .= '<option value="' . View::e($k) . '"' . $selected . '>' . View::e($v) . '</option>';
        }

        return '<div class="filter-bar">'
            . '<form method="get" action="' . View::url('surveillance') . '" class="rh-form-grid" style="grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:1rem; align-items:end;">'
            . '  <div><label style="font-size:0.8rem; font-weight:700; color:#475569; display:block; margin-bottom:0.25rem;">Collaborateur</label>'
            . '    <select name="user_id" style="width:100%; padding:0.5rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.9rem;">' . $userOptions . '</select></div>'
            . '  <div><label style="font-size:0.8rem; font-weight:700; color:#475569; display:block; margin-bottom:0.25rem;">Règle d\'Intégrité</label>'
            . '    <select name="regle_code" style="width:100%; padding:0.5rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.9rem;">' . $ruleOptions . '</select></div>'
            . '  <div><label style="font-size:0.8rem; font-weight:700; color:#475569; display:block; margin-bottom:0.25rem;">Statut de l\'alerte</label>'
            . '    <select name="statut" style="width:100%; padding:0.5rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.9rem;">' . $statutOptions . '</select></div>'
            . '  <div><label style="font-size:0.8rem; font-weight:700; color:#475569; display:block; margin-bottom:0.25rem;">Date Début</label>'
            . '    <input type="date" name="start_date" value="' . View::e($filters['start_date']) . '" style="width:100%; padding:0.5rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.9rem;"></div>'
            . '  <div><label style="font-size:0.8rem; font-weight:700; color:#475569; display:block; margin-bottom:0.25rem;">Date Fin</label>'
            . '    <input type="date" name="end_date" value="' . View::e($filters['end_date']) . '" style="width:100%; padding:0.5rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.9rem;"></div>'
            . '  <div style="display:flex; gap:0.5rem;">'
            . '    ' . Ui::button('🔍 Filtrer', ['type' => 'submit', 'variant' => 'primary', 'style' => 'flex:1; padding:0.55rem;'])
            . '    ' . Ui::button('🔄 Reset', ['href' => 'surveillance', 'variant' => 'secondary', 'style' => 'padding:0.55rem;'])
            . '  </div>'
            . '</form>'
            . '</div>';
    }

    /**
     * Rendu du tableau des alertes.
     */
    private static function renderAlertsTable(array $alerts): string
    {
        if (empty($alerts)) {
            return Ui::emptyState('Aucun signalement suspect', 'Félicitations, aucun comportement suspect détecté ou toutes les alertes ont été qualifiées.');
        }

        $rows = '';
        foreach ($alerts as $a) {
            $tone = match ($a['gravite']) {
                'tres_grave' => 'danger',
                'grave' => 'warning',
                'moyen' => 'neutral',
                default => 'neutral',
            };

            $statutBadge = match ($a['statut']) {
                'nouvelle' => '<span style="color:#ef4444; background:#fef2f2; border:1px solid #fca5a5; padding:2px 8px; border-radius:12px; font-size:0.75rem; font-weight:700;">Nouvelle</span>',
                'en_cours' => '<span style="color:#f59e0b; background:#fffbeb; border:1px solid #fcd34d; padding:2px 8px; border-radius:12px; font-size:0.75rem; font-weight:700;">En cours</span>',
                'justifiee' => '<span style="color:#64748b; background:#f1f5f9; border:1px solid #cbd5e1; padding:2px 8px; border-radius:12px; font-size:0.75rem; font-weight:700;">Justifiée</span>',
                'confirmee' => '<span style="color:#b91c1c; background:#fef2f2; border:1px solid #dc2626; padding:2px 8px; border-radius:12px; font-size:0.75rem; font-weight:700;">⚠️ Confirmée</span>',
                default => $a['statut'],
            };

            $urlDetail = View::url('surveillance/alertes/' . $a['id']);

            $rows .= '<tr style="cursor:pointer;" onclick="window.location=\'' . $urlDetail . '\'">'
                . '<td>' . Ui::badge(strtoupper($a['gravite']), $tone) . '</td>'
                . '<td><strong>' . View::e($a['user_name'] ?? 'Inconnu') . '</strong></td>'
                . '<td><strong>' . View::e($a['regle_titre'] ?? $a['regle_code']) . '</strong></td>'
                . '<td style="text-align:center;">' . $statutBadge . '</td>'
                . '<td>' . View::e(date('d/m/Y H:i', strtotime($a['created_at']))) . '</td>'
                . '<td style="text-align:right;"><a href="' . $urlDetail . '" style="color:var(--module-accent); font-weight:700; text-decoration:none;">Qualifier →</a></td>'
                . '</tr>';
        }

        return '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>Gravité</th><th>Collaborateur</th><th>Règle violée</th><th style="text-align:center;">Statut</th><th>Détectée le</th><th>Action</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }

    /**
     * Rendu du tableau de classement des employés.
     */
    private static function renderEmployeeRanking(array $employees): string
    {
        if (empty($employees)) {
            return Ui::emptyState('Aucune donnée', 'Aucun score disponible.');
        }

        $rows = '';
        foreach ($employees as $idx => $emp) {
            $rank = $idx + 1;
            $medaille = match ($rank) {
                1 => '🥇 ',
                2 => '🥈 ',
                3 => '🥉 ',
                default => '#' . $rank . ' ',
            };

            $score = (float) $emp['score_global'];
            $scoreTone = $score >= 80 ? 'success' : ($score >= 60 ? 'warning' : 'danger');

            $urlProfile = View::url('surveillance/employes/' . $emp['user_id']);

            $rows .= '<tr style="cursor:pointer;" onclick="window.location=\'' . $urlProfile . '\'">'
                . '<td><strong>' . $medaille . '</strong></td>'
                . '<td><strong>' . View::e($emp['full_name']) . '</strong></td>'
                . '<td style="text-align:center;"><strong style="font-size:1.1rem; color:' . ($score >= 80 ? '#10b981' : ($score >= 60 ? '#f59e0b' : '#ef4444')) . '">' . number_format($score, 1) . ' / 100</strong></td>'
                . '<td style="text-align:center;">' . ((int)$emp['nb_tres_grave'] > 0 ? '<span style="color:#ef4444; font-weight:700;">' . $emp['nb_tres_grave'] . '</span>' : '0') . '</td>'
                . '</tr>';
        }

        return '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
            . '<th>Pos.</th><th>Collaborateur</th><th style="text-align:center;">Score Intégrité</th><th style="text-align:center;">🔴 TG</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }

    /**
     * Graphique de tendance simple avec Chart.js.
     */
    private static function renderTrendChart(array $trend): string
    {
        if (empty($trend)) {
            return '<p style="padding:1.5rem; text-align:center; color:#64748b;">Pas assez de données de tendance pour le moment.</p>';
        }

        $labels = [];
        $tresGraveData = [];
        $graveData = [];
        $moyenData = [];

        foreach ($trend as $t) {
            $labels[] = $t['mois'];
            $tresGraveData[] = (int) $t['tres_grave'];
            $graveData[] = (int) $t['grave'];
            $moyenData[] = (int) $t['moyen'];
        }

        $jsLabels = json_encode($labels);
        $jsTG = json_encode($tresGraveData);
        $jsG = json_encode($graveData);
        $jsM = json_encode($moyenData);

        $canvasId = 'trendChart_' . uniqid();

        return '<div style="padding:1rem; position:relative; height:250px;">'
            . '  <canvas id="' . $canvasId . '" style="width:100%; height:230px;"></canvas>'
            . '</div>'
            . '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>'
            . '<script>'
            . '  document.addEventListener("DOMContentLoaded", function() {'
            . '    const ctx = document.getElementById("' . $canvasId . '").getContext("2d");'
            . '    new Chart(ctx, {'
            . '      type: "bar",'
            . '      data: {'
            . '        labels: ' . $jsLabels . ','
            . '        datasets: ['
            . '          { label: "Très Grave", data: ' . $jsTG . ', backgroundColor: "#ef4444" },'
            . '          { label: "Grave", data: ' . $jsG . ', backgroundColor: "#f59e0b" },'
            . '          { label: "Moyen", data: ' . $jsM . ', backgroundColor: "#64748b" }'
            . '        ]'
            . '      },'
            . '      options: {'
            . '        responsive: true,'
            . '        maintainAspectRatio: false,'
            . '        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }'
            . '      }'
            . '    });'
            . '  });'
            . '</script>';
    }

    /**
     * Rendu détaillé d'une alerte spécifique.
     */
    public static function alerteDetailPage(array $alert): string
    {
        $header = Ui::pageHeader(
            "Qualification de l'Alerte #{$alert['id']}",
            "Consultez les métadonnées et décidez si l'anomalie est justifiée ou confirmée comme une fraude.",
            [
                'eyebrow' => 'Pilotage DG',
                'actions' => Ui::button('← Retour au Dashboard', ['href' => 'surveillance', 'variant' => 'secondary', 'class' => 'finea-button-sm'])
            ]
        );

        $tone = match ($alert['gravite']) {
            'tres_grave' => 'danger',
            'grave' => 'warning',
            'moyen' => 'neutral',
            default => 'neutral',
        };

        // Rendu des infos du collaborateur
        $userInfo = '<div class="card" style="padding:1rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;">'
            . '  <h3 style="margin-top:0; font-size:1rem; border-bottom:1px solid #cbd5e1; padding-bottom:0.5rem; color:#1e293b;">Collaborateur impliqué</h3>'
            . '  <p style="margin:0.25rem 0;"><strong>Nom complet :</strong> ' . View::e($alert['user_name'] ?? 'Inconnu') . '</p>'
            . '  <p style="margin:0.25rem 0;"><strong>Email :</strong> ' . View::e($alert['user_email'] ?? '—') . '</p>'
            . '  <p style="margin:0.25rem 0;"><strong>Téléphone :</strong> ' . View::e($alert['user_phone'] ?? '—') . '</p>'
            . '  <p style="margin-top:0.75rem;"><a href="' . View::url('surveillance/employes/' . $alert['user_id']) . '" style="color:var(--module-accent); font-weight:700; text-decoration:none;">Consulter sa fiche intégrité →</a></p>'
            . '</div>';

        // Rendu des infos techniques de l'alerte
        $contexteHtml = '<pre style="background:#0f172a; color:#38bdf8; padding:1rem; border-radius:6px; font-family:monospace; font-size:0.85rem; overflow:auto;">'
            . View::e(json_encode($alert['contexte'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
            . '</pre>';

        $metadataInfo = '<div class="card" style="padding:1rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;">'
            . '  <h3 style="margin-top:0; font-size:1rem; border-bottom:1px solid #cbd5e1; padding-bottom:0.5rem; color:#1e293b;">Détails Alerte</h3>'
            . '  <p style="margin:0.25rem 0;"><strong>Règle :</strong> ' . View::e($alert['regle_titre'] ?? $alert['regle_code']) . '</p>'
            . '  <p style="margin:0.25rem 0;"><strong>Gravité :</strong> ' . Ui::badge(strtoupper($alert['gravite']), $tone) . '</p>'
            . '  <p style="margin:0.25rem 0;"><strong>Date détection :</strong> ' . date('d/m/Y H:i:s', strtotime($alert['created_at'])) . '</p>'
            . '  <p style="margin:0.25rem 0;"><strong>IP Client :</strong> ' . View::e($alert['audit_ip'] ?? '—') . '</p>'
            . '  <p style="margin:0.25rem 0; font-size:0.8rem; color:#64748b;"><strong>User Agent :</strong> ' . View::e($alert['audit_ua'] ?? '—') . '</p>'
            . '  <p style="margin:0.5rem 0 0 0; font-family:monospace; font-size:0.75rem; color:#dc2626;"><strong>Hash Audit :</strong> ' . View::e($alert['audit_hash'] ?? 'Aucun (hors audit)') . '</p>'
            . '</div>';

        // Décision DG
        $decisionHtml = '';
        if ($alert['statut'] === 'nouvelle' || $alert['statut'] === 'en_cours') {
            $decisionHtml = '<div class="finea-section-card" style="padding:1.5rem; border:2px solid var(--module-accent); border-radius:8px; background:#fff;">'
                . '  <h3 style="margin-top:0; color:var(--module-accent);">Qualification Décisionnelle (Direction Générale)</h3>'
                . '  <form method="post" action="' . View::url('surveillance/alertes/' . $alert['id'] . '/traiter') . '">'
                . '    <input type="hidden" name="_csrf_token" value="' . View::e(\App\Helpers\Csrf::token()) . '">'
                . '    <div style="margin-bottom:1rem;">'
                . '      <label style="font-weight:700; display:block; margin-bottom:0.5rem;">Décision de qualification :</label>'
                . '      <label style="margin-right:1.5rem; cursor:pointer;"><input type="radio" name="statut" value="justifiee" checked> 🟢 <strong>Justifiée / Erreur</strong> (Exclure du score employé, pas de vol)</label>'
                . '      <label style="cursor:pointer;"><input type="radio" name="statut" value="confirmee"> 🔴 <strong>Confirmée / Comportement Suspect</strong> (Appliquer pénalité sur le score et logger la fraude)</label>'
                . '    </div>'
                . '    <div style="margin-bottom:1.5rem;">'
                . '      <label style="font-weight:700; display:block; margin-bottom:0.5rem;">Commentaire / Justification DG :</label>'
                . '      <textarea name="commentaire_dg" rows="4" style="width:100%; padding:0.75rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.95rem;" placeholder="Indiquez la raison de cette qualification (auditions, circonstances, sanctions, etc.)" required></textarea>'
                . '    </div>'
                . '    ' . Ui::button('Valider la décision', ['type' => 'submit', 'variant' => 'primary'])
                . '  </form>'
                . '</div>';
        } else {
            $decisionTone = $alert['statut'] === 'confirmee' ? 'danger' : 'neutral';
            $decisionHtml = '<div class="card" style="padding:1.5rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;">'
                . '  <h3 style="margin-top:0; color:#1e293b;">Décision DG Enregistrée</h3>'
                . '  <p style="font-size:1.1rem;">Statut de qualification : ' . Ui::badge(strtoupper($alert['statut']), $decisionTone) . '</p>'
                . '  <p><strong>Qualifié par :</strong> ' . View::e($alert['DG_name'] ?? 'DG') . ' le ' . date('d/m/Y à H:i', strtotime($alert['traite_at'])) . '</p>'
                . '  <div style="background:#fff; border-left:4px solid #94a3b8; padding:0.75rem 1rem; margin-top:1rem; font-style:italic;">'
                . '    ' . nl2br(View::e($alert['commentaire_dg']))
                . '  </div>'
                . '</div>';
        }

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . '<div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem; margin-bottom:1.5rem;">'
            . '  <div>' . $userInfo . '<div style="margin-top:1.5rem;">' . $metadataInfo . '</div></div>'
            . '  <div>' . Ui::section('Contexte technique (Payload)', $contexteHtml) . '</div>'
            . '</div>'
            . $decisionHtml
            . '</div>'
            . '</div>';
    }

    /**
     * Page de profil de l'employé avec son historique.
     */
    public static function employeProfilePage(array $profile): string
    {
        $emp = $profile['employee'];
        $header = Ui::pageHeader(
            "Fiche d'Intégrité - {$emp['full_name']}",
            "Historique complet des comportements, alertes d'intégrité et notation de fiabilité.",
            [
                'eyebrow' => 'Surveillance DG',
                'actions' => Ui::button('← Retour au Dashboard', ['href' => 'surveillance', 'variant' => 'secondary', 'class' => 'finea-button-sm'])
            ]
        );

        $score = (float) $emp['score_global'];
        $scoreTone = $score >= 80 ? 'success' : ($score >= 60 ? 'warning' : 'danger');

        // Fiche de synthèse
        $summary = '<div class="card" style="padding:1.5rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; display:flex; justify-content:space-between; align-items:center;">'
            . '  <div>'
            . '    <h3 style="margin-top:0; font-size:1.25rem;">Score de Fiabilité Global</h3>'
            . '    <p style="margin:0.25rem 0; color:#64748b;">Calculé en continu d\'après les alertes d\'intégrité actives.</p>'
            . '    <p style="margin:0.5rem 0 0 0; font-size:0.85rem; color:#94a3b8;">Dernière mise à jour : ' . ($emp['derniere_maj'] ? date('d/m/Y H:i', strtotime($emp['derniere_maj'])) : 'Aujourd\'hui') . '</p>'
            . '  </div>'
            . '  <div style="text-align:center; padding:1rem; border-radius:12px; background:#fff; border:1px solid #cbd5e1; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);">'
            . '    <strong style="font-size:2.2rem; color:' . ($score >= 80 ? '#10b981' : ($score >= 60 ? '#f59e0b' : '#ef4444')) . '; display:block;">' . number_format($score, 1) . '</strong>'
            . '    <span style="font-size:0.8rem; font-weight:700; color:#64748b; text-transform:uppercase;">' . Ui::badge('Score Fiabilité', $scoreTone) . '</span>'
            . '  </div>'
            . '</div>';

        // Historique des alertes
        $alerts = $profile['alerts_history'];
        $rows = '';
        if (empty($alerts)) {
            $historyTable = Ui::emptyState('Aucune alerte', 'Cet employé présente un historique de comportement vierge.');
        } else {
            foreach ($alerts as $a) {
                $tone = match ($a['gravite']) {
                    'tres_grave' => 'danger',
                    'grave' => 'warning',
                    'moyen' => 'neutral',
                    default => 'neutral',
                };
                $urlDetail = View::url('surveillance/alertes/' . $a['id']);
                $rows .= '<tr style="cursor:pointer;" onclick="window.location=\'' . $urlDetail . '\'">'
                    . '<td>' . Ui::badge(strtoupper($a['gravite']), $tone) . '</td>'
                    . '<td><strong>' . View::e($a['regle_titre']) . '</strong></td>'
                    . '<td>' . View::e($a['statut']) . '</td>'
                    . '<td>' . date('d/m/Y H:i', strtotime($a['created_at'])) . '</td>'
                    . '<td style="text-align:right;"><a href="' . $urlDetail . '" style="color:var(--module-accent); font-weight:700; text-decoration:none;">Détails →</a></td>'
                    . '</tr>';
            }
            $historyTable = '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
                . '<th>Gravité</th><th>Règle déclenchée</th><th>Qualification DG</th><th>Détectée le</th><th>Action</th>'
                . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
        }

        // Tendance individuelle
        $trendHtml = self::renderTrendChart($profile['trend']);

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . $summary
            . '<div style="margin-top:1.5rem; display:grid; grid-template-columns:3fr 2fr; gap:1.5rem;">'
            . '  <div>' . Ui::section('Histotique de surveillance (' . count($alerts) . ' alerte(s))', $historyTable) . '</div>'
            . '  <div>' . Ui::section('Tendance historique (6 mois)', $trendHtml) . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';
    }

    /**
     * Page de configuration des règles de détection.
     */
    public static function configReglesPage(array $rules): string
    {
        $header = Ui::pageHeader(
            'Configuration des Règles d\'Intégrité',
            'Activez/Désactivez les règles de détection et configurez les seuils de tolérance sans redéployer le code.',
            [
                'eyebrow' => 'Surveillance DG',
                'actions' => Ui::button(View::html('<svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" stroke-width="2.5" fill="none" style="vertical-align:middle; margin-right:4px;"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg> Retour au Dashboard'), ['href' => 'surveillance', 'variant' => 'secondary', 'class' => 'finea-button-sm'])
            ]
        );

        $navTabs = self::renderNavTabs('config');

        $html = '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . $navTabs;

        foreach ($rules as $r) {
            $isActive = (int) $r['is_active'] === 1;
            $params = json_decode($r['parametres_json'] ?? '', true) ?: [];

            // Rendu des champs de paramètres dynamiques
            $fields = '';
            foreach ($params as $k => $v) {
                $fields .= '<div style="margin-bottom:0.75rem;">'
                    . '  <label style="display:block; font-size:0.8rem; font-weight:700; color:#475569; margin-bottom:0.25rem;">' . View::e($k) . '</label>'
                    . '  <input type="text" name="parametres[' . View::e($k) . ']" value="' . View::e((string) $v) . '" style="width:100%; padding:0.5rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.9rem;">'
                    . '</div>';
            }

            if (empty($fields)) {
                $fields = '<p style="font-style:italic; color:#64748b; font-size:0.9rem;">Cette règle ne requiert aucun paramètre supplémentaire.</p>';
            }

            $ruleContent = '<form method="post" action="' . View::url('surveillance/config/' . $r['code'] . '/update') . '">'
                . '<input type="hidden" name="_csrf_token" value="' . View::e(\App\Helpers\Csrf::token()) . '">'
                . '<div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem;">'
                . '  <div>'
                . '    <p><strong>Description :</strong> ' . View::e($r['description']) . '</p>'
                . '    <div style="margin-bottom:1rem;">'
                . '      <label style="display:block; font-weight:700; margin-bottom:0.25rem;">Gravité de l\'alerte :</label>'
                . '      <select name="gravite" style="padding:0.5rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.9rem;">'
                . '        <option value="moyen"' . ($r['gravite'] === 'moyen' ? ' selected' : '') . '>Neutral / Moyen</option>'
                . '        <option value="grave"' . ($r['gravite'] === 'grave' ? ' selected' : '') . '>⚠️ Grave</option>'
                . '        <option value="tres_grave"' . ($r['gravite'] === 'tres_grave' ? ' selected' : '') . '>🚨 Très Grave</option>'
                . '      </select>'
                . '    </div>'
                . '    <div style="margin-bottom:1rem;">'
                . '      <label style="cursor:pointer; font-weight:700;"><input type="checkbox" name="is_active" value="1"' . ($isActive ? ' checked' : '') . '> Active (Détection opérationnelle)</label>'
                . '    </div>'
                . '  </div>'
                . '  <div>'
                . '    <h4 style="margin-top:0;">Seuils & Paramètres d\'évaluation</h4>'
                . '    ' . $fields
                . '  </div>'
                . '</div>'
                . '<div style="margin-top:1rem; border-top:1px solid #e2e8f0; padding-top:0.75rem; display:flex; justify-content:flex-end;">'
                . '  ' . Ui::button('Enregistrer la configuration', ['type' => 'submit', 'variant' => 'accent', 'class' => 'finea-button-sm'])
                . '</div>'
                . '</form>';

            $badgeTone = match ($r['gravite']) {
                'tres_grave' => 'danger',
                'grave' => 'warning',
                'moyen' => 'neutral',
                default => 'neutral',
            };

            $titleBadge = Ui::badge(strtoupper($r['gravite']), $badgeTone) . ' ' . ($isActive ? '🟢 Active' : '🔴 Désactivée');

            $html .= '<div style="margin-bottom:1.5rem;">' 
                . Ui::section(View::e($r['titre']), $ruleContent, $titleBadge) 
                . '</div>';
        }

        $html .= '</div></div>';
        return $html;
    }

    /**
     * Page de vérification de l'intégrité de la chaîne.
     */
    public static function verifyIntegritePage(array $result): string
    {
        $header = Ui::pageHeader(
            'Chaîne d\'Audit Immuable',
            'Vérification en temps réel du scellement cryptographique du journal d\'audit (輕量級區塊鏈).',
            [
                'eyebrow' => 'Sécurité ERP',
                'actions' => Ui::button(View::html('<svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" stroke-width="2.5" fill="none" style="vertical-align:middle; margin-right:4px;"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg> Retour au Dashboard'), ['href' => 'surveillance', 'variant' => 'secondary', 'class' => 'finea-button-sm'])
            ]
        );

        $navTabs = self::renderNavTabs('integrite');

        $status = $result['valid']
            ? '<div style="background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; padding:1.5rem; border-radius:8px; display:flex; align-items:center; gap:1rem; margin-bottom:1.5rem;">'
            . '  ' . View::html('<svg viewBox="0 0 24 24" width="32" height="32" stroke="#059669" stroke-width="2.5" fill="none" style="flex-shrink:0;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>')
            . '  <div>'
            . '    <h3 style="margin:0; font-size:1.15rem;">Chaîne d\'audit intacte</h3>'
            . '    <p style="margin:0.25rem 0 0 0;">Toutes les entrées d\'audit sont validées cryptographiquement. Aucune altération détectée.</p>'
            . '  </div>'
            . '</div>'
            : '<div style="background:#fef2f2; border:1px solid #fca5a5; color:#991b1b; padding:1.5rem; border-radius:8px; display:flex; align-items:center; gap:1rem; margin-bottom:1.5rem;">'
            . '  ' . View::html('<svg viewBox="0 0 24 24" width="32" height="32" stroke="#dc2626" stroke-width="2.5" fill="none" style="flex-shrink:0;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>')
            . '  <div>'
            . '    <h3 style="margin:0; font-size:1.15rem;">RUPTURE D\'INTÉGRITÉ DÉTECTÉE</h3>'
            . '    <p style="margin:0.25rem 0 0 0;">Une ou plusieurs entrées ont été modifiées ou supprimées a posteriori (directement dans la BDD).</p>'
            . '  </div>'
            . '</div>';

        $summary = '<div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:1rem; margin-bottom:1.5rem;">'
            . '  <div style="background:#f8fafc; border:1px solid #e2e8f0; padding:1rem; border-radius:6px; text-align:center;"><strong>' . $result['total'] . '</strong><span style="display:block; font-size:0.8rem; color:#64748b;">Entrées d\'audit</span></div>'
            . '  <div style="background:#f8fafc; border:1px solid #e2e8f0; padding:1rem; border-radius:6px; text-align:center;"><strong>' . $result['checked'] . '</strong><span style="display:block; font-size:0.8rem; color:#64748b;">Chaînages vérifiés</span></div>'
            . '  <div style="background:#f8fafc; border:1px solid #e2e8f0; padding:1rem; border-radius:6px; text-align:center; color:' . ($result['valid'] ? '#10b981' : '#ef4444') . ';"><strong>' . count($result['broken']) . '</strong><span style="display:block; font-size:0.8rem; color:#64748b;">Anomalies</span></div>'
            . '</div>';

        $brokenTable = '';
        if (!$result['valid']) {
            $rows = '';
            foreach ($result['broken'] as $b) {
                $rows .= '<tr>'
                    . '  <td><strong>#' . $b['id'] . '</strong></td>'
                    . '  <td>' . ($b['type'] === 'chain_break' ? 'Rupture Chaînage' : 'Désalignement Hash') . '</td>'
                    . '  <td style="font-family:monospace; font-size:0.75rem; color:#dc2626;">' . View::e($b['expected_hash'] ?? $b['expected_hash_precedent']) . '</td>'
                    . '  <td style="font-family:monospace; font-size:0.75rem; color:#64748b;">' . View::e($b['actual_hash'] ?? $b['actual_hash_precedent']) . '</td>'
                    . '</tr>';
            }
            $brokenTable = Ui::section(
                'Détail des anomalies détectées',
                '<div class="finea-table-wrapper"><table class="finea-table"><thead><tr>'
                . '<th>ID Audit</th><th>Type d\'anomalie</th><th>Hash Calculé attendu</th><th>Hash stocké BDD</th>'
                . '</tr></thead><tbody>' . $rows . '</tbody></table></div>'
            );
        }

        return '<div class="finea-shell">'
            . '<div class="finea-container">'
            . $header
            . $navTabs
            . $status
            . $summary
            . $brokenTable
            . '</div>'
            . '</div>';
    }
}
