<?php

declare(strict_types=1);

namespace App\Services\Colisage;

use InvalidArgumentException;

/**
 * Saisie refusée : porte la liste complète des corrections à faire, pour que
 * l'agent les voie toutes d'un coup plutôt qu'une par tentative.
 */
final class DossierEnvoiInvalide extends InvalidArgumentException
{
    /**
     * @param array<int, string> $erreurs
     */
    public function __construct(public readonly array $erreurs)
    {
        parent::__construct(implode(' ', $erreurs));
    }
}
