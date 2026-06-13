<?php

namespace App\Modules\Tarifs\Services;

use App\Modules\Tarifs\Repositories\TarifsRepository;

class TarifsService
{
    public function __construct(private TarifsRepository $repository = new TarifsRepository()) {}

    public function getAllTarifs(): array
    {
        return $this->repository->getAll();
    }

    public function calculerPrix(string $paysDepart, string $paysArrivee, string $typeTarif, float $poids = 1.0): array
    {
        $tarif = $this->repository->getTarifTrajet($paysDepart, $paysArrivee, $typeTarif);
        if (!$tarif) {
            throw new \Exception("Aucun tarif configuré pour ce trajet et ce type.");
        }

        $montantTotal = $tarif['montant'];
        if (str_contains($typeTarif, 'KILO')) {
            $montantTotal = $tarif['montant'] * $poids;
        }

        return [
            'tarif_base' => $tarif,
            'poids' => $poids,
            'montant_total' => $montantTotal,
            'devise' => $tarif['devise']
        ];
    }

    public function createTarif(array $data): array
    {
        return $this->repository->create($data);
    }
}
