<?php

declare(strict_types=1);

namespace App\Models\Finance;

final class ImputationFonds
{
    public function __construct(
        public ?int $id,
        public int $demandeFondsId,
        public float $montantEngage,
        public float $montantReelDepense,
        public float $montantReliquatRestitue,
        public ?string $piecesJustificatives = null,
        public ?string $commentaires = null,
        public int $imputeParId = 0,
        public string $statutImputation = 'conforme', // 'conforme' | 'ecart_constate' | 'reliquat_encaisse'
        public ?string $dateImputation = null,
        // Hydrated relations
        public ?string $imputeParNom = null
    ) {}
}
