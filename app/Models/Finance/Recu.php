<?php

namespace App\Models\Finance;

class Recu
{
    public function __construct(
        public ?int $id,
        public int $paiementId,
        public string $numeroRecu,
        public ?string $pdfUrl = null,
        public ?string $dateEmission = null
    ) {}
}
