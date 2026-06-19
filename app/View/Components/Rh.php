<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

final class Rh
{
    /** @param array<string,mixed> $restrictedTables */
    public static function restrictedData(array $restrictedTables): string
    {
        if ($restrictedTables === []) {
            return '';
        }

        return '<aside class="rh-restricted-data" role="status">'
            . '<strong>Certaines données sont masquées selon vos habilitations.</strong>'
            . '<span>' . View::e(implode(', ', array_values($restrictedTables))) . '</span></aside>';
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<string,string> $columns
     */
    public static function lifecycleRecords(
        string $title,
        array $rows,
        array $columns,
        callable $date
    ): string {
        foreach ($rows as &$row) {
            foreach ($columns as $key => $_) {
                if (str_ends_with($key, '_date')) {
                    $row[$key] = $date($row[$key] ?? null);
                }
            }
        }
        unset($row);

        return self::card(RecordList::render($rows, $columns, [
            'title_key' => (string) array_key_first($columns),
            'status_key' => 'status',
            'empty' => 'Aucune donnée enregistrée.',
        ]), ['tag' => 'article', 'title' => $title]);
    }

    public static function decisionForm(
        string $action,
        string $csrfToken,
        bool $withComment = false
    ): string {
        $content = Form::hidden('_csrf_token', $csrfToken)
            . ($withComment ? Form::inputControl('comment', ['placeholder' => 'Commentaire']) : '')
            . Ui::button('Valider', [
                'type' => 'submit',
                'name' => 'decision',
                'value' => 'approve',
                'variant' => 'plain',
            ])
            . Ui::button('Refuser', [
                'type' => 'submit',
                'name' => 'decision',
                'value' => 'reject',
                'variant' => 'plain',
            ]);

        return self::form($action, $content, ['class' => 'rh-row-actions']);
    }

    /** @param array<int,array{label:string,href?:string,variant?:string,type?:string}> $actions @return array<int,string> */
    public static function actionButtons(array $actions): array
    {
        return array_map(
            static fn(array $action): string => Ui::button($action['label'], [
                'href' => (string) ($action['href'] ?? ''),
                'variant' => (string) ($action['variant'] ?? 'secondary'),
                'type' => (string) ($action['type'] ?? 'button'),
            ]),
            $actions
        );
    }

    /** @param array<string,mixed> $options */
    public static function pageHeader(string $title, string $subtitle = '', array $options = []): string
    {
        $options['class'] = Html::classes(['rh-hero', (string) ($options['class'] ?? '')]);
        return Ui::pageHeader($title, $subtitle, $options);
    }

    /** @param array<string,mixed> $options */
    public static function card(string $content, array $options = []): string
    {
        $tag = preg_replace('/[^a-z]/i', '', (string) ($options['tag'] ?? 'section')) ?: 'section';
        $class = Html::classes(['finea-section-card', (string) ($options['class'] ?? '')]);
        $heading = self::heading(
            (string) ($options['title'] ?? ''),
            [
                'eyebrow' => (string) ($options['eyebrow'] ?? ''),
                'meta' => (string) ($options['meta'] ?? ''),
                'actions' => $options['actions'] ?? [],
            ]
        );

        return '<' . $tag . ' class="' . View::e($class) . '">' . $heading . $content . '</' . $tag . '>';
    }

    /** @param array<string,mixed> $options */
    public static function heading(string $title, array $options = []): string
    {
        $eyebrow = (string) ($options['eyebrow'] ?? '');
        $meta = (string) ($options['meta'] ?? '');
        $actions = $options['actions'] ?? [];
        $actionsHtml = is_array($actions)
            ? implode('', array_filter($actions, 'is_string'))
            : (is_string($actions) ? $actions : '');

        if ($title === '' && $eyebrow === '' && $meta === '' && $actionsHtml === '') {
            return '';
        }

        return '<div class="rh-section-heading"><div>'
            . ($eyebrow !== '' ? '<p class="rh-eyebrow">' . View::e($eyebrow) . '</p>' : '')
            . ($title !== '' ? '<h2 class="finea-section-title">' . View::e($title) . '</h2>' : '')
            . '</div>'
            . ($meta !== '' ? '<span>' . View::e($meta) . '</span>' : '')
            . $actionsHtml . '</div>';
    }

    /** @param array<string,mixed> $options */
    public static function form(string $action, string $content, array $options = []): string
    {
        $attrs = [
            'method' => (string) ($options['method'] ?? 'post'),
            'action' => View::url(ltrim($action, '/')),
            'class' => Html::classes([(string) ($options['class'] ?? '')]),
        ];
        foreach (['enctype', 'id', 'data-rh-employee-form'] as $attribute) {
            if (array_key_exists($attribute, $options)) {
                $attrs[$attribute] = $options[$attribute];
            }
        }

        return '<form' . Html::attrs($attrs) . '>' . $content . '</form>';
    }

    /** @param array<int,string> $actions */
    public static function formActions(array $actions, string $class = ''): string
    {
        return '<div class="' . View::e(Html::classes(['rh-form-actions', $class])) . '">'
            . implode('', $actions) . '</div>';
    }

    /**
     * @param array<int,array{key:string,label:string,href:string,description?:string,count?:int}> $items
     * @param array<string,mixed> $options
     */
    public static function tabs(array $items, string $activeKey, array $options = []): string
    {
        return Tabs::render($items, $activeKey, [
            'class' => Html::classes(['rh-dashboard-tabs', (string) ($options['class'] ?? '')]),
            'item_class' => 'rh-dashboard-tab',
            'base_item_class' => false,
            'wrap_label' => false,
            'aria-label' => (string) ($options['aria-label'] ?? 'Navigation RH'),
        ]);
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @param callable(array<string,mixed>):array{label:string,value:mixed,description:string,tone?:string} $map
     */
    public static function alerts(array $items, callable $map): string
    {
        if ($items === []) {
            return '';
        }

        $html = '<section class="rh-alert-grid">';
        foreach ($items as $item) {
            $alert = $map($item);
            $html .= '<article class="rh-alert-card tone-'
                . View::e((string) ($alert['tone'] ?? 'warning')) . '"><span>'
                . View::e($alert['label']) . '</span><strong>'
                . View::e((string) $alert['value']) . '</strong><p>'
                . View::e($alert['description']) . '</p></article>';
        }

        return $html . '</section>';
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<int,array{label:string,key?:string,render?:callable}> $columns
     * @param array<string,mixed> $options
     */
    public static function table(array $rows, array $columns, array $options = []): string
    {
        $head = '';
        foreach ($columns as $column) {
            $head .= '<th>' . View::e($column['label']) . '</th>';
        }

        $body = '';
        foreach ($rows as $row) {
            $body .= '<tr>';
            foreach ($columns as $column) {
                if (isset($column['render']) && is_callable($column['render'])) {
                    $value = (string) $column['render']($row);
                } else {
                    $value = View::e((string) ($row[(string) ($column['key'] ?? '')] ?? ''));
                }
                $body .= '<td>' . $value . '</td>';
            }
            $body .= '</tr>';
        }

        if ($rows === []) {
            $body = '<tr><td colspan="' . count($columns) . '">'
                . View::e((string) ($options['empty'] ?? 'Aucune donnee disponible.')) . '</td></tr>';
        }

        $tableClass = Html::classes(['finea-table', (string) ($options['class'] ?? '')]);
        return '<div class="finea-table-wrap"><table class="' . View::e($tableClass) . '"><thead><tr>'
            . $head . '</tr></thead><tbody>' . $body . '</tbody></table></div>';
    }

    /** @param callable(int):string $href */
    public static function pagination(int $currentPage, int $totalPages, callable $href): string
    {
        if ($totalPages <= 1) {
            return '';
        }

        $html = '<nav class="rh-pagination" aria-label="Pagination">';
        for ($page = 1; $page <= $totalPages; $page++) {
            $active = $page === $currentPage;
            $html .= '<a class="' . ($active ? 'is-active' : '') . '" href="'
                . View::e($href($page)) . '"' . ($active ? ' aria-current="page"' : '') . '>'
                . $page . '</a>';
        }

        return $html . '</nav>';
    }

    /** @param array<int,array{number:int,href:string,active:bool}> $links */
    public static function paginationLinks(array $links): string
    {
        if (count($links) <= 1) {
            return '';
        }

        $html = '<nav class="rh-pagination" aria-label="Pagination">';
        foreach ($links as $link) {
            $html .= '<a class="' . ($link['active'] ? 'is-active' : '') . '" href="'
                . View::e($link['href']) . '"' . ($link['active'] ? ' aria-current="page"' : '') . '>'
                . (int) $link['number'] . '</a>';
        }

        return $html . '</nav>';
    }

    /** @param array<string,mixed> $details */
    public static function profileSummary(array $employee, array $details, string $exitText = ''): string
    {
        $active = (int) ($employee['is_active'] ?? 0) === 1;
        $detailHtml = '';
        foreach ($details as $label => $value) {
            $detailHtml .= '<div><small>' . View::e((string) $label) . '</small><strong>'
                . View::e((string) $value) . '</strong></div>';
        }

        return '<section class="rh-profile-summary"><article class="finea-section-card rh-profile-status">'
            . '<span class="finea-status-badge '
            . ($active ? 'finea-status-badge--ok' : 'finea-status-badge--warning') . '">'
            . ($active ? 'En poste' : 'Sorti') . '</span><strong>'
            . View::e((string) ($employee['function_name'] ?? '')) . '</strong><small>'
            . View::e((string) ($employee['status_name'] ?? '')) . '</small>'
            . (!$active && $exitText !== '' ? '<p>' . $exitText . '</p>' : '')
            . '</article><article class="finea-section-card rh-detail-grid">'
            . $detailHtml . '</article></section>';
    }

    /** @param array<int,array<string,mixed>> $events */
    public static function timeline(array $events, callable $date): string
    {
        if ($events === []) {
            return Ui::emptyState('Aucun evenement enregistre.');
        }

        $html = '<div class="finea-timeline">';
        foreach ($events as $event) {
            $description = trim((string) ($event['description'] ?? ''));
            $html .= '<article class="finea-timeline-item"><strong>'
                . View::e((string) ($event['title'] ?? '')) . '</strong><span>'
                . View::e((string) $date($event['event_date'] ?? null)) . ' - '
                . View::e((string) ($event['event_type'] ?? '')) . '</span>'
                . ($description !== '' ? '<p>' . nl2br(View::e($description)) . '</p>' : '')
                . '</article>';
        }

        return $html . '</div>';
    }

    /** @param array<int,array<string,mixed>> $documents */
    public static function documents(array $documents, string $empty = 'Aucune piece jointe enregistree.'): string
    {
        if ($documents === []) {
            return '<div class="finea-empty-state">' . View::e($empty) . '</div>';
        }

        $html = '<div class="rh-document-grid">';
        foreach ($documents as $document) {
            $child = !empty($document['child_index']) ? ' - enfant ' . (int) $document['child_index'] : '';
            $html .= '<a class="rh-document-card" href="'
                . View::url('public/' . ltrim((string) ($document['stored_path'] ?? ''), '/'))
                . '" target="_blank" rel="noopener"><strong>'
                . View::e((string) ($document['original_name'] ?? 'Document')) . '</strong><span>'
                . View::e((string) ($document['document_type'] ?? '') . $child) . '</span></a>';
        }

        return $html . '</div>';
    }
}
