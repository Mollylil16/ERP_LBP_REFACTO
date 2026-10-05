<?php

declare(strict_types=1);

namespace App\View\Pages\Admin;

/**
 * Ce que l'écran du journal des comptes a besoin de savoir.
 */
final class JournalPage
{
    /** @var array<int, array<string, mixed>> */
    public readonly array $lignes;

    /** @var array<string, mixed> */
    public readonly array $filtres;

    /** @var array<int, array{id:int, name:string}> */
    public readonly array $comptes;

    /** @var array<int, array{id:int, name:string}> */
    public readonly array $acteurs;

    public readonly string $editePar;

    /**
     * @param array<int, array<string, mixed>> $lignes
     * @param array<string, mixed> $filtres
     * @param array<int, array{id:int, name:string}> $comptes
     * @param array<int, array{id:int, name:string}> $acteurs
     */
    public function __construct(
        array $lignes,
        array $filtres,
        array $comptes = [],
        array $acteurs = [],
        string $editePar = ''
    ) {
        $this->lignes = $lignes;
        $this->filtres = $filtres;
        $this->comptes = $comptes;
        $this->acteurs = $acteurs;
        $this->editePar = $editePar;
    }
}
