<?php

declare(strict_types=1);

namespace App\Models\Colisage;

final class Expedition
{
    public function __construct(
        public readonly int $id,
        public readonly string $reference,
        public readonly string $typeTransport,
        public readonly ?int $agenceDepartId,
        public readonly ?int $agenceArriveeId,
        public readonly ?string $dateDepartPrevue,
        public readonly ?string $dateArriveeEstimee,
        public readonly ?int $livreurId,
        public readonly string $statut,
        public readonly string $createdAt,
        public readonly ?string $updatedAt
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['id'] ?? 0),
            (string) ($data['reference'] ?? ''),
            (string) ($data['type_transport'] ?? 'AÉRIEN'),
            isset($data['agence_depart_id']) ? (int) $data['agence_depart_id'] : null,
            isset($data['agence_arrivee_id']) ? (int) $data['agence_arrivee_id'] : null,
            isset($data['date_depart_prevue']) ? (string) $data['date_depart_prevue'] : null,
            isset($data['date_arrivee_estimee']) ? (string) $data['date_arrivee_estimee'] : null,
            isset($data['livreur_id']) ? (int) $data['livreur_id'] : null,
            (string) ($data['statut'] ?? 'BROUILLON'),
            (string) ($data['created_at'] ?? ''),
            isset($data['updated_at']) ? (string) $data['updated_at'] : null
        );
    }
}
