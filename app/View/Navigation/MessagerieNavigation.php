<?php

declare(strict_types=1);

namespace App\View\Navigation;

/**
 * Menu du module Messagerie : les discussions, le classeur des documents
 * (lot 3) et, pour la Direction seule, toutes les discussions.
 */
final class MessagerieNavigation
{
    /** @return array<int,array<string,mixed>> */
    public static function items(): array
    {
        $items = [
            [
                'group' => 'Messagerie',
                'key' => 'discussions',
                'label' => 'Discussions',
                'icon' => 'MSG',
                'url' => 'messagerie',
                'available' => true,
            ],
            [
                'group' => 'Messagerie',
                'key' => 'documents',
                'label' => 'Documents',
                'icon' => 'DOC',
                'url' => 'messagerie/documents',
                'available' => true,
            ],
        ];

        // Absent du menu de tous les autres : l'adresse leur répond 404.
        if (\App\Security\MessagerieAcces::estDirection()) {
            $items[] = [
                'group' => 'Direction',
                'key' => 'direction',
                'label' => 'Toutes les discussions',
                'icon' => 'DIR',
                'url' => 'messagerie/direction',
                'available' => true,
            ];
        }

        return $items;
    }
}
