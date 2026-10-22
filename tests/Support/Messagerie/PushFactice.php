<?php

declare(strict_types=1);

namespace Tests\Support\Messagerie;

use App\Repositories\Mobile\AbonnementsPushUtilisateursInterface;
use App\Services\Mobile\EnvoiPushInterface;

/**
 * Service de push simulé et abonnements en mémoire, réunis : les tests
 * déclarent des appareils, lisent ce qui est « parti » et ce que le suivi
 * des abonnements en a retenu. Aucun réseau.
 */
final class PushFactice implements EnvoiPushInterface, AbonnementsPushUtilisateursInterface
{
    /** @var array<int, array<string, mixed>> par identifiant d'abonnement */
    public array $abonnements = [];

    /** @var array<int, array{endpoint: string, charge: array<string, mixed>, ttl: int}> */
    public array $envoyes = [];

    /** @var array<string, int> statut HTTP rendu par endpoint (201 par défaut) */
    public array $statuts = [];

    /** @var array<int, int> */
    public array $succes = [];

    /** @var array<int, int> */
    public array $echecs = [];

    private int $prochainId = 1;

    public function appareil(int $userId, string $endpoint): int
    {
        $id = $this->prochainId++;
        $this->abonnements[$id] = ['id' => $id, 'user_id' => $userId, 'endpoint' => $endpoint, 'p256dh' => 'p', 'auth' => 'a'];

        return $id;
    }

    /** @return array<int, array<string, mixed>> les charges reçues par cet utilisateur */
    public function recuPar(int $userId): array
    {
        $endpoints = [];
        foreach ($this->abonnements as $a) {
            if ((int) $a['user_id'] === $userId) {
                $endpoints[] = $a['endpoint'];
            }
        }

        return array_values(array_map(
            static fn(array $e): array => $e['charge'],
            array_filter($this->envoyes, static fn(array $e): bool => in_array($e['endpoint'], $endpoints, true))
        ));
    }

    public function envoyer(array $abonnement, array $charge, int $ttl = 86400): array
    {
        $this->envoyes[] = ['endpoint' => (string) $abonnement['endpoint'], 'charge' => $charge, 'ttl' => $ttl];
        $statut = $this->statuts[(string) $abonnement['endpoint']] ?? 201;

        return [
            'ok' => $statut >= 200 && $statut < 300,
            'status' => $statut,
            'expire' => $statut === 404 || $statut === 410,
            'message' => 'simulé',
        ];
    }

    public function pourUtilisateurs(array $userIds): array
    {
        return array_values(array_filter(
            $this->abonnements,
            static fn(array $a): bool => in_array((int) $a['user_id'], array_map('intval', $userIds), true)
        ));
    }

    public function supprimer(int $id): void
    {
        unset($this->abonnements[$id]);
    }

    public function marquerSucces(int $id): void
    {
        $this->succes[$id] = ($this->succes[$id] ?? 0) + 1;
    }

    public function marquerEchec(int $id): void
    {
        $this->echecs[$id] = ($this->echecs[$id] ?? 0) + 1;
    }
}
