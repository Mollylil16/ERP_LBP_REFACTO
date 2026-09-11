<?php

declare(strict_types=1);

namespace App\Services\TrackingColis;

use App\Repositories\TrackingColis\TrackingColisDashboardRepository;

final class TrackingColisDashboardService extends \App\Services\Shared\AbstractModuleDashboardService implements \App\Services\Shared\ModuleDashboardContract
{
    /** Fenêtre d'observation des consultations du site public. */
    private const FENETRE_JOURS = 30;

    /** Au-delà, un colis actif est considéré comme immobilisé. */
    private const IMMOBILISE_JOURS = 21;

    public function __construct(private TrackingColisDashboardRepository $tracking)
    {
        parent::__construct($tracking);
    }

    /**
     * @return array<string, mixed>
     */
    public function suivi(?int $agenceId, string $recherche = ''): array
    {
        $colis = $this->qualifier($this->tracking->colisActifs($agenceId, $recherche));
        $statuts = $this->tracking->repartitionStatuts($agenceId);
        $echecs = $this->tracking->recherchesInfructueuses(self::FENETRE_JOURS);
        $volume = $this->tracking->volumeRecherches(self::FENETRE_JOURS);

        // Une recherche exacte sur une référence connue ouvre directement la fiche.
        $fiche = null;
        if ($recherche !== '') {
            $trouve = $this->tracking->colisParReference($recherche);
            if ($trouve !== null) {
                $fiche = [
                    'colis' => $trouve,
                    'evenements' => $this->tracking->evenements((int) $trouve['id']),
                ];
            }
        }

        return [
            'recherche' => $recherche,
            'fiche' => $fiche,
            'colis' => $colis,
            'statuts' => $statuts,
            'echecs' => $echecs,
            'volume' => $volume,
            'fenetre' => self::FENETRE_JOURS,
            'kpis' => $this->kpis($colis, $statuts, $volume),
        ];
    }

    /**
     * Marque les colis actifs qui n'avancent plus.
     *
     * @param array<int, array<string, mixed>> $colis
     * @return array<int, array<string, mixed>>
     */
    private function qualifier(array $colis): array
    {
        foreach ($colis as &$ligne) {
            $age = (int) ($ligne['jours_depuis_prise_en_charge'] ?? 0);

            $ligne['immobilise'] = $age >= self::IMMOBILISE_JOURS;
            $ligne['a_trace'] = !empty($ligne['derniere_etape']);
        }
        unset($ligne);

        return $colis;
    }

    /**
     * @param array<int, array<string, mixed>> $colis
     * @param array<int, array<string, mixed>> $statuts
     * @param array{total:int, echecs:int} $volume
     * @return array<int, array<string, mixed>>
     */
    private function kpis(array $colis, array $statuts, array $volume): array
    {
        $immobilises = count(array_filter($colis, static fn(array $c): bool => (bool) $c['immobilise']));
        $sansTrace = count(array_filter($colis, static fn(array $c): bool => !$c['a_trace']));

        $enTransit = 0;
        foreach ($statuts as $statut) {
            if ((string) $statut['statut'] === 'EN_TRANSIT') {
                $enTransit = (int) $statut['nb_envois'];
            }
        }

        $tauxEchec = $volume['total'] > 0
            ? (int) round($volume['echecs'] / $volume['total'] * 100)
            : null;

        return [
            [
                'label' => 'Colis en cours',
                'value' => (string) count($colis),
                'meta' => $enTransit . ' en transit',
            ],
            [
                'label' => 'Immobilisés',
                'value' => (string) $immobilises,
                'meta' => 'Pris en charge depuis plus de ' . self::IMMOBILISE_JOURS . ' jours',
            ],
            [
                'label' => 'Sans aucune trace',
                'value' => (string) $sansTrace,
                'meta' => 'Colis actifs sans le moindre point de suivi',
            ],
            [
                'label' => 'Recherches sans réponse',
                // Sans consultation sur la période, le taux n'existe pas.
                'value' => $tauxEchec !== null ? $tauxEchec . ' %' : '—',
                'meta' => $volume['total'] > 0
                    ? $volume['echecs'] . ' échecs sur ' . $volume['total'] . ' recherches (' . self::FENETRE_JOURS . ' j)'
                    : 'Aucune recherche sur le site public',
            ],
        ];
    }
}
