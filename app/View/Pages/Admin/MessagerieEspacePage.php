<?php

declare(strict_types=1);

namespace App\View\Pages\Admin;

/**
 * Ce que l'écran de l'espace de la messagerie a besoin de savoir : l'état de
 * la jauge, tel que MessagerieEspace::etat() le calcule, et les groupes
 * automatiques, tels que GroupesAutomatiques::etat() les décrit.
 */
final class MessagerieEspacePage
{
    /** @var array<string, mixed> */
    public readonly array $etat;

    /** @var array<int, array<string, mixed>> */
    public readonly array $groupes;

    /**
     * @param array<string, mixed> $etat
     * @param array<int, array<string, mixed>> $groupes
     */
    public function __construct(array $etat, array $groupes = [])
    {
        $this->etat = $etat;
        $this->groupes = $groupes;
    }
}
