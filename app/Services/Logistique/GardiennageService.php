<?php

declare(strict_types=1);

namespace App\Services\Logistique;

use App\Repositories\Logistique\LogistiqueSettingsRepository;
use DateTimeImmutable;

class GardiennageService
{
    public function __construct(private LogistiqueSettingsRepository $settingsRepository) {}

    /**
     * Calcul des frais de gardiennage pour un colis donné à une date spécifique.
     *
     * @param array<string, mixed> $colis
     * @return array{
     *     delaiGratuitJours: int,
     *     dateArrivee: ?string,
     *     dateLimiteRetrait: string,
     *     joursRetard: int,
     *     fraisParJour: float,
     *     totalFraisGardiennage: float,
     *     estHorsDelai: bool
     * }
     */
    public function calculateGardiennage(array $colis, ?string $dateReference = null, ?int $agenceId = null): array
    {
        $settings = $this->settingsRepository->getSettings($agenceId ?? (int) ($colis['agence_arrivee_id'] ?? 0));

        $dateArriveeStr = $colis['date_arrivee_agence'] ?? $colis['created_at'] ?? 'now';
        $dateArrivee = new DateTimeImmutable((string) $dateArriveeStr);

        $delaiJours = $settings->delaiGratuitJours;
        $fraisParJour = $settings->fraisGardiennageParJour;

        // Date limite théorique de retrait
        $dateLimite = isset($colis['date_limite_retrait']) && !empty($colis['date_limite_retrait'])
            ? new DateTimeImmutable((string) $colis['date_limite_retrait'])
            : $dateArrivee->modify("+{$delaiJours} days");

        $dateRef = $dateReference ? new DateTimeImmutable($dateReference) : new DateTimeImmutable();

        $joursRetard = 0;
        if ($dateRef > $dateLimite) {
            $diff = $dateLimite->diff($dateRef);
            $joursRetard = (int) $diff->days;
        }

        $totalFrais = $joursRetard * $fraisParJour;

        return [
            'delaiGratuitJours' => $delaiJours,
            'dateArrivee' => $dateArrivee->format('Y-m-d H:i:s'),
            'dateLimiteRetrait' => $dateLimite->format('Y-m-d H:i:s'),
            'joursRetard' => $joursRetard,
            'fraisParJour' => $fraisParJour,
            'totalFraisGardiennage' => round($totalFrais, 2),
            'estHorsDelai' => $joursRetard > 0,
        ];
    }
}
