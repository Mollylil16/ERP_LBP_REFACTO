<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use App\Helpers\Csrf;

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

    /** @param array<int,object|array<string,mixed>> $entities */
    public static function entityList(array $entities): string
    {
        if ($entities === []) return Ui::emptyState('Aucune entité sécurisée.');
        $html = '<div class="admin-entity-list">';
        foreach ($entities as $entity) {
            $module = is_array($entity) ? ($entity['module'] ?? '') : ($entity->module ?? '');
            $name = is_array($entity) ? ($entity['name'] ?? '') : ($entity->name ?? '');
            $description = is_array($entity) ? ($entity['description'] ?? '') : ($entity->description ?? '');
            $html .= '<div><span class="admin-module-chip">' . View::e((string) $module) . '</span><span><strong>'
                . View::e((string) $name) . '</strong><small>' . View::e((string) $description) . '</small></span></div>';
        }
        return $html . '</div>';
    }

    /** @param array<int,array{label:string,href:string}> $links */
    public static function infoCard(string $eyebrow, string $title, string $text, array $links = [], array $attrs = []): string
    {
        $class = Html::classes([(string) ($attrs['class'] ?? 'finea-section-card')]);
        $html = '<aside class="' . View::e($class) . '"><p class="admin-eyebrow">' . View::e($eyebrow) . '</p><h2>' . View::e($title)
            . '</h2><p>' . View::e($text) . '</p>';
        foreach ($links as $link) $html .= '<a href="' . View::url(ltrim($link['href'], '/')) . '">' . View::e($link['label']) . '</a>';
        return $html . '</aside>';
    }

    /** @param array<int,array<string,mixed>> $rows */
    public static function attendanceList(array $rows, callable $dateFormatter): string
    {
        if ($rows === []) return Ui::emptyState('Aucun pointage disponible pour ce mois.');
        $html = '<div class="employee-attendance-list">';
        foreach ($rows as $row) {
            $html .= '<article><time>' . View::e((string) $dateFormatter((string) ($row['attendance_date'] ?? ''))) . '</time><strong>'
                . View::e((string) ($row['attendance_status'] ?? '—')) . '</strong><span>'
                . View::e(substr((string) ($row['check_in_time'] ?? ''), 0, 5) ?: '—') . ' → '
                . View::e(substr((string) ($row['check_out_time'] ?? ''), 0, 5) ?: '—') . '</span><small>'
                . View::e(number_format((float) ($row['worked_hours'] ?? 0), 1, ',', ' ')) . ' h</small></article>';
        }
        return $html . '</div>';
    }

    /** @param array<int,array<string,mixed>> $documents */
    public static function documentGrid(array $documents): string
    {
        if ($documents === []) return '<div class="employee-document-grid">' . Ui::emptyState('Aucun document disponible.') . '</div>';
        $html = '<div class="employee-document-grid">';
        foreach ($documents as $document) {
            $html .= '<a href="' . View::url('public/' . ltrim((string) ($document['stored_path'] ?? ''), '/')) . '" target="_blank" rel="noopener"><strong>'
                . View::e((string) ($document['original_name'] ?? 'Document')) . '</strong><small>'
                . View::e((string) ($document['document_type'] ?? '')) . '</small></a>';
        }
        return $html . '</div>';
    }

    /** @param array<int,array<string,mixed>> $steps */
    public static function workflow(array $steps): string
    {
        if ($steps === []) return Ui::emptyState('Aucune étape configurée.');
        $html = '<div class="module-workflow-grid">';
        foreach ($steps as $step) $html .= '<article><strong>' . View::e((string) ($step['title'] ?? 'Étape')) . '</strong><p>' . View::e((string) ($step['text'] ?? '')) . '</p></article>';
        return $html . '</div>';
    }

    /** @param array<int,array{label:string,total:int|float|string}> $rows */
    public static function ranking(array $rows): string
    {
        if ($rows === []) return Ui::emptyState('Aucune donnée disponible.');
        $html = '<div class="rh-ranking">';
        foreach ($rows as $row) $html .= '<div><span>' . View::e((string) $row['label']) . '</span><strong>' . View::e((string) $row['total']) . '</strong></div>';
        return $html . '</div>';
    }

    /** @param array<int,array{label:string,total:int|float|string}> $rows */
    public static function bars(array $rows, int|float $total): string
    {
        if ($rows === []) return Ui::emptyState('Les répartitions apparaîtront après l’intégration du personnel.');
        $html = '<div class="rh-bars">';
        foreach ($rows as $row) {
            $width = min(100, ((int) $row['total'] / max(1, (int) $total)) * 100);
            $html .= '<div class="rh-bar-row"><div><span>' . View::e((string) $row['label']) . '</span><strong>' . View::e((string) $row['total'])
                . '</strong></div><div class="rh-bar"><span style="width: ' . View::e((string) $width) . '%"></span></div></div>';
        }
        return $html . '</div>';
    }


    /** @param array<int,array<string,mixed>> $rows */
    public static function explanationList(array $rows, callable $dateFormatter): string
    {
        if ($rows === []) return '<div class="employee-explanation-list">' . Ui::emptyState('Aucune demande d’explication.') . '</div>';
        $html = '<div class="employee-explanation-list">';
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $status = (string) ($row['status'] ?? 'pending_response');
            $html .= '<article><header><strong>' . View::e((string) ($row['subject'] ?? 'Demande d’explication')) . '</strong><span class="employee-status status-'
                . View::e($status) . '">' . View::e($status) . '</span></header><p>' . View::e((string) ($row['facts'] ?? ''))
                . '</p><small>Réponse attendue avant le ' . View::e((string) $dateFormatter((string) ($row['response_due_date'] ?? ''))) . '</small>';
            if (in_array($status, ['pending_response', 'complement_requested'], true)) {
                $form = '<form method="post" action="' . View::url('espace-employe/explications/' . $id . '/repondre') . '">'
                    . Form::hidden('_csrf_token', Csrf::token())
                    . Form::textarea('response', 'Votre réponse circonstanciée', '', ['rows' => 7, 'minlength' => 20, 'required' => true])
                    . Ui::button('Transmettre ma réponse', ['variant' => 'accent', 'type' => 'submit']) . '</form>';
                $html .= Modal::render('explanation-' . $id, 'Répondre à la demande d’explication', $form, 'Répondre', ['eyebrow' => 'Droit de réponse']);
            } elseif (!empty($row['employee_response'])) {
                $html .= '<blockquote>' . View::e((string) $row['employee_response']) . '</blockquote>';
            }
            $html .= '</article>';
        }
        return $html . '</div>';
    }

    /** @param array<int,array{label:string,value:mixed,meta:string}> $metrics */
    public static function metricPanels(array $metrics): string
    {
        $html = '<section class="rh-analytics-grid">';
        foreach ($metrics as $metric) {
            $html .= '<article class="finea-section-card rh-metric-panel"><p class="rh-eyebrow">' . View::e($metric['label']) . '</p><strong>'
                . View::e((string) $metric['value']) . '</strong><span>' . View::e($metric['meta']) . '</span></article>';
        }
        return $html . '</section>';
    }

    /** @param array<int,array{title:string,text:string,button?:string,href?:string}> $reports */
    public static function reportCards(array $reports): string
    {
        $html = '<section class="rh-report-grid">';
        foreach ($reports as $report) {
            $html .= '<article class="finea-section-card"><h2 class="finea-section-title">' . View::e($report['title']) . '</h2><p>' . View::e($report['text']) . '</p>';
            if (!empty($report['button'])) $html .= Ui::button($report['button'], ['href' => (string) ($report['href'] ?? ''), 'variant' => 'secondary', 'type' => 'button']);
            $html .= '</article>';
        }
        return $html . '</section>';
    }

}
