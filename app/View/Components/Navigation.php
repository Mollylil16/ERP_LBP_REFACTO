<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use InvalidArgumentException;

final class Navigation
{
    /**
     * Règle commune :
     * - navigation groupée par domaine, avec un sous-niveau optionnel (subgroup) ;
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

        foreach ($groups as $group => $subgroups) {
            $groupId = 'nav-group-' . substr(sha1($group), 0, 10);
            $containsActive = self::containsActiveInSubgroups($subgroups, $activeKey);

            $html .= '<section class="module-nav-group' . ($containsActive ? ' has-active' : '') . '" data-nav-group>'
                . '<button class="module-nav-group-title" type="button" aria-expanded="true" aria-controls="' . $groupId . '" data-nav-group-toggle>'
                . '<span>' . View::e($group) . '</span>' . self::chevron() . '</button>'
                . '<div class="module-nav-group-items" id="' . $groupId . '">';

            foreach ($subgroups as $subgroup => $links) {
                if ($subgroup === '') {
                    $html .= self::renderLinks($links, $activeKey);
                    continue;
                }

                $subId = $groupId . '-' . substr(sha1($subgroup), 0, 10);
                $subActive = self::containsActiveInLinks($links, $activeKey);
                $html .= '<section class="module-nav-group module-nav-subgroup' . ($subActive ? ' has-active' : '') . '" data-nav-group>'
                    . '<button class="module-nav-group-title module-nav-subgroup-title" type="button" aria-expanded="true" aria-controls="' . $subId . '" data-nav-group-toggle>'
                    . '<span>' . View::e($subgroup) . '</span>' . self::chevron() . '</button>'
                    . '<div class="module-nav-group-items" id="' . $subId . '">'
                    . self::renderLinks($links, $activeKey)
                    . '</div></section>';
            }

            $html .= '</div></section>';
        }

        return $html . '</nav></div>';
    }

    /**
     * Regroupe les items par domaine (group), puis par sous-domaine optionnel (subgroup).
     * La clé de sous-groupe vide ('') désigne les liens directs du groupe (sans sous-menu).
     *
     * @param array<int,array<string,mixed>> $items
     * @return array<string,array<string,array<int,array<string,mixed>>>>
     */
    public static function groups(array $items): array
    {
        $groups = [];
        $keys = [];
        foreach ($items as $index => $item) {
            $key = trim((string) ($item['key'] ?? ''));
            $label = trim((string) ($item['label'] ?? ''));
            $group = trim((string) ($item['group'] ?? self::defaultGroup($key))) ?: 'Général';
            $subgroup = trim((string) ($item['subgroup'] ?? ''));
            if ($key === '' || $label === '') throw new InvalidArgumentException("Navigation invalide à l’index {$index}.");
            if (isset($keys[$key])) throw new InvalidArgumentException("Clé de navigation dupliquée : {$key}.");
            if (($item['available'] ?? true) && trim((string) ($item['url'] ?? '')) === '') {
                throw new InvalidArgumentException("URL absente pour la navigation : {$key}.");
            }
            $keys[$key] = true;
            $groups[$group][$subgroup][] = $item;
        }
        return $groups;
    }

    /** @param array<int,array<string,mixed>> $links */
    private static function renderLinks(array $links, string $activeKey): string
    {
        $html = '';
        foreach ($links as $item) {
            $available = (bool) ($item['available'] ?? true);
            $active = ($item['key'] ?? '') === $activeKey;
            $class = Html::classes(['module-nav-link', 'is-active' => $active, 'is-disabled' => !$available]);
            $href = $available ? View::url(ltrim((string) $item['url'], '/')) : '#';
            $html .= '<a class="' . View::e($class) . '" href="' . View::e($href) . '"'
                . ($active ? ' aria-current="page"' : '')
                . (!$available ? ' aria-disabled="true" data-coming-soon' : '') . '>'
                . '<span class="module-nav-icon">' . ($item['icon'] ?? '•') . '</span>'
                . '<span class="module-nav-label">' . View::e((string) $item['label']) . '</span>'
                . (!$available ? '<small>Bientôt</small>' : '') . '</a>';
        }
        return $html;
    }

    /** @param array<string,array<int,array<string,mixed>>> $subgroups */
    private static function containsActiveInSubgroups(array $subgroups, string $activeKey): bool
    {
        foreach ($subgroups as $links) {
            if (self::containsActiveInLinks($links, $activeKey)) {
                return true;
            }
        }
        return false;
    }

    /** @param array<int,array<string,mixed>> $links */
    private static function containsActiveInLinks(array $links, string $activeKey): bool
    {
        foreach ($links as $link) {
            if (($link['key'] ?? '') === $activeKey) {
                return true;
            }
        }
        return false;
    }

    private static function chevron(): string
    {
        return '<span aria-hidden="true">⌄</span>';
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
