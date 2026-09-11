<?php

declare(strict_types=1);

namespace App\Services\Mobile;

/**
 * Envoi d'une notification vers un abonnement navigateur.
 *
 * Cette abstraction existe pour que la logique de diffusion des alertes puisse être
 * vérifiée sans réseau : le contrat porte sur le résultat, pas sur le transport.
 */
interface EnvoiPushInterface
{
    /**
     * @param array{endpoint: string, p256dh: string, auth: string} $abonnement
     * @param array<string, mixed> $charge
     * @return array{ok: bool, status: int, expire: bool, message: string}
     */
    public function envoyer(array $abonnement, array $charge, int $ttl = 86400): array;
}
