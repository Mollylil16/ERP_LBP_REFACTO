<?php

declare(strict_types=1);

namespace App\View\Pages\Admin;

/**
 * Ce que l'écran de l'espace de la messagerie a besoin de savoir : l'état de
 * la jauge, tel que MessagerieEspace::etat() le calcule.
 */
final class MessagerieEspacePage
{
    /** @var array<string, mixed> */
    public readonly array $etat;

    /** @param array<string, mixed> $etat */
    public function __construct(array $etat)
    {
        $this->etat = $etat;
    }
}
