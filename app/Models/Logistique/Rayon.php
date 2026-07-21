<?php

declare(strict_types=1);

namespace App\Models\Logistique;

final class Rayon
{
    public function __construct(
        public readonly int $id,
        public readonly int $agenceId,
        public readonly string $codeRayon,
        public readonly string $nomRayon,
        public readonly int $capaciteMax,
        public readonly int $capaciteOccupee,
        public readonly string $statut,
        public readonly string $createdAt,
        public readonly ?string $updatedAt = null,
        public readonly ?string $agenceNom = null
    ) {}

    public function tauxOccupation(): float
    {
        if ($this->capaciteMax <= 0) {
            return 0.0;
        }
        return min(100.0, round(($this->capaciteOccupee / $this->capaciteMax) * 100, 1));
    }

    public function placesDisponibles(): int
    {
        return max(0, $this->capaciteMax - $this->capaciteOccupee);
    }

    public function estDisponible(): bool
    {
        return $this->statut === 'ACTIF' && $this->placesDisponibles() > 0;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['id'] ?? 0),
            (int) ($data['agence_id'] ?? 0),
            (string) ($data['code_rayon'] ?? ''),
            (string) ($data['nom_rayon'] ?? ''),
            (int) ($data['capacite_max'] ?? 50),
            (int) ($data['capacite_occupee'] ?? 0),
            (string) ($data['statut'] ?? 'ACTIF'),
            (string) ($data['created_at'] ?? ''),
            isset($data['updated_at']) ? (string) $data['updated_at'] : null,
            isset($data['agence_nom']) ? (string) $data['agence_nom'] : null
        );
    }
}
