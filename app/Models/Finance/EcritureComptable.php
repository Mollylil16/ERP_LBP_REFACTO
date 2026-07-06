<?php

namespace App\Models\Finance;

class EcritureComptable
{
    public function __construct(
        public ?int $id,
        public string $dateEcriture,
        public string $journal,
        public string $compteDebit,
        public string $compteCredit,
        public float $montant,
        public string $devise,
        public ?float $tauxChange,
        public ?string $pieceJustificativeId,
        public string $libelle,
        public ?string $lettrage = null,
        public ?string $createdAt = null
    ) {}
}
