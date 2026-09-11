<?php

declare(strict_types=1);

namespace App\Services\FlotteTransport;

use App\Repositories\FlotteTransport\FlotteTransportDashboardRepository;

final class FlotteTransportDashboardService extends \App\Services\Shared\AbstractModuleDashboardService implements \App\Services\Shared\ModuleDashboardContract
{
    /** Au-delà, la position remontée n'est plus considérée comme fraîche. */
    private const POSITION_FRAICHE_HEURES = 6;

    public function __construct(private FlotteTransportDashboardRepository $flotte)
    {
        parent::__construct($flotte);
    }

    /**
     * @return array<string, mixed>
     */
    public function flotte(?int $agenceId): array
    {
        $livreurs = $this->qualifierPositions($this->flotte->livreurs($agenceId));
        $missions = $this->flotte->missions($agenceId);
        $terminees = $this->flotte->missionsTerminees($agenceId);

        $enRetard = array_values(array_filter($missions, static fn(array $m): bool => (int) $m['jours_retard'] > 0));

        return [
            'livreurs' => $livreurs,
            'missions' => $missions,
            'enRetard' => $enRetard,
            'terminees' => $terminees,
            'kpis' => $this->kpis($livreurs, $missions, $enRetard),
        ];
    }

    /**
     * Indique si la dernière position connue est encore exploitable.
     *
     * Une position vieille d'une semaine affichée sans avertissement se lit
     * comme la position actuelle du livreur : c'est cette confusion qu'on évite.
     *
     * @param array<int, array<string, mixed>> $livreurs
     * @return array<int, array<string, mixed>>
     */
    private function qualifierPositions(array $livreurs): array
    {
        $limite = time() - self::POSITION_FRAICHE_HEURES * 3600;

        foreach ($livreurs as &$livreur) {
            $date = (string) ($livreur['derniere_localisation'] ?? '');
            $horodatage = $date !== '' ? strtotime($date) : false;

            $livreur['a_position'] = $livreur['latitude'] !== null && $livreur['longitude'] !== null;
            $livreur['position_fraiche'] = $horodatage !== false && $horodatage >= $limite;
        }
        unset($livreur);

        return $livreurs;
    }

    /**
     * @param array<int, array<string, mixed>> $livreurs
     * @param array<int, array<string, mixed>> $missions
     * @param array<int, array<string, mixed>> $enRetard
     * @return array<int, array<string, mixed>>
     */
    private function kpis(array $livreurs, array $missions, array $enRetard): array
    {
        $disponibles = count(array_filter($livreurs, static fn(array $l): bool => (string) $l['statut'] === 'Disponible'));
        $avecVehicule = count(array_filter(
            $livreurs,
            static fn(array $l): bool => trim((string) ($l['plaque_immatriculation'] ?? '')) !== ''
        ));
        $colis = array_sum(array_map(static fn(array $m): int => (int) $m['nb_colis'], $missions));

        return [
            [
                'label' => 'Livreurs',
                'value' => (string) count($livreurs),
                'meta' => $disponibles . ' disponible(s), ' . (count($livreurs) - $disponibles) . ' en course',
            ],
            [
                'label' => 'Véhicules identifiés',
                'value' => (string) $avecVehicule,
                'meta' => $avecVehicule < count($livreurs)
                    ? (count($livreurs) - $avecVehicule) . ' livreur(s) sans plaque renseignée'
                    : 'Toutes les plaques sont renseignées',
            ],
            [
                'label' => 'Missions en cours',
                'value' => (string) count($missions),
                'meta' => $colis . ' colis embarqués',
            ],
            [
                'label' => 'Missions en retard',
                'value' => (string) count($enRetard),
                'meta' => 'Arrivée estimée dépassée',
            ],
        ];
    }
}
