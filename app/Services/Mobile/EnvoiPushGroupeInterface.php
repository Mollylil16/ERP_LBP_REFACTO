<?php

declare(strict_types=1);

namespace App\Services\Mobile;

/**
 * Envoi de plusieurs notifications d'un coup.
 *
 * Interface à part plutôt qu'une méthode de plus dans EnvoiPushInterface :
 * les doublures des tests existants n'ont pas à l'écrire. NotificationsPush
 * envoie en parallèle quand le transport le sait, une à une sinon.
 */
interface EnvoiPushGroupeInterface extends EnvoiPushInterface
{
    /**
     * @param array<int|string, array{0: array{endpoint: string, p256dh: string, auth: string}, 1: array<string, mixed>}> $envois
     * @return array<int|string, array{ok: bool, status: int, expire: bool, message: string}> mêmes clés que $envois
     */
    public function envoyerTous(array $envois, int $ttl = 86400): array;
}
