<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use App\Helpers\ModuleIcon;

final class Dashboard
{
    /** @param array<string,mixed> $attrs */
    public static function header(string $title, string $subtitle = '', array $attrs = []): string
    {
        return \App\View\Components\Ui::pageHeader($title, $subtitle, $attrs);
    }

    /** @param array<int,array{label:mixed,value:mixed,meta?:mixed,tone?:string}> $items */
    public static function kpis(array $items, array $attrs = []): string
    {
        $class = Html::classes(['finea-grid', 'finea-kpi-grid', (string) ($attrs['class'] ?? '')]);
        $html = '<section class="' . View::e($class) . '" aria-label="' . View::e((string) ($attrs['aria-label'] ?? 'Indicateurs clés')) . '">';
        foreach ($items as $item) $html .= self::kpi($item);
        return $html . '</section>';
    }

    /** @param array{label:mixed,value:mixed,meta?:mixed,tone?:string,href?:string} $item */
    public static function kpi(array $item): string
    {
        $href = trim((string) ($item['href'] ?? ''));
        $class = Html::classes(['finea-kpi-card', 'is-clickable' => $href !== '', isset($item['tone']) ? 'tone-' . $item['tone'] : '']);
        $tag = $href !== '' ? 'a' : 'article';
        $attributes = $href !== ''
            ? ' href="' . View::url(ltrim($href, '/')) . '" aria-label="' . View::e('Ouvrir : ' . (string) $item['label']) . '"'
            : '';
        return '<' . $tag . ' class="' . View::e($class) . '"' . $attributes . '><span class="finea-kpi-label">'
            . View::e((string) $item['label']) . '</span><strong class="finea-kpi-value">'
            . View::e((string) $item['value']) . '</strong><small class="finea-kpi-meta">'
            . View::e((string) ($item['meta'] ?? '')) . '</small>'
            . ($href !== '' ? '<span class="finea-kpi-arrow" aria-hidden="true">→</span>' : '')
            . '</' . $tag . '>';
    }

    /** @param array<int,array{label:string,hint?:string,url?:string,href?:string}> $actions */
    public static function actions(array $actions): string
    {
        $html = '<div class="module-action-list">';
        foreach ($actions as $action) {
            $urlPath = $action['url'] ?? $action['href'] ?? '';
            $hint = $action['hint'] ?? '';
            $html .= '<a href="' . View::url(ltrim((string) $urlPath, '/')) . '"><strong>' . View::e($action['label'])
                . '</strong><span>' . View::e((string) $hint) . '</span><small>Ouvrir</small></a>';
        }
        return $html . '</div>';
    }

    /** @param array<int,array{label:string,count:mixed,description:string,tone?:string,href:string}> $items */
    public static function alerts(array $items): string
    {
        $html = '<section class="rh-alert-grid" aria-label="Alertes opérationnelles">';
        
        $icons = [
            'success' => '<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect><path d="M9 12l2 2 4-4"></path></svg>',
            'warning' => '<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>',
            'danger' => '<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>',
            'pink' => '<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>',
            'info' => '<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>',
        ];

        foreach ($items as $item) {
            $tone = View::e((string) ($item['tone'] ?? 'info'));
            $icon = $icons[$tone] ?? $icons['info'];
            $label = View::e(mb_strtoupper($item['label']));
            
            $html .= '<a class="rh-alert-card tone-' . $tone . '" href="' . View::url(ltrim($item['href'], '/')) . '" aria-label="' . View::e('Ouvrir : ' . $item['label']) . '">'
                . '<div class="rh-alert-card-header">'
                . '<span class="rh-alert-card-icon">' . $icon . '</span>'
                . '<span class="rh-alert-card-badge">' . View::e((string) $item['count']) . '</span>'
                . '</div>'
                . '<div class="rh-alert-card-title-wrapper">'
                . '<span class="rh-alert-card-title">' . $label . '</span>'
                . '<span class="rh-alert-card-arrow">→</span>'
                . '</div>'
                . '<p class="rh-alert-card-description">' . View::e($item['description']) . '</p>'
                . '</a>';
        }
        return $html . '</section>';
    }

    /**
     * @param array<int,array{key:string,label:string,href:string,description?:string,count?:int}> $items
     */
    public static function tabs(array $items, string $activeKey): string
    {
        $html = '<nav class="rh-dashboard-tabs" aria-label="Vues du tableau de bord">';
        
        $icons = [
            'classic' => '<svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path></svg>',
            'statistique' => '<svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M18 20V10M12 20V4M6 20v-6"></path></svg>',
            'analytique' => '<svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="9" y1="15" x2="15" y2="15"></line><line x1="9" y1="19" x2="15" y2="19"></line><path d="M9 11h1"></path></svg>',
        ];

        foreach ($items as $item) {
            $isActive = $item['key'] === $activeKey;
            $class = 'rh-dashboard-tab' . ($isActive ? ' is-active' : '');
            $iconHtml = '<span class="rh-dashboard-tab-icon is-' . View::e($item['key']) . '">' . ($icons[$item['key']] ?? '') . '</span>';
            
            $label = $item['label'];
            if ($item['key'] === 'classic') {
                $label = 'Tableau de bord classique';
            } elseif ($item['key'] === 'statistique') {
                $label = 'Tableau de bord statistique';
            } elseif ($item['key'] === 'analytique') {
                $label = 'Tableau de bord analytique';
            }

            $description = $item['description'] ?? '';
            if ($item['key'] === 'classic') {
                $description = 'Acces rapides, alertes et suivi courant RH.';
            } elseif ($item['key'] === 'statistique') {
                $description = 'Graphes assiduite, demandes, taches et heures sup.';
            } elseif ($item['key'] === 'analytique') {
                $description = 'Analyse complete et exports de rapports RH.';
            }

            $html .= '<a class="' . $class . '" href="' . View::url(ltrim($item['href'], '/')) . '"'
                . ($isActive ? ' aria-current="page"' : '') . '>'
                . $iconHtml
                . '<span class="rh-dashboard-tab-content">'
                . '<strong>' . View::e($label) . '</strong>'
                . '<small>' . View::e($description) . '</small>'
                . '</span>'
                . '</a>';
        }

        $html .= '</nav>';
        return $html;
    }

    /**
     * @param array<int,array{label:mixed,total:mixed}> $rows
     * @param array<int,array{label:string,href:string,hint?:string,tone?:string,count?:int,count_tone?:string}> $actions
     */
    public static function distributionWithActions(array $rows, int $total, array $actions): string
    {
        $distribution = '<section class="finea-section-card"><div class="rh-section-heading"><div>'
            . '<p class="rh-eyebrow">Repartition</p>'
            . '<h2 class="finea-section-title">Services les plus representes</h2>'
            . '</div><a class="rh-priorities-link" href="' . View::url('rh/personnel') . '">Voir toute la liste</a></div>';

        if ($rows === []) {
            $distribution .= '<div class="finea-empty-state">Les repartitions apparaitront apres l\'integration du personnel.</div>';
        } else {
            $distribution .= '<div class="rh-bars">';
            foreach ($rows as $row) {
                $count = (int) ($row['total'] ?? 0);
                $width = min(100, ($count / max(1, $total)) * 100);
                $distribution .= '<div class="rh-bar-row"><div><span class="rh-bar-label">'
                    . View::e((string) ($row['label'] ?? ''))
                    . '</span><span class="rh-bar-badge">' . $count . ' pers.</span></div>'
                    . '<div class="rh-bar"><span style="width: ' . View::e((string) $width) . '%"></span></div></div>';
            }
            $distribution .= '</div>';
        }

        $quickLinks = '<aside class="rh-quick-card"><p class="rh-eyebrow">Acces rapides</p>'
            . '<h2>Operations RH</h2><div class="rh-quick-list">';
        foreach ($actions as $action) {
            $toneClass = isset($action['tone']) ? ' quick-item--' . $action['tone'] : '';
            $badgeHtml = '';
            if (isset($action['count']) && $action['count'] > 0) {
                $badgeTone = $action['count_tone'] ?? 'default';
                $badgeHtml = '<span class="quick-item-badge is-' . $badgeTone . '">' . (int) $action['count'] . '</span>';
            }
            
            $quickLinks .= '<a class="quick-item' . $toneClass . '" href="' . View::url(ltrim($action['href'], '/')) . '">'
                . '<div class="quick-item-header">'
                . '<span class="quick-item-title">' . View::e($action['label']) . '</span>'
                . $badgeHtml
                . '</div>'
                . '<small>' . View::e((string) ($action['hint'] ?? 'Ouvrir')) . '</small></a>';
        }

        return '<div class="rh-content-grid">' . $distribution . '</section>'
            . $quickLinks . '</div></aside></div>';
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<string,string> $fields
     * @param array<string,mixed> $options
     */
    public static function recentRecords(array $rows, array $fields, array $options = []): string
    {
        $eyebrow = (string) ($options['eyebrow'] ?? 'Elements recents');
        $title = (string) ($options['title'] ?? 'Derniers elements');
        $emptyMsg = (string) ($options['empty'] ?? 'Aucune donnee disponible.');

        $html = '<section class="finea-section-card rh-recent-section">'
            . '<div class="rh-section-heading"><div>'
            . '<p class="rh-eyebrow">' . View::e($eyebrow) . '</p>'
            . '<h2 class="finea-section-title">' . View::e($title) . '</h2>'
            . '</div><a class="rh-priorities-link" href="' . View::url('rh/personnel') . '">Consulter tous les profils</a></div>';

        if ($rows === []) {
            $html .= '<div class="finea-empty-state">' . View::e($emptyMsg) . '</div>';
        } else {
            $html .= '<div class="rh-recent-table-wrapper">';
            $html .= '<table class="rh-recent-table">';
            
            $columnOrder = ['employee_number', 'full_name', 'service_name', 'function_name', 'hire_date', 'status'];
            
            $html .= '<thead><tr>';
            foreach ($columnOrder as $key) {
                if (isset($fields[$key])) {
                    $html .= '<th>' . View::e($fields[$key]) . '</th>';
                }
            }
            $html .= '</tr></thead>';
            
            $html .= '<tbody>';
            foreach ($rows as $row) {
                $html .= '<tr>';
                foreach ($columnOrder as $key) {
                    if (!isset($fields[$key])) continue;
                    $val = (string) ($row[$key] ?? '');
                    if ($key === 'status') {
                        $html .= '<td>' . Ui::badge($val, 'neutral', ['class' => 'rh-status-badge-table']) . '</td>';
                    } elseif ($key === 'employee_number') {
                        $html .= '<td><strong>' . View::e($val) . '</strong></td>';
                    } else {
                        $html .= '<td>' . View::e($val) . '</td>';
                    }
                }
                $html .= '</tr>';
            }
            $html .= '<tbody>';
            $html .= '</table>';
            $html .= '</div>';
        }

        $html .= '</section>';
        return $html;
    }

    /**
     * @param array<int,array{label:mixed,total:mixed}> $functions
     * @param array<int,array{label:mixed,total:mixed}> $statuses
     */
    public static function classicRankings(array $functions, array $statuses): string
    {
        $functionsHtml = '<section class="finea-section-card rh-classic-rankings-panel">'
            . '<p class="rh-eyebrow" style="color: #6366f1;">Fonctions</p>'
            . '<h2 class="finea-section-title">Fonctions dominantes</h2>';
        
        if ($functions === []) {
            $functionsHtml .= '<div class="finea-empty-state">Aucune fonction enregistree.</div>';
        } else {
            $functionsHtml .= '<div class="rh-functions-grid">';
            foreach ($functions as $row) {
                $functionsHtml .= '<div class="rh-function-card">'
                    . '<small>' . View::e((string) ($row['label'] ?? '')) . '</small>'
                    . '<strong>' . (int) ($row['total'] ?? 0) . '</strong>'
                    . '</div>';
            }
            $functionsHtml .= '</div>';
        }
        $functionsHtml .= '</section>';

        $statusesHtml = '<section class="finea-section-card rh-classic-rankings-panel">'
            . '<p class="rh-eyebrow" style="color: #f59e0b;">Statuts</p>'
            . '<h2 class="finea-section-title">Repartition des statuts</h2>';
        
        if ($statuses === []) {
            $statusesHtml .= '<div class="finea-empty-state">Aucun statut enregistre.</div>';
        } else {
            $statusesHtml .= '<div class="rh-statuses-list">';
            foreach ($statuses as $row) {
                $statusesHtml .= '<div class="rh-status-row">'
                    . '<span>' . View::e((string) ($row['label'] ?? '')) . '</span>'
                    . '<span class="rh-status-row-badge">' . (int) ($row['total'] ?? 0) . '</span>'
                    . '</div>';
            }
            $statusesHtml .= '</div>';
        }
        $statusesHtml .= '</section>';

        return '<div class="rh-classic-rankings-grid">' . $functionsHtml . $statusesHtml . '</div>';
    }

    /** @param array<int,array{eyebrow:string,value:mixed,description:string}> $items */
    public static function metrics(array $items): string
    {
        $html = '<section class="rh-analytics-grid">';
        foreach ($items as $item) {
            $html .= '<article class="finea-section-card rh-metric-panel"><p class="rh-eyebrow">'
                . View::e($item['eyebrow']) . '</p><strong>'
                . View::e((string) $item['value']) . '</strong><span>'
                . View::e($item['description']) . '</span></article>';
        }

        return $html . '</section>';
    }

    /** @param array<int,array{title:string,rows:array<int,array{label:mixed,total:mixed}>}> $groups */
    public static function rankings(array $groups, string $empty = 'Aucune donnee disponible.'): string
    {
        $html = '<div class="rh-three-columns">';
        foreach ($groups as $group) {
            $html .= '<section class="finea-section-card"><h2 class="finea-section-title">'
                . View::e($group['title']) . '</h2>';
            if ($group['rows'] === []) {
                $html .= '<div class="finea-empty-state">' . View::e($empty) . '</div>';
            } else {
                $html .= '<div class="rh-ranking">';
                foreach ($group['rows'] as $row) {
                    $html .= '<div><span>' . View::e((string) ($row['label'] ?? ''))
                        . '</span><strong>' . (int) ($row['total'] ?? 0) . '</strong></div>';
                }
                $html .= '</div>';
            }
            $html .= '</section>';
        }

        return $html . '</div>';
    }

    public static function analyticIntro(
        string $eyebrow,
        string $title,
        string $description,
        string $status,
        string $tone = 'ok'
    ): string {
        return '<section class="rh-analytic-hero finea-section-card"><div><p class="rh-eyebrow">'
            . View::e($eyebrow) . '</p><h2>' . View::e($title) . '</h2><p>'
            . View::e($description) . '</p></div><span class="finea-status-badge finea-status-badge--'
            . View::e($tone) . '">' . View::e($status) . '</span></section>';
    }

    /**
     * @param array<int,array{title:string,description:string,action:string,button?:array<string,mixed>}> $reports
     */
    public static function reports(array $reports): string
    {
        $html = '<section class="rh-report-grid">';
        foreach ($reports as $report) {
            $html .= '<article class="finea-section-card"><h2 class="finea-section-title">'
                . View::e($report['title']) . '</h2><p>'
                . View::e($report['description']) . '</p>'
                . Ui::button($report['action'], (array) ($report['button'] ?? [])) . '</article>';
        }

        return $html . '</section>';
    }

    /** @param array<string,mixed> $module */
    public static function businessModuleDashboard(array $module): string
    {
        $module['kpis'] = array_map(
            static fn(array $kpi): array => $kpi + ['href' => '/' . (string) $module['slug'] . '/dashboard#operations'],
            (array) ($module['kpis'] ?? [])
        );

        $workflowHtml = '';
        foreach ((array) ($module['workflow'] ?? []) as $step) {
            $workflowHtml .= '<article><strong>' . View::e((string) ($step['title'] ?? '')) . '</strong><p>'
                . View::e((string) ($step['text'] ?? '')) . '</p></article>';
        }

        $style = '--module-hero-gradient: ' . (string) ($module['gradient'] ?? 'linear-gradient(135deg, #1d2b57, #2563eb)') . ';';
        $icon = ModuleIcon::svg((string) ($module['iconKey'] ?? 'dashboard'));

        $workflowSection = $workflowHtml !== '' && ($module['showWorkflow'] ?? true)
            ? '<section class="finea-section-card"><div class="module-section-heading"><div><p class="finea-eyebrow">Backend</p><h2 class="finea-section-title">Structure prévue pour l’évolution métier</h2></div></div><div class="module-workflow-grid">'
                . $workflowHtml . '</div></section>'
            : '';

        return '<div class="finea-shell module-dashboard-shell"><div class="finea-container">'
            . Ui::pageHeader(
                (string) ($module['label'] ?? 'Module'),
                (string) ($module['description'] ?? ''),
                [
                    'class' => 'module-dashboard-hero',
                    'style' => $style,
                    'eyebrow' => View::e((string) ($module['code'] ?? 'ERP')) . ' • Module métier',
                    'icon' => '<span class="module-dashboard-icon">' . $icon . '</span>',
                    'badge' => '<span class="module-dashboard-chip">Dashboard prêt</span>',
                    'actions' => Ui::button('Changer de module', ['href' => 'selection_portail', 'variant' => 'accent']),
                ]
            )
            . self::kpis((array) $module['kpis'], ['class' => 'module-dashboard-kpis'])
            . '<div class="module-dashboard-grid" id="operations"><section class="finea-section-card">'
            . '<div class="module-section-heading"><div><p class="finea-eyebrow">Accès rapides</p><h2 class="finea-section-title">Opérations du module</h2></div><span class="finea-status-badge finea-status-badge--info">Socle clean code</span></div>'
            . self::actions((array) ($module['actions'] ?? []))
            . '</section><aside class="finea-section-card module-identity-card"><span class="module-dashboard-icon large">' . $icon . '</span><h2>'
            . View::e((string) ($module['label'] ?? 'Module'))
            . '</h2><p>Les couleurs, l’icône et le code reprennent le point d’entrée du portail pour identifier immédiatement l’espace courant.</p>'
            . '<div class="module-identity-swatches"><span style="background: ' . View::e((string) ($module['accent2'] ?? '#1d2b57')) . '"></span><span style="background: '
            . View::e((string) ($module['accent'] ?? '#2563eb')) . '"></span><span style="background: var(--finea-gold)"></span></div></aside></div>'
            . $workflowSection . '</div></div>';
    }
    /**
     * Renders the 13 quick-action pill buttons shown in the dashboard header.
     *
     * @param array<int,array{label:string,href:string,icon:string,variant?:string,count?:int}> $actions
     */
    public static function quickActions(array $actions): string
    {
        $html = '<div class="rh-quick-actions">';
        foreach ($actions as $action) {
            $variant = (string) ($action['variant'] ?? 'secondary');
            $class = 'rh-quick-action-btn rh-quick-action-btn--' . preg_replace('/[^a-z0-9_-]/i', '', $variant);
            $count = (int) ($action['count'] ?? 0);
            $badge = $count > 0 ? '<span class="rh-quick-action-badge">' . $count . '</span>' : '';
            $html .= '<a class="' . View::e($class) . '" href="' . View::url(ltrim($action['href'], '/'))
                . '"><span class="rh-quick-action-icon">' . ($action['icon'] ?? '')
                . '</span>' . View::e($action['label']) . $badge . '</a>';
        }

        return $html . '</div>';
    }

    /**
     * Renders the "Priorités RH — Dernières demandes à valider" section.
     *
     * @param array<int,array{type:string,employee:string,status:string,date:string}> $requests
     */
    public static function priorities(array $requests, string $allHref = 'rh/validations'): string
    {
        $html = '<section class="finea-section-card rh-priorities-section">'
            . '<div class="rh-section-heading"><div>'
            . '<p class="rh-eyebrow">Priorites RH</p>'
            . '<h2 class="finea-section-title">Dernieres demandes a valider</h2>'
            . '</div><a class="rh-priorities-link" href="' . View::url(ltrim($allHref, '/'))
            . '">Tout traiter →</a></div>';

        if ($requests === []) {
            $html .= '<div class="finea-empty-state">Aucune demande en attente de validation.</div>';
        } else {
            $html .= '<div class="rh-priorities-grid">';
            foreach ($requests as $request) {
                $html .= '<article class="rh-priority-card">'
                    . '<strong>' . View::e($request['type']) . '</strong>'
                    . '<span>' . View::e($request['employee']) . '</span>'
                    . '<div class="rh-priority-footer">'
                    . '<span class="finea-status-badge finea-status-badge--warning">' . View::e($request['status']) . '</span>'
                    . '<time>' . View::e($request['date']) . '</time>'
                    . '</div></article>';
            }
            $html .= '</div>';
        }

        return $html . '</section>';
    }

    public static function statIntro(): string
    {
        return '<div class="finea-section-card rh-stat-intro-card">'
            . '  <div class="rh-stat-intro-flex">'
            . '    <div>'
            . '      <p class="rh-eyebrow">TABLEAU DE BORD STATISTIQUE</p>'
            . '      <h2 class="finea-section-title" style="font-size: 1.5rem; margin: 4px 0 8px 0; color: #1e293b;">Lecture opérationnelle RH du mois</h2>'
            . '      <p style="color: #64748b; font-size: 0.88rem; line-height: 1.5; margin: 0; max-width: 850px;">'
            . '        Assiduite, demandes traitees, emploi du temps, taches et heures supplementaires sont consolides pour aider les RH a voir vite les points d attention.'
            . '      </p>'
            . '    </div>'
            . '    <div class="rh-stat-intro-pills">'
            . '      <a href="' . View::url('rh/pointage') . '" class="stat-header-pill pill-green">Pointage</a>'
            . '      <a href="' . View::url('rh/validations') . '" class="stat-header-pill pill-blue">Demandes RH</a>'
            . '      <a href="' . View::url('rh/parametrage') . '" class="stat-header-pill pill-orange">Horaires</a>'
            . '    </div>'
            . '  </div>'
            . '</div>';
    }

    /** @param array<string,mixed> $analytics */
    public static function statKPIs(array $analytics): string
    {
        $presenceRate = number_format((float) ($analytics['presenceRate'] ?? 0), 1, ',', ' ');
        $attendanceRows = (int) ($analytics['attendanceRows'] ?? 0);
        
        $lateRows = (int) ($analytics['lateRows'] ?? 0);
        $lateRate = $attendanceRows > 0 ? round(($lateRows / $attendanceRows) * 100, 1) : 0.0;
        $lateRateFormatted = number_format($lateRate, 1, ',', ' ');

        $requestsProcessed = (int) ($analytics['requestsProcessed'] ?? 0);
        $requestsApproved = (int) ($analytics['requestsApproved'] ?? 0);
        $requestsRejected = (int) ($analytics['requestsRejected'] ?? 0);

        $overtimeHours = number_format((float) ($analytics['overtimeHours'] ?? 0), 1, ',', ' ');
        $overtimeEmployees = (int) ($analytics['overtimeEmployees'] ?? 0);

        $items = [
            [
                'title' => 'Taux de presence',
                'value' => $presenceRate . '%',
                'subtext' => $attendanceRows . ' pointages presents / missions / conges',
                'tone' => 'success',
                'icon' => '<svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><polyline points="16 11 18 13 22 9"></polyline></svg>'
            ],
            [
                'title' => 'Retards detectes',
                'value' => $lateRows,
                'subtext' => $lateRateFormatted . '% des lignes de pointage du mois',
                'tone' => 'danger',
                'icon' => '<svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>'
            ],
            [
                'title' => 'Demandes traitees',
                'value' => $requestsProcessed,
                'subtext' => $requestsApproved . ' validees, ' . $requestsRejected . ' refusees',
                'tone' => 'pink',
                'icon' => '<svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><polyline points="9 11 11 13 15 9"></polyline></svg>'
            ],
            [
                'title' => 'Emploi du temps',
                'value' => '0',
                'subtext' => '0,0 h planifiees ou renseignees',
                'tone' => 'warning',
                'icon' => '<svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>'
            ],
            [
                'title' => 'Heures sup',
                'value' => $overtimeHours,
                'subtext' => $overtimeEmployees . ' collaborateur(s) concernes',
                'tone' => 'info',
                'icon' => '<svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>'
            ]
        ];

        $html = '<section class="rh-stat-kpis">';
        foreach ($items as $item) {
            $html .= '<article class="rh-stat-kpi-card tone-' . $item['tone'] . '">'
                . '<div class="rh-stat-kpi-header">'
                . '<span class="rh-stat-kpi-title">' . View::e((string) $item['title']) . '</span>'
                . '<span class="rh-stat-kpi-icon">' . $item['icon'] . '</span>'
                . '</div>'
                . '<strong class="rh-stat-kpi-value">' . View::e((string) $item['value']) . '</strong>'
                . '<span class="rh-stat-kpi-subtext">' . View::e((string) $item['subtext']) . '</span>'
                . '</article>';
        }
        $html .= '</section>';

        return $html;
    }

    /**
     * @param array<string,array{presence:int,absence:int,retard:int}> $dailyStats
     */
    public static function dailyAttendanceChart(array $dailyStats): string
    {
        $year = (int) date('Y');
        $month = (int) date('m');
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        
        $left = '<section class="finea-section-card rh-daily-chart-card">'
            . '<div class="rh-section-heading"><div>'
            . '<h2 class="finea-section-title" style="font-size: 1.15rem; color: #1e2b57;">Assiduite sur les derniers jours</h2>'
            . '<p style="color: #64748b; font-size: 0.8rem; margin: 4px 0 0 0;">Presence, absences et retards issus du pointage journalier.</p>'
            . '</div><a class="rh-priorities-link" href="' . View::url('rh/pointage') . '">Corriger</a></div>';

        $left .= '<div class="rh-daily-chart-grid">';
        
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $label = sprintf('%02d/%02d', $day, $month);
            
            $stats = $dailyStats[$dateStr] ?? ['presence' => 0, 'absence' => 0, 'retard' => 0];
            
            $hasPresence = $stats['presence'] > 0;
            $hasAbsence = $stats['absence'] > 0;
            $hasRetard = $stats['retard'] > 0;

            // Mock visualization logic to display color tags on seeded WAMP days matching mockup visual details
            // If completely empty database (which is true in local WAMP but not in prod), we display subtle indicator dots
            if ($stats['presence'] === 0 && $stats['absence'] === 0 && $stats['retard'] === 0) {
                // Keep clean gray caps to reflect true database state without static mockup
                $hasPresence = false;
                $hasAbsence = false;
                $hasRetard = false;
            }

            $left .= '<div class="rh-daily-chart-day">'
                . '<div class="rh-daily-chart-caps">'
                . '<span class="rh-daily-cap cap-presence' . ($hasPresence ? ' is-active' : '') . '"></span>'
                . '<span class="rh-daily-cap cap-absence' . ($hasAbsence ? ' is-active' : '') . '"></span>'
                . '<span class="rh-daily-cap cap-retard' . ($hasRetard ? ' is-active' : '') . '"></span>'
                . '</div>'
                . '<span class="rh-daily-chart-label">' . $label . '</span>'
                . '</div>';
        }
        
        $left .= '</div>';
        
        $left .= '<div class="rh-daily-chart-legend">'
            . '<span><span class="legend-dot dot-presence"></span>Presence</span>'
            . '<span><span class="legend-dot dot-absence"></span>Absence / maladie</span>'
            . '<span><span class="legend-dot dot-retard"></span>Retard</span>'
            . '</div>';
            
        $left .= '</section>';

        $right = '<section class="finea-section-card rh-pointage-repartition-card">'
            . '<h2 class="finea-section-title" style="font-size: 1.15rem; color: #1e2b57;">Repartition du pointage</h2>'
            . '<p style="color: #64748b; font-size: 0.8rem; margin: 4px 0 0 0;">Lecture rapide des statuts saisis ce mois.</p>'
            . '<div class="rh-stat-col-empty" style="margin-top: 48px;">Aucun statut de pointage a afficher.</div>'
            . '</section>';

        return '<div class="rh-daily-charts-split">' . $left . $right . '</div>';
    }

    public static function statThreeColumns(): string
    {
        $cols = [
            [
                'title' => 'Demandes RH traitees',
                'desc' => 'Validation, refus et annulations sur le mois.',
                'empty' => 'Aucune demande RH sur la periode.'
            ],
            [
                'title' => 'Types les plus traites',
                'desc' => 'Ce graphe montre ce qui mobilise le plus les RH.',
                'empty' => 'Aucun traitement finalise sur le mois.'
            ],
            [
                'title' => 'Heures supplementaires',
                'desc' => 'Collaborateurs les plus concernes par les surcharges horaires.',
                'empty' => 'Aucune heure supplementaire renseigne.'
            ]
        ];

        $html = '<div class="rh-stat-three-cols">';
        foreach ($cols as $col) {
            $html .= '<article class="finea-section-card rh-stat-col-card">'
                . '<h3 class="rh-stat-col-title">' . View::e($col['title']) . '</h3>'
                . '<p class="rh-stat-col-desc">' . View::e($col['desc']) . '</p>'
                . '<div class="rh-stat-col-empty">' . View::e($col['empty']) . '</div>'
                . '</article>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * @param array{events?:int,employees?:int,individual?:int,service?:int} $tasks
     */
    public static function statTachesEtCharge(array $tasks = []): string
    {
        $events = (int) ($tasks['events'] ?? 0);
        $employees = (int) ($tasks['employees'] ?? 0);
        $individual = (int) ($tasks['individual'] ?? 0);
        $service = (int) ($tasks['service'] ?? 0);

        $left = '<section class="finea-section-card rh-stat-tasks-card">'
            . '<h3 class="rh-stat-col-title" style="color: #ffffff;">Taches et emploi du temps</h3>'
            . '<p class="rh-stat-col-desc" style="color: #94a3b8; margin-bottom: 20px;">Evenements planifies, statuts et parametrage horaires.</p>'
            . '<div class="rh-stat-tasks-grid">'
            . '  <div class="rh-stat-task-block"><strong>' . $events . '</strong><span>evenements renseignes</span></div>'
            . '  <div class="rh-stat-task-block"><strong>' . $employees . '</strong><span>collaborateurs planifies</span></div>'
            . '  <div class="rh-stat-task-block"><strong>' . $individual . '</strong><span>horaires individuels</span></div>'
            . '  <div class="rh-stat-task-block"><strong>' . $service . '</strong><span>horaires par service</span></div>'
            . '</div>'
            . '</section>';

        $right = '<section class="finea-section-card rh-stat-load-card">'
            . '<h3 class="rh-stat-col-title">Charge planifiee par collaborateur</h3>'
            . '<p class="rh-stat-col-desc">Aide a detecter les agendas trop charges avant incident.</p>'
            . '<div class="rh-stat-col-empty" style="margin-top: 48px;">Aucune charge planifiee sur le mois.</div>'
            . '</section>';

        return '<div class="rh-stat-taches-charge-grid">' . $left . $right . '</div>';
    }

    /**
     * @param array<int,array{month:string,presenceRate:float,overtimeHours:float}> $trends
     */
    public static function monthlyTrendChart(array $trends): string
    {
        $html = '<section class="finea-section-card rh-trend-card" style="margin-top: 18px;">'
            . '<div class="rh-section-heading"><div>'
            . '<p class="rh-eyebrow">Tendance mensuelle</p>'
            . '<h2 class="finea-section-title" style="font-size: 1.15rem; color: #1e2b57;">Tendance mensuelle</h2>'
            . '<p style="color: #64748b; font-size: 0.8rem; margin: 4px 0 0 0;">Presence et heures supplementaires sur six mois.</p>'
            . '</div></div>';

        $html .= '<div class="rh-trend-months-grid">';
        foreach ($trends as $trend) {
            $presence = (float) $trend['presenceRate'];
            $overtime = (float) $trend['overtimeHours'];
            
            $presenceHeight = min(100, max(4, $presence));
            $overtimeHeight = min(100, max(4, ($overtime / 50) * 100)); // scale by max 50h

            $html .= '<div class="rh-trend-month-item">'
                . '  <span class="rh-trend-month-label">' . View::e($trend['month']) . '</span>'
                . '  <div class="rh-trend-bars-container">'
                . '    <div class="rh-trend-bar-col"><span class="trend-bar bar-presence" style="height: ' . $presenceHeight . '%;"></span></div>'
                . '    <div class="rh-trend-bar-col"><span class="trend-bar bar-overtime" style="height: ' . $overtimeHeight . '%;"></span></div>'
                . '  </div>'
                . '  <div class="rh-trend-month-values">'
                . '    <span>' . number_format($presence, 1, ',', ' ') . '% presence</span>'
                . '    <span>' . number_format($overtime, 1, ',', ' ') . ' h sup</span>'
                . '  </div>'
                . '</div>';
        }
        $html .= '</div>';
        $html .= '</section>';

        return $html;
    }

    /**
     * @param array<int,array{label:mixed,total:mixed}> $services
     * @param array<int,array{label:mixed,total:mixed}> $statuses
     * @param array<int,array{id:int,full_name:string,employee_number:string|null}> $employees
     */
    public static function analyticDashboard(array $services, array $statuses, array $employees): string
    {
        $header = '<div class="finea-section-card rh-stat-intro-card">'
            . '  <div class="rh-stat-intro-flex">'
            . '    <div>'
            . '      <p class="rh-eyebrow">TABLEAU DE BORD ANALYTIQUE</p>'
            . '      <h2 class="finea-section-title" style="font-size: 1.5rem; margin: 4px 0 8px 0; color: #1e293b;">Rapports, analyses et commentaires RH</h2>'
            . '      <p style="color: #64748b; font-size: 0.88rem; line-height: 1.5; margin: 0; max-width: 850px;">'
            . '        Generez des rapports complets et consolidez les commentaires RH pour les points mensuels, annuels ou periodiques.'
            . '      </p>'
            . '    </div>'
            . '    <div class="rh-stat-intro-pills">'
            . '      <a href="' . View::url('rh/pointage') . '" class="stat-header-pill pill-green">Pointage</a>'
            . '      <a href="' . View::url('rh/validations') . '" class="stat-header-pill pill-blue">Demandes RH</a>'
            . '      <a href="' . View::url('rh/parametrage') . '" class="stat-header-pill pill-orange">Horaires</a>'
            . '    </div>'
            . '  </div>'
            . '</div>';

        $serviceOptions = '<option value="all">Tous</option>';
        foreach ($services as $srv) {
            $lbl = (string) ($srv['label'] ?? '');
            if ($lbl !== '') {
                $serviceOptions .= '<option value="' . View::e($lbl) . '">' . View::e($lbl) . '</option>';
            }
        }

        $statusOptions = '<option value="all">Tous</option>';
        foreach ($statuses as $stat) {
            $lbl = (string) ($stat['label'] ?? '');
            if ($lbl !== '') {
                $statusOptions .= '<option value="' . View::e($lbl) . '">' . View::e($lbl) . '</option>';
            }
        }

        $employeeOptions = '<option value="all">Toutes</option>';
        foreach ($employees as $emp) {
            $num = $emp['employee_number'] ? '[' . $emp['employee_number'] . '] ' : '';
            $employeeOptions .= '<option value="' . (int) $emp['id'] . '">' . View::e($num . $emp['full_name']) . '</option>';
        }

        $currentDate = date('d/m/Y');
        $months = [
            1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
            5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
            9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre'
        ];
        $currentMonth = $months[(int)date('m')] . ' ' . date('Y');
        $currentYear = date('Y');
        $monthStart = date('01/m/Y');
        $monthEnd = date('t/m/Y');

        $form = '<section class="finea-section-card rh-analytic-exporter-card" style="margin-top: 18px;">'
            . '  <div class="rh-analytic-header-flex">'
            . '    <div>'
            . '      <h3 class="rh-stat-col-title" style="font-size: 1.15rem; color: #1e2b57; margin-bottom: 4px !important;">Exporter un rapport RH complet</h3>'
            . '      <p class="rh-stat-col-desc" style="margin-bottom: 0;">Rapport journalier, mensuel, annuel ou periode libre avec commentaires RH integres au PDF.</p>'
            . '    </div>'
            . '    <button type="button" class="rh-visualize-report-btn" onclick="prepareAndPrintReport()">'
            . '      <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round" class="rh-visualize-icon"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>'
            . '      Visualiser le rapport'
            . '    </button>'
            . '  </div>'
            . '  <form class="rh-analytic-filter-form">'
            . '    <div class="rh-analytic-form-grid">'
            . '      <div class="rh-analytic-field">'
            . '        <label>Perimetre</label>'
            . '        <select name="perimetre"><option value="general">General</option><option value="service">Service</option><option value="collaborateur">Collaborateur</option></select>'
            . '      </div>'
            . '      <div class="rh-analytic-field">'
            . '        <label>Type de rapport</label>'
            . '        <select name="type_rapport"><option value="journalier">Journalier</option><option value="mensuel" selected>Mensuel</option><option value="annuel">Annuel</option><option value="libre">Libre</option></select>'
            . '      </div>'
            . '      <div class="rh-analytic-field">'
            . '        <label>Jour</label>'
            . '        <input type="text" name="jour" value="' . $currentDate . '">'
            . '      </div>'
            . '      <div class="rh-analytic-field">'
            . '        <label>Mois</label>'
            . '        <input type="text" name="mois" value="' . $currentMonth . '">'
            . '      </div>'
            . '      <div class="rh-analytic-field">'
            . '        <label>Annee</label>'
            . '        <input type="text" name="annee" value="' . $currentYear . '">'
            . '      </div>'
            . '      <div class="rh-analytic-field">'
            . '        <label>Personne</label>'
            . '        <select name="personne">' . $employeeOptions . '</select>'
            . '      </div>'
            . '      <div class="rh-analytic-field">'
            . '        <label>Service</label>'
            . '        <select name="service">' . $serviceOptions . '</select>'
            . '      </div>'
            . '      <div class="rh-analytic-field">'
            . '        <label>Statut</label>'
            . '        <select name="statut">' . $statusOptions . '</select>'
            . '      </div>'
            . '      <div class="rh-analytic-field">'
            . '        <label>Debut</label>'
            . '        <input type="text" name="debut" value="' . $monthStart . '">'
            . '      </div>'
            . '      <div class="rh-analytic-field">'
            . '        <label>Fin</label>'
            . '        <input type="text" name="fin" value="' . $monthEnd . '">'
            . '      </div>'
            . '    </div>'
            . '    <div class="rh-analytic-textareas-grid">'
            . '      <div class="rh-analytic-textarea-field">'
            . '        <label>Synthese RH</label>'
            . '        <textarea name="synthese_rh" placeholder="Conclusion generale, climat social, faits marquants..."></textarea>'
            . '      </div>'
            . '      <div class="rh-analytic-textarea-field">'
            . '        <label>Points d\'attention</label>'
            . '        <textarea name="points_attention" placeholder="Retards recurrents, absences, surcharge, contrats a regulariser..."></textarea>'
            . '      </div>'
            . '      <div class="rh-analytic-textarea-field">'
            . '        <label>Actions prevues</label>'
            . '        <textarea name="actions_prevues" placeholder="Relances, corrections de pointage, entretiens, actions RH..."></textarea>'
            . '      </div>'
            . '    </div>'
            . '  </form>'
            . '</section>'
            . '<script>'
            . 'function prepareAndPrintReport() {'
            . '  const form = document.querySelector(".rh-analytic-filter-form");'
            . '  if (!form) { window.print(); return; }'
            . '  const formData = new FormData(form);'
            . '  const perimetreSelect = form.querySelector("select[name=\'perimetre\']");'
            . '  const perimetreText = perimetreSelect ? perimetreSelect.options[perimetreSelect.selectedIndex].text : "";'
            . '  const typeSelect = form.querySelector("select[name=\'type_rapport\']");'
            . '  const typeText = typeSelect ? typeSelect.options[typeSelect.selectedIndex].text : "";'
            . '  const jour = formData.get("jour") || "";'
            . '  const mois = formData.get("mois") || "";'
            . '  const annee = formData.get("annee") || "";'
            . '  const personneSelect = form.querySelector("select[name=\'personne\']");'
            . '  const personneText = personneSelect ? personneSelect.options[personneSelect.selectedIndex].text : "";'
            . '  const serviceSelect = form.querySelector("select[name=\'service\']");'
            . '  const serviceText = serviceSelect ? serviceSelect.options[serviceSelect.selectedIndex].text : "";'
            . '  const statutSelect = form.querySelector("select[name=\'statut\']");'
            . '  const statutText = statutSelect ? statutSelect.options[statutSelect.selectedIndex].text : "";'
            . '  const debut = formData.get("debut") || "";'
            . '  const fin = formData.get("fin") || "";'
            . '  const synthese = formData.get("synthese_rh") || form.querySelector("textarea[name=\'synthese_rh\']").getAttribute("placeholder") || "";'
            . '  const points = formData.get("points_attention") || form.querySelector("textarea[name=\'points_attention\']").getAttribute("placeholder") || "";'
            . '  const actions = formData.get("actions_prevues") || form.querySelector("textarea[name=\'actions_prevues\']").getAttribute("placeholder") || "";'
            . '  let printBlock = document.getElementById("rh-report-print-template");'
            . '  if (!printBlock) {'
            . '    printBlock = document.createElement("div");'
            . '    printBlock.id = "rh-report-print-template";'
            . '    printBlock.className = "rh-print-report-container";'
            . '    document.body.appendChild(printBlock);'
            . '  }'
            . '  let periodDetails = "";'
            . '  if (typeSelect && typeSelect.value === "journalier") { periodDetails = "Journée du " + jour; }'
            . '  else if (typeSelect && typeSelect.value === "mensuel") { periodDetails = "Mois de " + mois; }'
            . '  else if (typeSelect && typeSelect.value === "annuel") { periodDetails = "Année " + annee; }'
            . '  else { periodDetails = "Période du " + debut + " au " + fin; }'
            . '  printBlock.innerHTML = `'
            . '    <div class="rh-print-report-header">'
            . '      <div class="logo-area">'
            . '        <span class="logo-symbol">LBP</span>'
            . '        <span class="logo-title">LA BELLE PORTE</span>'
            . '      </div>'
            . '      <div class="report-meta">'
            . '        <h2>RAPPORT ANALYTIQUE RH</h2>'
            . '        <div class="report-date-badge">${periodDetails}</div>'
            . '      </div>'
            . '    </div>'
            . '    <div class="rh-print-report-meta-grid">'
            . '      <div class="meta-item"><span class="meta-label">Périmètre :</span><span class="meta-value">${perimetreText}</span></div>'
            . '      <div class="meta-item"><span class="meta-label">Type :</span><span class="meta-value">${typeText}</span></div>'
            . '      <div class="meta-item"><span class="meta-label">Collaborateur(s) :</span><span class="meta-value">${personneText}</span></div>'
            . '      <div class="meta-item"><span class="meta-label">Service :</span><span class="meta-value">${serviceText}</span></div>'
            . '      <div class="meta-item"><span class="meta-label">Statut ciblé :</span><span class="meta-value">${statutText}</span></div>'
            . '      <div class="meta-item"><span class="meta-label">Date d\\\'export :</span><span class="meta-value">${new Date().toLocaleDateString("fr-FR")}</span></div>'
            . '    </div>'
            . '    <div class="rh-print-report-section">'
            . '      <h3>I. Synthèse & Climat Social</h3>'
            . '      <div class="rh-print-report-content">${synthese.replace(/\\n/g, "<br>")}</div>'
            . '    </div>'
            . '    <div class="rh-print-report-section">'
            . '      <h3>II. Points de Vigilance et d\\\'Attention</h3>'
            . '      <div class="rh-print-report-content">${points.replace(/\\n/g, "<br>")}</div>'
            . '    </div>'
            . '    <div class="rh-print-report-section">'
            . '      <h3>III. Actions RH Planifiées</h3>'
            . '      <div class="rh-print-report-content">${actions.replace(/\\n/g, "<br>")}</div>'
            . '    </div>'
            . '    <div class="rh-print-report-footer">'
            . '      <p>Document généré par le portail ERP LBP - Confidentialité stricte.</p>'
            . '      <div class="signature-line">'
            . '        <p>Signataire RH</p>'
            . '        <div class="sig-space"></div>'
            . '      </div>'
            . '    </div>'
            . '  `;'
            . '  window.print();'
            . '}'
            . '</script>';

        return $header . $form;
    }
}
