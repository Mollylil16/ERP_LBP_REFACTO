<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Repositories\FactureRepository;

class FactureService
{
    public function __construct(private FactureRepository $repository = new FactureRepository()) {}

    public function listFactures(array $filters = []): array
    {
        return $this->repository->fetchFactures($filters);
    }

    public function getFacture(int $id): array
    {
        $facture = $this->repository->fetchFactureById($id);
        if ($facture === null) {
            throw new \RuntimeException('Facture introuvable');
        }
        return $facture;
    }

    public function createFacture(array $payload): array
    {
        if (empty($payload['id_client']) || empty($payload['montant_total'])) {
            throw new \InvalidArgumentException('Client et montant total sont requis');
        }

        return $this->repository->createFacture($payload);
    }
}
