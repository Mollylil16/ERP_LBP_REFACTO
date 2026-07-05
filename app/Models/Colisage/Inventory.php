<?php

declare(strict_types=1);

namespace App\Models\Colisage;

final class Inventory
{
    public function __construct(
        public readonly int $id,
        public readonly int $agenceId,
        public readonly string $dateInventaire,
        public readonly string $statut,
        public readonly ?string $commentaires,
        public readonly ?int $creePar
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['id'] ?? 0),
            (int) ($data['agence_id'] ?? 0),
            (string) ($data['date_inventaire'] ?? ''),
            (string) ($data['statut'] ?? 'BROUILLON'),
            isset($data['commentaires']) ? (string) $data['commentaires'] : null,
            isset($data['cree_par']) ? (int) $data['cree_par'] : null
        );
    }
}
