<?php

namespace App\Modules\Logistique\Services;

use App\Modules\Logistique\Repositories\ExpeditionRepository;

class ExpeditionService
{
    public function __construct(private ExpeditionRepository $repository = new ExpeditionRepository()) {}

    public function listExpeditions(array $filters = []): array
    {
        return $this->repository->fetchExpeditions($filters);
    }

    public function getExpedition(int $id): array
    {
        $exp = $this->repository->fetchExpeditionById($id);
        if ($exp === null) {
            throw new \RuntimeException('Expédition introuvable');
        }
        return $exp;
    }

    public function createExpedition(array $payload): array
    {
        return $this->repository->createExpedition($payload);
    }

    public function updateStatut(int $id, string $statut): array
    {
        if (empty($statut)) {
            throw new \InvalidArgumentException('Le statut est requis');
        }

        $exp = $this->repository->updateStatut($id, $statut);
        if ($exp === null) {
            throw new \RuntimeException('Expédition introuvable ou mise à jour impossible');
        }

        return $exp;
    }
}
