<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Repositories\PaiementRepository;

class PaiementService
{
    public function __construct(private PaiementRepository $repository = new PaiementRepository()) {}

    public function listPaiements(array $filters = []): array
    {
        return $this->repository->fetchPaiements($filters);
    }

    public function createPaiement(array $payload): array
    {
        if (empty($payload['id_facture']) || empty($payload['montant']) || empty($payload['mode_paiement'])) {
            throw new \InvalidArgumentException('Facture, montant et mode de paiement sont requis');
        }

        return $this->repository->createPaiement($payload);
    }
}
