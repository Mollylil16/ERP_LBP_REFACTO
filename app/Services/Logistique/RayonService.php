<?php

declare(strict_types=1);

namespace App\Services\Logistique;

use App\Repositories\Logistique\RayonRepository;
use App\Repositories\Logistique\LogistiqueSettingsRepository;
use App\Models\Logistique\Rayon;
use DateTimeImmutable;

class RayonService
{
    public function __construct(
        private RayonRepository $rayonRepository,
        private LogistiqueSettingsRepository $settingsRepository
    ) {}

    /**
     * Affecte automatiquement un colis à un rayon disponible de l'agence si le paramètre auto_assign_rayon est activé.
     *
     * @param array<string, mixed> $colisData
     * @return array{rayonId: ?int, dateArrivee: string, dateLimiteRetrait: string, autoAssigne: bool}
     */
    public function autoAssignRayonForColis(int $agenceId, array $colisData): array
    {
        $settings = $this->settingsRepository->getSettings($agenceId);

        $now = new DateTimeImmutable();
        $dateArrivee = $now->format('Y-m-d H:i:s');
        $dateLimite = $now->modify("+{$settings->delaiGratuitJours} days")->format('Y-m-d H:i:s');

        $assignedRayonId = null;
        $autoAssigne = false;

        if ($settings->autoAssignRayon) {
            $typeColis = (string) ($colisData['type_expediteur'] ?? $colisData['trafic'] ?? '');
            $poidsTotal = (float) ($colisData['poids_total'] ?? 0.0);
            $estAssure = !empty($colisData['assurance_souscrite']);

            $rayon = $this->rayonRepository->findAvailableRayon($agenceId, $typeColis, $poidsTotal, $estAssure);
            if ($rayon !== null) {
                $assignedRayonId = $rayon->id;
                $autoAssigne = true;
            }
        }

        return [
            'rayonId' => $assignedRayonId,
            'dateArrivee' => $dateArrivee,
            'dateLimiteRetrait' => $dateLimite,
            'autoAssigne' => $autoAssigne,
        ];
    }
}
