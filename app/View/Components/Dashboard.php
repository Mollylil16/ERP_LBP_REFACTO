<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use App\Helpers\ModuleIcon;

final class Dashboard
{
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

    /** @param array<int,array{label:string,hint:string,url:string}> $actions */
    public static function actions(array $actions): string
    {
        $html = '<div class="module-action-list">';
        foreach ($actions as $action) {
            $html .= '<a href="' . View::url(ltrim($action['url'], '/')) . '"><strong>' . View::e($action['label'])
                . '</strong><span>' . View::e($action['hint']) . '</span><small>Ouvrir</small></a>';
        }
        return $html . '</div>';
    }

    /** @param array<int,array{label:string,count:mixed,description:string,tone?:string,href:string}> $items */
    public static function alerts(array $items): string
    {
        $html = '<section class="rh-alert-grid" aria-label="Alertes opérationnelles">';
        foreach ($items as $item) {
            $html .= '<a class="rh-alert-card tone-' . View::e((string) ($item['tone'] ?? 'info'))
                . '" href="' . View::url(ltrim($item['href'], '/')) . '" aria-label="' . View::e('Ouvrir : ' . $item['label']) . '">'
                . '<span>' . View::e($item['label']) . '</span><strong>' . View::e((string) $item['count'])
                . '</strong><p>' . View::e($item['description']) . '</p></a>';
        }
        return $html . '</section>';
    }

    /**
     * @param array<int,array{key:string,label:string,href:string,description?:string,count?:int}> $items
     */
    public static function tabs(array $items, string $activeKey): string
    {
        return Tabs::render($items, $activeKey, [
            'class' => 'rh-dashboard-tabs',
            'item_class' => 'rh-dashboard-tab',
            'base_item_class' => false,
            'wrap_label' => false,
            'aria-label' => 'Vues du tableau de bord',
        ]);
    }

    /**
     * @param array<int,array{label:mixed,total:mixed}> $rows
     * @param array<int,array{label:string,href:string,hint?:string}> $actions
     */
    public static function distributionWithActions(array $rows, int $total, array $actions): string
    {
        $distribution = '<section class="finea-section-card"><div class="rh-section-heading"><div>'
            . '<p class="rh-eyebrow">Repartition</p>'
            . '<h2 class="finea-section-title">Services les plus representes</h2>'
            . '</div><span>' . $total . ' collaborateurs</span></div>';

        if ($rows === []) {
            $distribution .= '<div class="finea-empty-state">Les repartitions apparaitront apres l\'integration du personnel.</div>';
        } else {
            $distribution .= '<div class="rh-bars">';
            foreach ($rows as $row) {
                $count = (int) ($row['total'] ?? 0);
                $width = min(100, ($count / max(1, $total)) * 100);
                $distribution .= '<div class="rh-bar-row"><div><span>'
                    . View::e((string) ($row['label'] ?? ''))
                    . '</span><strong>' . $count . '</strong></div>'
                    . '<div class="rh-bar"><span style="width: ' . View::e((string) $width) . '%"></span></div></div>';
            }
            $distribution .= '</div>';
        }

        $quickLinks = '<aside class="rh-quick-card"><p class="rh-eyebrow">Acces rapides</p>'
            . '<h2>Operations RH</h2><div class="rh-quick-list">';
        foreach ($actions as $action) {
            $quickLinks .= '<a href="' . View::url(ltrim($action['href'], '/')) . '">'
                . View::e($action['label']) . '<small>'
                . View::e((string) ($action['hint'] ?? 'Ouvrir')) . '</small></a>';
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
        $content = $rows === []
            ? '<div class="finea-empty-state">'
                . View::e((string) ($options['empty'] ?? 'Aucune donnee disponible.')) . '</div>'
            : RecordList::render($rows, $fields, $options);

        return '<section class="finea-section-card rh-recent-section">'
            . '<div class="rh-section-heading"><div><p class="rh-eyebrow">'
            . View::e((string) ($options['eyebrow'] ?? 'Elements recents'))
            . '</p><h2 class="finea-section-title">'
            . View::e((string) ($options['title'] ?? 'Derniers elements'))
            . '</h2></div></div>' . $content . '</section>';
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

}
