<?php

namespace App\Models\Finance;

class Paiement
{
    public function __construct(
        public ?int $id,
        public int $factureId,
        public ?int $caissiereId,
        public float $montant,
        public string $devise,
        public string $mode,
        public string $type,
        public ?string $datePaiement = null
    ) {}
}
