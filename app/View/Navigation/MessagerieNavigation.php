<?php

declare(strict_types=1);

namespace App\View\Navigation;

/**
 * Menu du module Messagerie. Un seul écran au lot 1 ; les suivants
 * (fichiers, favoris, tâches) s'ajouteront ici.
 */
final class MessagerieNavigation
{
    /** @return array<int,array<string,mixed>> */
    public static function items(): array
    {
        return [
            [
                'group' => 'Messagerie',
                'key' => 'discussions',
                'label' => 'Discussions',
                'icon' => 'MSG',
                'url' => 'messagerie',
                'available' => true,
            ],
        ];
    }
}
