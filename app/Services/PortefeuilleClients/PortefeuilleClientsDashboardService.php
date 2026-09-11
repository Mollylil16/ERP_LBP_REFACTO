<?php

declare(strict_types=1);

namespace App\Services\PortefeuilleClients;

use App\Repositories\PortefeuilleClients\PortefeuilleClientsDashboardRepository;

/**
 * Classe le portefeuille par valeur et qualifie le risque.
 *
 * La segmentation suit la règle de Pareto appliquée au chiffre d'affaires : les
 * clients qui font les 80 premiers pour cent du CA sont en A, les 15 suivants en
 * B, le reste en C. Elle est recalculée à chaque affichage et par devise, donc
 * elle ne peut pas se désynchroniser de la facturation.
 */
final class PortefeuilleClientsDashboardService extends \App\Services\Shared\AbstractModuleDashboardService implements \App\Services\Shared\ModuleDashboardContract
{
    private const SEUIL_A = 0.80;
    private const SEUIL_B = 0.95;

    /** Au-delà, une facture ouverte est considérée comme à risque. */
    private const ANCIENNETE_RISQUE_JOURS = 90;

    public function __construct(private PortefeuilleClientsDashboardRepository $portefeuille)
    {
        parent::__construct($portefeuille);
    }

    /**
     * @return array<string, mixed>
     */
    public function portefeuille(?int $agenceId, int $mois = 12): array
    {
        $clients = $this->segmenter($this->portefeuille->clients($agenceId, $mois));
        $encours = $this->qualifierRisque($this->portefeuille->encours($agenceId));
        $inactifs = $this->portefeuille->inactifs($agenceId);

        return [
            'mois' => $mois,
            'clients' => $clients,
            'encours' => $encours,
            'inactifs' => $inactifs,
            'parDevise' => $this->totauxParDevise($clients),
            'kpis' => $this->kpis($clients, $encours, $inactifs),
        ];
    }

    /**
     * Attribue A, B ou C à chaque client, par devise.
     *
     * @param array<int, array<string, mixed>> $clients
     * @return array<int, array<string, mixed>>
     */
    private function segmenter(array $clients): array
    {
        $totaux = [];
        foreach ($clients as $client) {
            $devise = (string) $client['devise'];
            $totaux[$devise] = ($totaux[$devise] ?? 0.0) + (float) $client['ca'];
        }

        $cumuls = [];
        foreach ($clients as &$client) {
            $devise = (string) $client['devise'];
            $total = $totaux[$devise] ?? 0.0;

            // Le segment se lit sur le cumul atteint AVANT ce client. Le client
            // qui franchit un seuil appartient à la classe supérieure — c'est la
            // définition usuelle de l'ABC, et la seule qui tienne quand un client
            // fait à lui seul tout le chiffre d'affaires de sa devise : mesuré
            // après, son cumul vaut 100 % et il tomberait en C.
            $avant = $cumuls[$devise] ?? 0.0;
            $part = $total > 0 ? $avant / $total : 0.0;

            $cumuls[$devise] = $avant + (float) $client['ca'];

            $client['segment'] = match (true) {
                $total <= 0 => 'C',
                $part < self::SEUIL_A => 'A',
                $part < self::SEUIL_B => 'B',
                default => 'C',
            };
            $client['part_ca'] = $total > 0 ? round((float) $client['ca'] / $total * 100, 1) : 0.0;
            $client['taux_recouvrement'] = (float) $client['ca'] > 0
                ? (int) round((float) $client['encaisse'] / (float) $client['ca'] * 100)
                : null;
            $client['jours_depuis_derniere'] = $this->joursDepuis((string) $client['derniere_facture']);
        }
        unset($client);

        return $clients;
    }

    /**
     * @param array<int, array<string, mixed>> $encours
     * @return array<int, array<string, mixed>>
     */
    private function qualifierRisque(array $encours): array
    {
        foreach ($encours as &$ligne) {
            $anciennete = (int) $ligne['anciennete_jours'];

            $ligne['tone'] = match (true) {
                $anciennete >= self::ANCIENNETE_RISQUE_JOURS * 2 => 'danger',
                $anciennete >= self::ANCIENNETE_RISQUE_JOURS => 'warning',
                default => 'neutral',
            };
            $ligne['a_risque'] = $anciennete >= self::ANCIENNETE_RISQUE_JOURS;
        }
        unset($ligne);

        return $encours;
    }

    /**
     * Totaux par devise : le seul agrégat de montants qui ait un sens ici.
     *
     * @param array<int, array<string, mixed>> $clients
     * @return array<int, array<string, mixed>>
     */
    private function totauxParDevise(array $clients): array
    {
        $parDevise = [];
        foreach ($clients as $client) {
            $devise = (string) $client['devise'];
            $parDevise[$devise] ??= ['devise' => $devise, 'ca' => 0.0, 'encaisse' => 0.0, 'impaye' => 0.0, 'nb_clients' => 0];
            $parDevise[$devise]['ca'] += (float) $client['ca'];
            $parDevise[$devise]['encaisse'] += (float) $client['encaisse'];
            $parDevise[$devise]['impaye'] += (float) $client['impaye'];
            $parDevise[$devise]['nb_clients']++;
        }

        return array_values($parDevise);
    }

    private function joursDepuis(string $date): ?int
    {
        $horodatage = strtotime($date);
        if ($horodatage === false) {
            return null;
        }

        return (int) floor((time() - $horodatage) / 86400);
    }

    /**
     * @param array<int, array<string, mixed>> $clients
     * @param array<int, array<string, mixed>> $encours
     * @param array<int, array<string, mixed>> $inactifs
     * @return array<int, array<string, mixed>>
     */
    private function kpis(array $clients, array $encours, array $inactifs): array
    {
        // Un client facturé en deux devises apparaît sur deux lignes : on compte
        // les identifiants distincts, pas les lignes.
        $actifs = count(array_unique(array_map(static fn(array $c): int => (int) $c['id'], $clients)));
        $enregistres = $this->portefeuille->nombreClients();

        $segmentA = count(array_unique(array_map(
            static fn(array $c): int => (int) $c['id'],
            array_filter($clients, static fn(array $c): bool => $c['segment'] === 'A')
        )));

        $aRisque = count(array_filter($encours, static fn(array $e): bool => (bool) $e['a_risque']));

        return [
            [
                'label' => 'Clients actifs',
                'value' => (string) $actifs,
                'meta' => $enregistres > 0
                    ? 'sur ' . $enregistres . ' clients enregistrés'
                    : 'Aucun client au fichier',
            ],
            [
                'label' => 'Segment A',
                'value' => (string) $segmentA,
                'meta' => 'Font les 80 % du chiffre d\'affaires',
            ],
            [
                'label' => 'Encours à risque',
                'value' => (string) $aRisque,
                'meta' => 'Impayé de plus de ' . self::ANCIENNETE_RISQUE_JOURS . ' jours',
            ],
            [
                'label' => 'À relancer',
                'value' => (string) count($inactifs),
                'meta' => 'Clients sans facture depuis 90 jours',
            ],
        ];
    }
}
