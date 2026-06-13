<?php

namespace App\Modules\Colissage\Services;

use App\Modules\Colissage\Repositories\InventaireRepository;

class InventaireService
{
    public function __construct(private InventaireRepository $repository = new InventaireRepository()) {}

    public function listInventaires(array $filters = []): array
    {
        return $this->repository->fetchInventaires($filters);
    }

    public function createInventaire(array $payload): array
    {
        if (empty($payload['id_agence']) || empty($payload['id_createur'])) {
            throw new \InvalidArgumentException('Agence et créateur sont requis');
        }

        $inventaire = $this->repository->createInventaire($payload);

        if (!empty($payload['lignes']) && is_array($payload['lignes'])) {
            foreach ($payload['lignes'] as $ligne) {
                if (empty($ligne['id_colis']) || empty($ligne['statut_constate'])) {
                    continue;
                }
                $this->repository->addLigne($inventaire['id'], $ligne);
            }
        }

        return $inventaire;
    }
}
