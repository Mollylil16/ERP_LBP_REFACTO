<?php

namespace App\Models\Finance;

class DemandePaiement
{
    public function __construct(
        public ?int $id,
        public int $prestataireId,
        public int $superviseurRegionalId,
        public float $montant,
        public string $devise,
        public string $motif,
        public ?string $justificatifUrl,
        public string $statut,
        public ?int $caissierePrincipaleId = null,
        public ?string $dateDemande = null,
        public ?string $dateTraitement = null,
        public ?string $updatedAt = null
    ) {}

    public ?string $prestataire_name = null;
}
