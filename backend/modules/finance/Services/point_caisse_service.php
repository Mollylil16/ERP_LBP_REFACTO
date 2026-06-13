<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Repositories\PointCaisseRepository;

class PointCaisseService
{
    public function __construct(private PointCaisseRepository $repository = new PointCaisseRepository()) {}

    public function listPointsCaisse(array $filters = []): array
    {
        return $this->repository->fetchPointsCaisse($filters);
    }

    public function createPointCaisse(array $payload): array
    {
        if (empty($payload['id_agence']) || empty($payload['id_caissiere']) || empty($payload['date_point'])) {
            throw new \InvalidArgumentException('Agence, caissière et date sont requis');
        }

        return $this->repository->createPointCaisse($payload);
    }

    public function validerPointCaisse(int $id, int $idValidateur, string $action): array
    {
        // action = 'VALIDE' ou 'REJETE'
        if (!in_array($action, ['VALIDE', 'REJETE'])) {
            throw new \InvalidArgumentException('Action invalide');
        }

        $point = $this->repository->validerPointCaisse($id, $idValidateur, $action);
        if ($point === null) {
            throw new \RuntimeException('Point de caisse introuvable');
        }

        return $point;
    }
}
