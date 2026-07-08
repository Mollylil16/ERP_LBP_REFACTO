<?php

declare(strict_types=1);

namespace App\Models\Colisage;

final class Produit
{
    public function __construct(
        public readonly int $id,
        public readonly string $nom,
        public readonly ?string $categorie,
        public readonly ?string $nature,
        public readonly float $prixUnitaire,
        public readonly ?float $prixForfaitaire,
        public readonly ?string $description,
        public readonly bool $actif,
        public readonly string $unite
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['id'] ?? 0),
            (string) ($data['nom'] ?? ''),
            isset($data['categorie']) ? (string) $data['categorie'] : null,
            isset($data['nature']) ? (string) $data['nature'] : null,
            (float) ($data['prix_unitaire'] ?? 0.0),
            isset($data['prix_forfaitaire']) ? (float) $data['prix_forfaitaire'] : null,
            isset($data['description']) ? (string) $data['description'] : null,
            (bool) ($data['actif'] ?? true),
            (string) ($data['unite'] ?? 'kg')
        );
    }
}
