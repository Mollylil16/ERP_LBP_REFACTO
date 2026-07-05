<?php

namespace App\Models\Finance;

class Facture
{
    public function __construct(
        public ?int $id,
        public string $numeroFacture,
        public int $colisId,
        public int $clientId,
        public int $caissiereId,
        public int $agenceId,
        public float $montantTotal,
        public float $montantEncaisse,
        public float $montantRestant,
        public string $devise,
        public ?float $tauxChange,
        public string $statut,
        public ?string $qrCodePaiement = null,
        public ?string $dateExpirationQr = null,
        public ?string $dateEmission = null,
        public ?string $dateEcheanceSolde = null,
        public ?string $updatedAt = null
    ) {}
}
