<?php

declare(strict_types=1);

namespace App\Models\Finance;

final class DemandeFonds
{
    public function __construct(
        public ?int $id,
        public string $numeroDemande,
        public int $agenceId,
        public string $cadre, // 'traitement_dossier' | 'fonctionnement'
        public ?string $dossierNum,
        public string $motif,
        public float $montant,
        public string $devise,
        public int $demandeurId,
        public string $statut = 'en_attente', // 'en_attente' | 'validee' | 'rejetee' | 'decaissee' | 'imputee'
        public ?string $motifRejet = null,
        public ?int $validateurId = null,
        public ?string $dateValidation = null,
        public ?int $caissiereId = null,
        public ?string $dateDecaissement = null,
        public string $modePaiement = 'Espèces',
        public ?string $referenceBonCaisse = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
        // Hydrated relations
        public ?string $agenceNom = null,
        public ?string $demandeurNom = null,
        public ?string $validateurNom = null,
        public ?string $caissiereNom = null,
        public ?ImputationFonds $imputation = null
    ) {}
}
