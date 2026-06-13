<?php

namespace App\Modules\Colissage\Services;

use App\Modules\Colissage\Repositories\ColisRepository;

class ColisService
{
    public function __construct(private ColisRepository $repository = new ColisRepository()) {}

    public function listColis(array $filters = []): array
    {
        return $this->repository->fetchColis($filters);
    }

    public function getColis(int $id): array
    {
        $colis = $this->repository->fetchColisById($id);
        if ($colis === null) {
            throw new \RuntimeException('Colis introuvable');
        }
        return $colis;
    }

    public function createColis(array $payload, string $codeAgence = 'CI'): array
    {
        $sequenceService = new \App\Core\SequenceGeneratorService();
        $numeroTracking = $sequenceService->generateDossier($codeAgence);
        return $this->repository->createColis($payload, $numeroTracking);
    }

    public function updateStatut(int $id, string $statut): array
    {
        if (empty($statut)) {
            throw new \InvalidArgumentException('Le statut est requis');
        }

        $colis = $this->repository->updateStatut($id, $statut);
        if ($colis === null) {
            throw new \RuntimeException('Colis introuvable ou mise à jour impossible');
        }

        return $colis;
    }

    public function retraitColis(int $id, array $payload): array
    {
        if (empty($payload['nom_recuperateur']) || empty($payload['cni_recuperateur'])) {
            throw new \InvalidArgumentException('Le nom et la CNI du récupérateur sont obligatoires pour un retrait.');
        }

        $colis = $this->repository->retraitColis($id, $payload);
        if ($colis === null) {
            throw new \RuntimeException('Colis introuvable ou mise à jour impossible');
        }

        return $colis;
    }
}
