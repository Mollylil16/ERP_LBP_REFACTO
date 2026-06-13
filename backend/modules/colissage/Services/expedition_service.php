<?php

namespace App\Modules\Colissage\Services;

use App\Modules\Colissage\Repositories\ExpeditionRepository;

class ExpeditionService
{
    public function __construct(private ExpeditionRepository $repository = new ExpeditionRepository()) {}

    public function listExpeditions(array $filters = []): array
    {
        return $this->repository->fetchExpeditions($filters);
    }

    public function createExpedition(array $payload): array
    {
        if (empty($payload['type']) || empty($payload['id_agence_depart']) || empty($payload['id_agence_arrivee'])) {
            throw new \InvalidArgumentException('Le type, l\'agence de départ et d\'arrivée sont requis');
        }

        return $this->repository->createExpedition($payload);
    }

    public function updateStatut(int $id, string $statut): array
    {
        $expedition = $this->repository->updateStatut($id, $statut);
        if ($expedition === null) {
            throw new \RuntimeException('Expedition introuvable');
        }
        return $expedition;
    }

    public function addGpsTracking(int $id, array $payload): array
    {
        if (empty($payload['etape_description'])) {
            throw new \InvalidArgumentException('La description de l\'étape est obligatoire');
        }

        $this->repository->addGpsTracking($id, $payload);
        return $this->repository->fetchGpsTracking($id);
    }

    public function getGpsTracking(int $id): array
    {
        return $this->repository->fetchGpsTracking($id);
    }
}
