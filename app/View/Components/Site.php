<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

final class Site
{
    public static function icon(string $name): string
    {
        $icons = [
            'customs' => '<svg viewBox="0 0 24 24"><path d="M5 4h14v5c0 5.5-3 9-7 11-4-2-7-5.5-7-11V4Z"/><path d="M8 11h8M12 7v8"/></svg>',
            'freight' => '<svg viewBox="0 0 24 24"><path d="M3 16h18M6 16V8l6-3 6 3v8"/><path d="M8 16v3M16 16v3M9 10h6"/></svg>',
            'tracking' => '<svg viewBox="0 0 24 24"><path d="M12 21s7-5.1 7-12A7 7 0 1 0 5 9c0 6.9 7 12 7 12Z"/><circle cx="12" cy="9" r="2.4"/></svg>',
            'delivery' => '<svg viewBox="0 0 24 24"><path d="M3 7h11v10H3zM14 11h4l3 3v3h-7z"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/></svg>',
        ];
        return $icons[$name] ?? $icons['tracking'];
    }

    /** @param array<int,array<string,string>> $stats */
    public static function stats(array $stats): string
    {
        $html = '<section class="site-stats" aria-label="Indicateurs LBP Transit">';
        foreach ($stats as $stat) {
            $html .= '<article><strong>' . View::e((string) ($stat['value'] ?? ''))
                . '</strong><span>' . View::e((string) ($stat['label'] ?? '')) . '</span></article>';
        }
        return $html . '</section>';
    }

    /** @param array<int,array<string,mixed>> $services */
    public static function services(array $services): string
    {
        $html = '<section class="site-grid site-grid--four">';
        foreach ($services as $service) {
            $html .= '<article class="site-service-card"><span class="site-service-card__icon">'
                . self::icon((string) ($service['icon'] ?? 'tracking')) . '</span><h3>'
                . View::e((string) ($service['title'] ?? '')) . '</h3><p>'
                . View::e((string) ($service['text'] ?? '')) . '</p></article>';
        }
        return $html . '</section>';
    }
}
