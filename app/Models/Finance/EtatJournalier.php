<?php

namespace App\Models\Finance;

class EtatJournalier
{
    public function __construct(
        public ?int $id,
        public int $agenceId,
        public ?int $chefAgenceId,
        public string $dateJour,
        public int $nbColisEnregistres,
        public int $nbFacturesEmises,
        public float $totalFactureXof,
        public float $totalFactureEur,
        public float $totalEncaisseXof,
        public float $totalEncaisseEur,
        public float $totalRestantDuXof,
        public float $totalRestantDuEur,
        public float $soldeCaisseAgenceXof,
        public float $soldeCaisseAgenceEur,
        public string $statut,
        public ?string $dateSoumission = null,
        public ?int $consolideParId = null,
        public ?string $dateConsolidation = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null
    ) {}
}
