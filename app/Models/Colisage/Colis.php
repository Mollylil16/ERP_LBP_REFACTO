<?php

declare(strict_types=1);

namespace App\Models\Colisage;

final class Colis
{
    public function __construct(
        public readonly int $id,
        public readonly string $numeroTracking,
        public readonly int $expediteurId,
        public readonly int $destinataireId,
        public readonly float $poidsTotal,
        public readonly float $valeurDeclaree,
        public readonly string $devise,
        public readonly ?int $agenceDepartId,
        public readonly ?int $agenceArriveeId,
        public readonly string $statut,
        public readonly ?string $typeExpediteur,
        public readonly ?int $expeditionId,
        public readonly ?string $recupNom,
        public readonly ?string $recupCni,
        public readonly ?string $recupTelephone,
        public readonly ?string $recupDateHeure,
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
            (string) ($data['numero_tracking'] ?? ''),
            (int) ($data['expediteur_id'] ?? 0),
            (int) ($data['destinataire_id'] ?? 0),
            (float) ($data['poids_total'] ?? 0.0),
            (float) ($data['valeur_declaree'] ?? 0.0),
            (string) ($data['devise'] ?? 'XOF'),
            isset($data['agence_depart_id']) ? (int) $data['agence_depart_id'] : null,
            isset($data['agence_arrivee_id']) ? (int) $data['agence_arrivee_id'] : null,
            (string) ($data['statut'] ?? 'RÉCEPTIONNÉ'),
            isset($data['type_expediteur']) ? (string) $data['type_expediteur'] : null,
            isset($data['expedition_id']) ? (int) $data['expedition_id'] : null,
            isset($data['recup_nom']) ? (string) $data['recup_nom'] : null,
            isset($data['recup_cni']) ? (string) $data['recup_cni'] : null,
            isset($data['recup_telephone']) ? (string) $data['recup_telephone'] : null,
            isset($data['recup_date_heure']) ? (string) $data['recup_date_heure'] : null,
            (string) ($data['created_at'] ?? ''),
            isset($data['updated_at']) ? (string) $data['updated_at'] : null
        );
    }
}
