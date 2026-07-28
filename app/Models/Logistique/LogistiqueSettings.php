<?php

declare(strict_types=1);

namespace App\Models\Logistique;

final class LogistiqueSettings
{
    public function __construct(
        public readonly int $id,
        public readonly ?int $agenceId,
        public readonly int $delaiGratuitJours,
        public readonly float $fraisGardiennageParJour,
        public readonly bool $autoAssignRayon,
        public readonly string $createdAt,
        public readonly ?string $updatedAt = null
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['id'] ?? 0),
            isset($data['agence_id']) ? (int) $data['agence_id'] : null,
            (int) ($data['delai_gratuit_jours'] ?? 7),
            (float) ($data['frais_gardiennage_par_jour'] ?? 500.0),
            !empty($data['auto_assign_rayon']),
            (string) ($data['created_at'] ?? ''),
            isset($data['updated_at']) ? (string) $data['updated_at'] : null
        );
    }
}
