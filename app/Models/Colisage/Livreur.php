<?php

declare(strict_types=1);

namespace App\Models\Colisage;

final class Livreur
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly ?string $modeleVehicule,
        public readonly ?string $plaqueImmatriculation,
        public readonly string $statut,
        public readonly ?float $latitude,
        public readonly ?float $longitude,
        public readonly ?string $derniereLocalisation
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['id'] ?? 0),
            (int) ($data['user_id'] ?? 0),
            isset($data['modele_vehicule']) ? (string) $data['modele_vehicule'] : null,
            isset($data['plaque_immatriculation']) ? (string) $data['plaque_immatriculation'] : null,
            (string) ($data['statut'] ?? 'Disponible'),
            isset($data['latitude']) ? (float) $data['latitude'] : null,
            isset($data['longitude']) ? (float) $data['longitude'] : null,
            isset($data['derniere_localisation']) ? (string) $data['derniere_localisation'] : null
        );
    }
}
