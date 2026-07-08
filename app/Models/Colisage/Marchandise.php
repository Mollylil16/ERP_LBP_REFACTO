<?php

declare(strict_types=1);

namespace App\Models\Colisage;

final class Marchandise
{
    public function __construct(
        public readonly int $id,
        public readonly int $colisId,
        public readonly string $description,
        public readonly int $quantite,
        public readonly float $poidsUnitaire,
        public readonly string $createdAt
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['id'] ?? 0),
            (int) ($data['colis_id'] ?? 0),
            (string) ($data['description'] ?? ''),
            (int) ($data['quantite'] ?? 1),
            (float) ($data['poids_unitaire'] ?? 0.0),
            (string) ($data['created_at'] ?? '')
        );
    }
}
