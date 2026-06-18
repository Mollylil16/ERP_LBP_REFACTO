<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

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
}
