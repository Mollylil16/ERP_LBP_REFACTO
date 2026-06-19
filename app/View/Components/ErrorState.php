<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use App\View\Pages\Error\ErrorPage;

final class ErrorState
{
    public static function page(ErrorPage $page): string
    {
        return '<section class="error-state error-state--' . View::e($page->tone) . '">'
            . '<div class="error-state__glow error-state__glow--one"></div>'
            . '<div class="error-state__glow error-state__glow--two"></div>'
            . '<article class="error-state__card">'
            . '<div class="error-state__visual">'
            . '<span class="error-state__status">' . $page->statusCode . '</span>'
            . '<div class="error-state__icon" aria-hidden="true">' . self::icon($page->symbol) . '</div>'
            . '<span class="error-state__orbit"></span>'
            . '</div>'
            . '<div class="error-state__content">'
            . '<p class="error-state__eyebrow">' . View::e($page->eyebrow) . '</p>'
            . '<h1>' . View::e($page->title) . '</h1>'
            . '<p class="error-state__message">' . View::e($page->message) . '</p>'
            . '<p class="error-state__detail">' . View::e($page->detail) . '</p>'
            . '<div class="error-state__explanation"><span>Ce que cela signifie</span><p>'
            . View::e($page->explanation) . '</p></div>'
            . '<div class="error-state__guidance"><span>Que faire maintenant ?</span><ul>'
            . self::suggestions($page->suggestions) . '</ul></div>'
            . '<div class="error-state__actions">' . self::actions($page->actions) . '</div>'
            . '</div></article>'
            . '<p class="error-state__signature">ERP LBP · Code ' . $page->statusCode
            . ' · Une explication claire, puis une solution.</p>'
            . '</section>';
    }

    /** @param array<int,string> $suggestions */
    private static function suggestions(array $suggestions): string
    {
        return implode('', array_map(
            static fn(string $suggestion): string => '<li>' . View::e($suggestion) . '</li>',
            $suggestions
        ));
    }

    /** @param array<int,array{label:string,href:string,variant:string}> $actions */
    private static function actions(array $actions): string
    {
        $html = '';
        foreach ($actions as $action) {
            $icon = match ($action['href']) {
                '/', '' => 'home',
                'selection_portail' => 'modules',
                'login' => 'login',
                default => 'arrow',
            };
            $html .= Ui::button($action['label'], [
                'href' => $action['href'],
                'variant' => $action['variant'],
                'class' => 'error-state__action error-state__action--' . $icon,
            ]);
        }

        return $html;
    }

    private static function icon(string $symbol): string
    {
        $icons = [
            'tools' => '<path d="M39 10a15 15 0 0 0-18 19L8 42a7 7 0 0 0 10 10l13-13a15 15 0 0 0 19-18l-9 9-8-2-2-8 8-10Z"/><path d="m38 40 14 14"/>',
            'lock' => '<rect x="14" y="28" width="36" height="27" rx="7"/><path d="M22 28v-8a10 10 0 0 1 20 0v8"/><path d="M32 39v6"/>',
            'shield' => '<path d="M32 7 51 15v14c0 13-8 22-19 28C21 51 13 42 13 29V15l19-8Z"/><path d="m24 32 5 5 11-12"/>',
            'clock' => '<circle cx="32" cy="32" r="23"/><path d="M32 18v15l10 6"/>',
            'document' => '<path d="M18 7h19l10 10v40H18Z"/><path d="M37 7v12h10M25 30h15M25 39h15"/>',
            'server' => '<rect x="10" y="10" width="44" height="18" rx="5"/><rect x="10" y="36" width="44" height="18" rx="5"/><path d="M18 19h.1M18 45h.1M27 19h18M27 45h18"/>',
            'warning' => '<path d="M32 8 57 54H7L32 8Z"/><path d="M32 24v14M32 46h.1"/>',
            'compass' => '<circle cx="32" cy="32" r="22"/><path d="m38 26-5 9-9 5 5-9 9-5Z"/><circle cx="32" cy="32" r="2"/>',
        ];

        return '<svg viewBox="0 0 64 64" role="img">'
            . ($icons[$symbol] ?? $icons['warning']) . '</svg>';
    }
}
