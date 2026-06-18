<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use InvalidArgumentException;

final class Navigation
{
    /**
     * Règle commune :
     * - navigation groupée par domaine ;
     * - clés uniques ;
     * - un lien disponible possède une URL ;
     * - Retour au portail reste hors de la zone défilante.
     *
     * @param array<int,array<string,mixed>> $items
     */
    public static function module(array $items, string $activeKey, array $options = []): string
    {
        $groups = self::groups($items);
        $label = (string) ($options['aria-label'] ?? 'Navigation du module');
        $html = '<div class="module-navigation-scroll" data-module-navigation-scroll>'
            . '<nav class="module-navigation" aria-label="' . View::e($label) . '">';

        foreach ($groups as $group => $links) {
            $groupId = 'nav-group-' . substr(sha1($group), 0, 10);
            $containsActive = false;
            foreach ($links as $link) {
                if (($link['key'] ?? '') === $activeKey) {
                    $containsActive = true;
                    break;
                }
            }
            $html .= '<section class="module-nav-group' . ($containsActive ? ' has-active' : '') . '" data-nav-group>'
                . '<button class="module-nav-group-title" type="button" aria-expanded="true" aria-controls="' . $groupId . '" data-nav-group-toggle>'
                . '<span>' . View::e($group) . '</span><span aria-hidden="true">⌄</span></button>'
                . '<div class="module-nav-group-items" id="' . $groupId . '">';

            foreach ($links as $item) {
                $available = (bool) ($item['available'] ?? true);
                $active = ($item['key'] ?? '') === $activeKey;
                $class = Html::classes(['module-nav-link', 'is-active' => $active, 'is-disabled' => !$available]);
                $href = $available ? View::url(ltrim((string) $item['url'], '/')) : '#';
                $html .= '<a class="' . View::e($class) . '" href="' . View::e($href) . '"'
                    . ($active ? ' aria-current="page"' : '')
                    . (!$available ? ' aria-disabled="true" data-coming-soon' : '') . '>'
                    . '<span class="module-nav-icon">' . View::e((string) ($item['icon'] ?? '•')) . '</span>'
                    . '<span class="module-nav-label">' . View::e((string) $item['label']) . '</span>'
                    . (!$available ? '<small>Bientôt</small>' : '') . '</a>';
            }

            $html .= '</div></section>';
        }

        return $html . '</nav></div>';
    }

    /** @param array<int,array<string,mixed>> $items @return array<string,array<int,array<string,mixed>>> */
    public static function groups(array $items): array
    {
        $groups = [];
        $keys = [];
        foreach ($items as $index => $item) {
            $key = trim((string) ($item['key'] ?? ''));
            $label = trim((string) ($item['label'] ?? ''));
            $group = trim((string) ($item['group'] ?? self::defaultGroup($key))) ?: 'Général';
            if ($key === '' || $label === '') throw new InvalidArgumentException("Navigation invalide à l’index {$index}.");
            if (isset($keys[$key])) throw new InvalidArgumentException("Clé de navigation dupliquée : {$key}.");
            if (($item['available'] ?? true) && trim((string) ($item['url'] ?? '')) === '') {
                throw new InvalidArgumentException("URL absente pour la navigation : {$key}.");
            }
            $keys[$key] = true;
            $groups[$group][] = $item;
        }
        return $groups;
    }

    private static function defaultGroup(string $key): string
    {
        return match ($key) {
            'dashboard' => 'Pilotage',
            'operations', 'documents' => 'Activité',
            'reporting' => 'Analyse',
            'settings' => 'Configuration',
            default => 'Général',
        };
    }
}
