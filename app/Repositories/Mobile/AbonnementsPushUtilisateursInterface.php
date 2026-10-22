<?php

declare(strict_types=1);

namespace App\Repositories\Mobile;

/**
 * Abonnements push de n'importe quel utilisateur, et suivi de leur état.
 *
 * AbonnementsPushInterface ne sert que les alertes de la Direction ; celle-ci
 * sert la messagerie (lot 4) et, plus tard, les rappels de tâches : tout
 * utilisateur actif peut y être abonné, sur autant d'appareils qu'il veut.
 */
interface AbonnementsPushUtilisateursInterface
{
    /**
     * Abonnements de ces utilisateurs : id, user_id, endpoint, p256dh, auth.
     *
     * @param array<int, int> $userIds
     * @return array<int, array<string, mixed>>
     */
    public function pourUtilisateurs(array $userIds): array;

    /** Retire un abonnement dont le navigateur a signalé qu'il n'existe plus. */
    public function supprimer(int $id): void;

    public function marquerSucces(int $id): void;

    public function marquerEchec(int $id): void;
}
