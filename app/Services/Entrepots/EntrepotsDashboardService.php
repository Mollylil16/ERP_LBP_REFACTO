<?php

declare(strict_types=1);

namespace App\Services\Entrepots;

use App\Repositories\Entrepots\EntrepotsDashboardRepository;

/**
 * Met en forme l'occupation des magasins et chiffre le gardiennage dû.
 *
 * Le calcul du gardiennage est fait ici et non en SQL : le délai de gratuité et
 * le tarif varient par agence, et la règle métier doit rester lisible à un
 * endroit plutôt que noyée dans une expression CASE.
 */
final class EntrepotsDashboardService extends \App\Services\Shared\AbstractModuleDashboardService implements \App\Services\Shared\ModuleDashboardContract
{
    public function __construct(private EntrepotsDashboardRepository $entrepots)
    {
        parent::__construct($entrepots);
    }

    /**
     * Tout ce qu'affiche l'écran, en une passe.
     *
     * @return array<string, mixed>
     */
    public function occupation(?int $agenceId): array
    {
        $parametres = $this->entrepots->parametres($agenceId);
        $rayons = $this->entrepots->rayons($agenceId);
        $colis = $this->chiffrerGardiennage($this->entrepots->colisStockes($agenceId), $parametres);

        $enSouffrance = array_values(array_filter($colis, static fn(array $c): bool => $c['jours_factures'] > 0));

        return [
            'parametres' => $parametres,
            'rayons' => $this->classerRayons($rayons),
            'colis' => $colis,
            'enSouffrance' => $enSouffrance,
            'mouvements' => $this->entrepots->mouvementsRecents($agenceId),
            'inventaires' => $this->entrepots->inventaires($agenceId),
            'kpis' => $this->kpis($rayons, $colis, $enSouffrance),
        ];
    }

    /**
     * Ajoute à chaque colis les jours facturables et le gardiennage dû.
     *
     * @param array<int, array<string, mixed>> $colis
     * @param array{delai_gratuit_jours:int, frais_gardiennage_par_jour:float} $parametres
     * @return array<int, array<string, mixed>>
     */
    private function chiffrerGardiennage(array $colis, array $parametres): array
    {
        $gratuit = $parametres['delai_gratuit_jours'];
        $tarif = $parametres['frais_gardiennage_par_jour'];

        foreach ($colis as &$ligne) {
            $jours = max(0, (int) ($ligne['jours_stockes'] ?? 0));
            $factures = max(0, $jours - $gratuit);

            $ligne['jours_stockes'] = $jours;
            $ligne['jours_factures'] = $factures;
            // Le gardiennage court par colis physique, pas par expédition.
            $ligne['gardiennage_xof'] = round($factures * $tarif * max(1, (int) ($ligne['nombre_colis'] ?? 1)), 2);
        }
        unset($ligne);

        return $colis;
    }

    /**
     * Ajoute le taux de remplissage et un ton d'alerte à chaque rayon.
     *
     * @param array<int, array<string, mixed>> $rayons
     * @return array<int, array<string, mixed>>
     */
    private function classerRayons(array $rayons): array
    {
        foreach ($rayons as &$rayon) {
            $capacite = max(1, (int) ($rayon['capacite_max'] ?? 1));
            $occupe = (int) ($rayon['nb_colis'] ?? 0);
            $taux = (int) round($occupe / $capacite * 100);

            $rayon['taux'] = $taux;
            $rayon['tone'] = match (true) {
                $taux >= 100 => 'danger',
                $taux >= 80 => 'warning',
                default => 'success',
            };
        }
        unset($rayon);

        return $rayons;
    }

    /**
     * @param array<int, array<string, mixed>> $rayons
     * @param array<int, array<string, mixed>> $colis
     * @param array<int, array<string, mixed>> $enSouffrance
     * @return array<int, array<string, mixed>>
     */
    private function kpis(array $rayons, array $colis, array $enSouffrance): array
    {
        $capacite = array_sum(array_map(static fn(array $r): int => (int) $r['capacite_max'], $rayons));
        $occupe = array_sum(array_map(static fn(array $r): int => (int) $r['nb_colis'], $rayons));
        $gardiennage = array_sum(array_map(static fn(array $c): float => (float) $c['gardiennage_xof'], $enSouffrance));
        $poids = array_sum(array_map(static fn(array $c): float => (float) ($c['poids_total'] ?? 0), $colis));

        $taux = $capacite > 0 ? (int) round($occupe / $capacite * 100) : null;

        return [
            [
                'label' => 'Taux d\'occupation',
                // Sans rayon paramétré le taux n'a pas de sens : on le dit,
                // plutôt que d'afficher 0 % qui se lirait comme « magasin vide ».
                'value' => $taux !== null ? $taux . ' %' : '—',
                'meta' => $capacite > 0
                    ? $occupe . ' colis sur ' . $capacite . ' places'
                    : 'Aucun rayon paramétré',
            ],
            [
                'label' => 'Colis en magasin',
                'value' => (string) count($colis),
                'meta' => number_format($poids, 0, ',', ' ') . ' kg stockés',
            ],
            [
                'label' => 'Au-delà du délai gratuit',
                'value' => (string) count($enSouffrance),
                'meta' => 'Colis dont le gardiennage court',
            ],
            [
                'label' => 'Gardiennage à facturer',
                'value' => number_format($gardiennage, 0, ',', ' ') . ' XOF',
                'meta' => 'Cumul dû à ce jour',
            ],
        ];
    }
}
