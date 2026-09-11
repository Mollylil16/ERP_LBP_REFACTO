<?php

declare(strict_types=1);

namespace App\Repositories\Mobile;

/**
 * Source des abonnements à notifier et suivi de leur état.
 *
 * Abstraite pour que la diffusion des alertes soit vérifiable sans base de données.
 */
interface AbonnementsPushInterface
{
    /**
     * Abonnements de tous les comptes habilités à recevoir les alertes de direction.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pourDirection(): array;

    /**
     * Retire un abonnement dont le navigateur a signalé qu'il n'existe plus.
     */
    public function supprimer(int $id): void;

    public function marquerSucces(int $id): void;

    public function marquerEchec(int $id): void;
}
