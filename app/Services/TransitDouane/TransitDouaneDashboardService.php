<?php

declare(strict_types=1);

namespace App\Services\TransitDouane;

use App\Repositories\TransitDouane\TransitDouaneDashboardRepository;

final class TransitDouaneDashboardService extends \App\Services\Shared\AbstractModuleDashboardService implements \App\Services\Shared\ModuleDashboardContract
{
    /**
     * Écart à partir duquel le coût au kilo d'un lot est signalé comme une
     * dérive par rapport à la moyenne de son trajet.
     */
    private const SEUIL_DERIVE = 0.25;

    public function __construct(private TransitDouaneDashboardRepository $transit)
    {
        parent::__construct($transit);
    }

    /**
     * @return array<string, mixed>
     */
    public function dossiers(?int $agenceId): array
    {
        $lots = $this->recalculerCouts($this->transit->lots());
        $parTrajet = $this->transit->coutParTrajet();
        $lots = $this->signalerDerives($lots, $parTrajet);

        return [
            'lots' => $lots,
            'parTrajet' => $this->moyennes($parTrajet),
            'enTransit' => $this->transit->colisEnTransit($agenceId),
            'kpis' => $this->kpis($lots),
        ];
    }

    /**
     * Recalcule le coût au kilo à partir des frais courants.
     *
     * @param array<int, array<string, mixed>> $lots
     * @return array<int, array<string, mixed>>
     */
    private function recalculerCouts(array $lots): array
    {
        foreach ($lots as &$lot) {
            $poids = (float) $lot['poids_total_kg'];
            $total = (float) $lot['total_xof'];

            $lot['cout_kg'] = $poids > 0 ? round($total / $poids, 2) : null;
            // Un écart avec la valeur figée signale une correction de frais
            // postérieure à la saisie : c'est l'information utile, pas un bug.
            $lot['cout_kg_desynchronise'] = $lot['cout_kg'] !== null
                && abs($lot['cout_kg'] - (float) $lot['cout_kg_saisi']) >= 1.0;
        }
        unset($lot);

        return $lots;
    }

    /**
     * Marque les lots dont le coût au kilo s'écarte de la moyenne du trajet.
     *
     * @param array<int, array<string, mixed>> $lots
     * @param array<int, array<string, mixed>> $parTrajet
     * @return array<int, array<string, mixed>>
     */
    private function signalerDerives(array $lots, array $parTrajet): array
    {
        $reference = [];
        foreach ($parTrajet as $trajet) {
            $poids = (float) $trajet['poids_kg'];
            if ($poids > 0) {
                $reference[(string) $trajet['trajet_code']] = (float) $trajet['total_xof'] / $poids;
            }
        }

        foreach ($lots as &$lot) {
            $moyenne = $reference[(string) $lot['trajet_code']] ?? null;
            $cout = $lot['cout_kg'];

            $lot['ecart_moyenne'] = $moyenne !== null && $moyenne > 0 && $cout !== null
                ? round(($cout - $moyenne) / $moyenne * 100)
                : null;
            $lot['derive'] = $lot['ecart_moyenne'] !== null
                && abs((float) $lot['ecart_moyenne']) >= self::SEUIL_DERIVE * 100;
        }
        unset($lot);

        return $lots;
    }

    /**
     * @param array<int, array<string, mixed>> $parTrajet
     * @return array<int, array<string, mixed>>
     */
    private function moyennes(array $parTrajet): array
    {
        foreach ($parTrajet as &$trajet) {
            $poids = (float) $trajet['poids_kg'];
            $trajet['cout_kg_moyen'] = $poids > 0 ? round((float) $trajet['total_xof'] / $poids, 2) : null;
        }
        unset($trajet);

        return $parTrajet;
    }

    /**
     * @param array<int, array<string, mixed>> $lots
     * @return array<int, array<string, mixed>>
     */
    private function kpis(array $lots): array
    {
        $ouverts = array_filter($lots, static fn(array $l): bool => (string) $l['statut'] !== 'CLÔTURÉ');
        $derives = array_filter($lots, static fn(array $l): bool => (bool) $l['derive']);

        $douane = array_sum(array_map(static fn(array $l): float => (float) $l['frais_douane_xof'], $lots));
        $poids = array_sum(array_map(static fn(array $l): float => (float) $l['poids_total_kg'], $lots));
        $total = array_sum(array_map(static fn(array $l): float => (float) $l['total_xof'], $lots));

        return [
            [
                'label' => 'Lots suivis',
                'value' => (string) count($lots),
                'meta' => count($ouverts) . ' encore ouvert(s)',
            ],
            [
                'label' => 'Frais de douane',
                'value' => number_format($douane, 0, ',', ' ') . ' XOF',
                'meta' => 'Cumul sur les lots affichés',
            ],
            [
                'label' => 'Coût moyen au kilo',
                // Sans poids saisi, la division n'a pas de sens : on l'écrit.
                'value' => $poids > 0 ? number_format($total / $poids, 0, ',', ' ') . ' XOF/kg' : '—',
                'meta' => $poids > 0
                    ? number_format($poids, 0, ',', ' ') . ' kg dédouanés'
                    : 'Aucun poids renseigné',
            ],
            [
                'label' => 'Lots hors norme',
                'value' => (string) count($derives),
                'meta' => 'Coût au kilo à plus de 25 % de la moyenne du trajet',
            ],
        ];
    }
}
