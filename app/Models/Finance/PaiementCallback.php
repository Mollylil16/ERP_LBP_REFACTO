<?php

namespace App\Models\Finance;

class PaiementCallback
{
    public function __construct(
        public ?int $id,
        public ?int $factureId,
        public ?int $paiementId,
        public string $provider,
        public string $transactionReference,
        public float $montant,
        public string $devise,
        public string $statut,
        public ?string $rawPayload = null,
        public ?string $createdAt = null
    ) {}
}
