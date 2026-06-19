<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

final class Tabs
{
    /**
     * @param array<int,array{key:string,label:string,href:string,description?:string,count?:int}> $items
     * @param array<string,mixed> $attrs
     */
    public static function render(array $items, string $activeKey, array $attrs = []): string
    {
        $class = Html::classes(['finea-tabs', (string) ($attrs['class'] ?? '')]);
        $label = (string) ($attrs['aria-label'] ?? 'Navigation par onglets');
        $baseItemClass = (bool) ($attrs['base_item_class'] ?? true) ? 'finea-tab' : '';
        $itemClass = (string) ($attrs['item_class'] ?? '');
        $wrapLabel = (bool) ($attrs['wrap_label'] ?? true);
        $html = '<nav class="' . View::e($class) . '" aria-label="' . View::e($label) . '">';

        foreach ($items as $item) {
            $isActive = $item['key'] === $activeKey;
            $tabClass = Html::classes([$baseItemClass, $itemClass, 'is-active' => $isActive]);
            $description = (string) ($item['description'] ?? '');
            $count = isset($item['count'])
                ? '<span class="finea-tab-count">' . (int) $item['count'] . '</span>'
                : '';

            $labelHtml = '<strong>' . View::e($item['label']) . '</strong>'
                . ($description !== '' ? '<small>' . View::e($description) . '</small>' : '');

            $html .= '<a class="' . View::e($tabClass) . '" href="' . View::url(ltrim($item['href'], '/')) . '"'
                . ($isActive ? ' aria-current="page"' : '') . '>'
                . ($wrapLabel ? '<span>' . $labelHtml . '</span>' : $labelHtml)
                . $count . '</a>';
        }

        return $html . '</nav>';
    }
}
