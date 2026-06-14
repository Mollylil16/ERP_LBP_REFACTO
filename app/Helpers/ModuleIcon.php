<?php

namespace App\Helpers;

final class ModuleIcon
{
    public static function svg(string $name): string
    {
        $icons = [
            'finance' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V9.5L12 5l8 4.5V19"/><path d="M8 19v-6h8v6"/><path d="M9 10h6"/><path d="M12 13v6"/></svg>',
            'rh' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 19v-1.5a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4V19"/><circle cx="10" cy="7.5" r="3"/><path d="M20 19v-1.2a3.2 3.2 0 0 0-2.4-3.1"/><path d="M15.5 4.8a3 3 0 0 1 0 5.4"/></svg>',
            'colisage' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 8.5 12 4l8 4.5-8 4.5-8-4.5Z"/><path d="M4 8.5v7L12 20l8-4.5v-7"/><path d="M12 13v7"/><path d="m8.2 6.2 8 4.5"/></svg>',
            'logistique' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7h11v9H3z"/><path d="M14 10h4l3 3v3h-7"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/><path d="M6 11h5"/></svg>',
            'admin' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l7 3v5c0 4.5-2.8 8.2-7 10-4.2-1.8-7-5.5-7-10V6l7-3Z"/><path d="M9.5 12.2 11.2 14l3.6-4"/></svg>',
            'employee' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/><path d="M4 21a8 8 0 0 1 16 0"/><path d="M9 18h6"/></svg>',
            'crm' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19v-7a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4v7"/><circle cx="9" cy="8" r="3"/><path d="M15 11h3"/><path d="M15 15h3"/><path d="M7 19v-2a3 3 0 0 1 6 0v2"/></svg>',
            'tickets' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v10H8l-4 4V5Z"/><path d="M8 9h8"/><path d="M8 12h5"/></svg>',
            'website' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18"/><path d="M12 3a14 14 0 0 0 0 18"/></svg>',
        ];

        return $icons[$name] ?? $icons['admin'];
    }
}
