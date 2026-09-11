<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Repositories\Entrepots\EntrepotsDashboardRepository;
use App\Repositories\PortefeuilleClients\PortefeuilleClientsDashboardRepository;
use App\Repositories\TransitDouane\TransitDouaneDashboardRepository;
use App\Services\Entrepots\EntrepotsDashboardService;
use App\Services\PortefeuilleClients\PortefeuilleClientsDashboardService;
use App\Services\TransitDouane\TransitDouaneDashboardService;
use Tests\TestCase;

/**
 * Les règles de calcul des modules métier.
 *
 * Les dépôts sont remplacés par des doublures : ce qui est testé ici est la
 * règle (gardiennage, segmentation, dérive de coût), pas le SQL. Les doublures
 * n'appellent pas le constructeur parent, donc la connexion PDO n'est jamais
 * touchée.
 */
final class ModulesMetierTest extends TestCase
{
    // ------------------------------------------------------------------
    // Entrepôts : gardiennage
    // ------------------------------------------------------------------

    public function test_le_gardiennage_ne_court_qu_apres_le_delai_gratuit(): void
    {
        $service = new EntrepotsDashboardService(new class extends EntrepotsDashboardRepository {
            public function __construct() {}
            public function parametres(?int $agenceId): array
            {
                return ['delai_gratuit_jours' => 7, 'frais_gardiennage_par_jour' => 500.0];
            }
            public function rayons(?int $agenceId): array { return []; }
            public function colisStockes(?int $agenceId, int $limite = 200): array
            {
                return [
                    ['jours_stockes' => 3, 'nombre_colis' => 1, 'poids_total' => 10],
                    ['jours_stockes' => 7, 'nombre_colis' => 1, 'poids_total' => 10],
                    ['jours_stockes' => 10, 'nombre_colis' => 1, 'poids_total' => 10],
                    ['jours_stockes' => 10, 'nombre_colis' => 4, 'poids_total' => 40],
                ];
            }
            public function mouvementsRecents(?int $agenceId, int $limite = 25): array { return []; }
            public function inventaires(?int $agenceId, int $limite = 10): array { return []; }
        });

        $donnees = $service->occupation(null);
        $colis = $donnees['colis'];

        self::assertSame(0.0, $colis[0]['gardiennage_xof'], 'Trois jours de stockage restent gratuits.');
        self::assertSame(0.0, $colis[1]['gardiennage_xof'], 'Le jour du délai lui-même est encore gratuit.');
        self::assertSame(1500.0, $colis[2]['gardiennage_xof'], '3 jours facturés x 500 XOF.');
        self::assertSame(6000.0, $colis[3]['gardiennage_xof'], 'Le tarif court par colis physique : 3 x 500 x 4.');

        self::assertCount(2, $donnees['enSouffrance'], 'Seuls les colis réellement facturables sont en souffrance.');
    }

    public function test_le_taux_d_occupation_n_invente_pas_de_valeur_sans_rayon(): void
    {
        $service = new EntrepotsDashboardService(new class extends EntrepotsDashboardRepository {
            public function __construct() {}
            public function parametres(?int $agenceId): array
            {
                return ['delai_gratuit_jours' => 7, 'frais_gardiennage_par_jour' => 500.0];
            }
            public function rayons(?int $agenceId): array { return []; }
            public function colisStockes(?int $agenceId, int $limite = 200): array { return []; }
            public function mouvementsRecents(?int $agenceId, int $limite = 25): array { return []; }
            public function inventaires(?int $agenceId, int $limite = 10): array { return []; }
        });

        $kpis = $service->occupation(null)['kpis'];

        self::assertSame('—', $kpis[0]['value'], 'Sans rayon, « 0 % » se lirait comme un magasin vide.');
        self::assertSame('Aucun rayon paramétré', $kpis[0]['meta']);
    }

    public function test_le_remplissage_alerte_au_dela_de_la_capacite(): void
    {
        $service = new EntrepotsDashboardService(new class extends EntrepotsDashboardRepository {
            public function __construct() {}
            public function parametres(?int $agenceId): array
            {
                return ['delai_gratuit_jours' => 7, 'frais_gardiennage_par_jour' => 500.0];
            }
            public function rayons(?int $agenceId): array
            {
                return [
                    ['capacite_max' => 50, 'nb_colis' => 20, 'poids_kg' => 0],
                    ['capacite_max' => 50, 'nb_colis' => 45, 'poids_kg' => 0],
                    ['capacite_max' => 50, 'nb_colis' => 60, 'poids_kg' => 0],
                ];
            }
            public function colisStockes(?int $agenceId, int $limite = 200): array { return []; }
            public function mouvementsRecents(?int $agenceId, int $limite = 25): array { return []; }
            public function inventaires(?int $agenceId, int $limite = 10): array { return []; }
        });

        $rayons = $service->occupation(null)['rayons'];

        self::assertSame(['success', 'warning', 'danger'], array_column($rayons, 'tone'));
        self::assertSame([40, 90, 120], array_column($rayons, 'taux'));
    }

    // ------------------------------------------------------------------
    // Portefeuille clients : segmentation ABC
    // ------------------------------------------------------------------

    public function test_la_segmentation_suit_le_cumul_du_chiffre_d_affaires(): void
    {
        $service = $this->portefeuille([
            $this->client(1, 'Gros', 800_000, 'XOF'),
            $this->client(2, 'Moyen', 150_000, 'XOF'),
            $this->client(3, 'Petit', 40_000, 'XOF'),
            $this->client(4, 'Miette', 10_000, 'XOF'),
        ]);

        $segments = array_column($service->portefeuille(null)['clients'], 'segment', 'client');

        self::assertSame('A', $segments['Gros'], '80 % du CA à lui seul.');
        self::assertSame('B', $segments['Moyen'], 'Fait passer le cumul de 80 % à 95 %.');
        self::assertSame('C', $segments['Petit']);
        self::assertSame('C', $segments['Miette']);
    }

    public function test_les_devises_sont_segmentees_separement(): void
    {
        // Un petit client en EUR ne doit pas être écrasé par les montants XOF :
        // 500 EUR pèsent plus que 500 XOF, et les deux ne s'additionnent pas.
        $service = $this->portefeuille([
            $this->client(1, 'Gros XOF', 1_000_000, 'XOF'),
            $this->client(2, 'Seul EUR', 500, 'EUR'),
        ]);

        $segments = array_column($service->portefeuille(null)['clients'], 'segment', 'client');

        self::assertSame('A', $segments['Gros XOF']);
        self::assertSame('A', $segments['Seul EUR'], 'Seul client de sa devise, il en fait 100 % du CA.');
    }

    public function test_un_client_facture_en_deux_devises_n_est_compte_qu_une_fois(): void
    {
        $service = $this->portefeuille([
            $this->client(7, 'Bicéphale', 900_000, 'XOF'),
            $this->client(7, 'Bicéphale', 1_200, 'EUR'),
        ]);

        $kpis = $service->portefeuille(null)['kpis'];

        self::assertSame('1', $kpis[0]['value'], 'Deux lignes, un seul client.');
    }

    public function test_le_portefeuille_vide_ne_produit_pas_de_division_par_zero(): void
    {
        $service = $this->portefeuille([]);

        $donnees = $service->portefeuille(null);

        self::assertSame('0', $donnees['kpis'][0]['value']);
        self::assertSame([], $donnees['parDevise']);
    }

    // ------------------------------------------------------------------
    // Transit douane : dérive du coût au kilo
    // ------------------------------------------------------------------

    public function test_un_lot_trop_cher_est_signale_par_rapport_a_son_trajet(): void
    {
        $service = new TransitDouaneDashboardService(new class extends TransitDouaneDashboardRepository {
            public function __construct() {}
            public function lots(int $limite = 100): array
            {
                return [
                    // 100 000 / 100 kg = 1 000 XOF/kg, soit le double de la moyenne.
                    $this->lot('LOT-CHER', 100_000, 100),
                    // 45 000 / 100 kg = 450 XOF/kg, dans la norme.
                    $this->lot('LOT-NORMAL', 45_000, 100),
                ];
            }
            public function coutParTrajet(): array
            {
                return [[
                    'trajet_code' => 'LB-FR',
                    'trajet_libelle' => 'Ligne France',
                    'nb_lots' => 10,
                    'poids_kg' => 1000.0,
                    'douane_xof' => 200_000.0,
                    'fret_xof' => 250_000.0,
                    'manutention_xof' => 50_000.0,
                    'total_xof' => 500_000.0,
                ]];
            }
            public function colisEnTransit(?int $agenceId): array { return []; }

            /** @return array<string, mixed> */
            private function lot(string $reference, float $total, float $poids): array
            {
                return [
                    'reference_lot' => $reference,
                    'trajet_code' => 'LB-FR',
                    'trajet_libelle' => 'Ligne France',
                    'frais_douane_xof' => $total / 2,
                    'frais_fret_xof' => $total / 2,
                    'frais_manutention_xof' => 0.0,
                    'poids_total_kg' => $poids,
                    'cout_kg_saisi' => $total / $poids,
                    'statut' => 'VALIDÉ',
                    'total_xof' => $total,
                ];
            }
        });

        $lots = array_column($service->dossiers(null)['lots'], null, 'reference_lot');

        self::assertSame(1000.0, $lots['LOT-CHER']['cout_kg']);
        self::assertSame(100.0, (float) $lots['LOT-CHER']['ecart_moyenne'], 'Deux fois la moyenne de 500 XOF/kg.');
        self::assertTrue($lots['LOT-CHER']['derive']);

        self::assertSame(450.0, $lots['LOT-NORMAL']['cout_kg']);
        self::assertFalse($lots['LOT-NORMAL']['derive'], '10 % d\'écart reste sous le seuil de 25 %.');
    }

    public function test_un_lot_sans_poids_ne_produit_pas_de_cout_au_kilo(): void
    {
        $service = new TransitDouaneDashboardService(new class extends TransitDouaneDashboardRepository {
            public function __construct() {}
            public function lots(int $limite = 100): array
            {
                return [[
                    'reference_lot' => 'LOT-SANS-POIDS',
                    'trajet_code' => 'LB-FR',
                    'frais_douane_xof' => 50_000.0,
                    'frais_fret_xof' => 0.0,
                    'frais_manutention_xof' => 0.0,
                    'poids_total_kg' => 0.0,
                    'cout_kg_saisi' => 0.0,
                    'statut' => 'SIMULATION',
                    'total_xof' => 50_000.0,
                ]];
            }
            public function coutParTrajet(): array { return []; }
            public function colisEnTransit(?int $agenceId): array { return []; }
        });

        $donnees = $service->dossiers(null);

        self::assertNull($donnees['lots'][0]['cout_kg'], 'Diviser par un poids nul n\'a pas de sens.');
        self::assertNull($donnees['lots'][0]['ecart_moyenne']);
        self::assertSame('—', $donnees['kpis'][2]['value']);
    }

    // ------------------------------------------------------------------
    // Doublures
    // ------------------------------------------------------------------

    /**
     * @param array<int, array<string, mixed>> $clients
     */
    private function portefeuille(array $clients): PortefeuilleClientsDashboardService
    {
        return new PortefeuilleClientsDashboardService(
            new class ($clients) extends PortefeuilleClientsDashboardRepository {
                /** @param array<int, array<string, mixed>> $clients */
                public function __construct(private array $clients = []) {}
                public function clients(?int $agenceId, int $mois = 12, int $limite = 300): array
                {
                    return $this->clients;
                }
                public function inactifs(?int $agenceId, int $joursSansActivite = 90, int $limite = 100): array
                {
                    return [];
                }
                public function encours(?int $agenceId, int $limite = 100): array { return []; }
                public function nombreClients(): int { return count($this->clients); }
            }
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function client(int $id, string $nom, float $ca, string $devise): array
    {
        return [
            'id' => $id,
            'client' => $nom,
            'phone' => null,
            'email' => null,
            'type' => 'standard',
            'client_depuis' => '2025-01-01 00:00:00',
            'devise' => $devise,
            'nb_factures' => 1,
            'ca' => $ca,
            'encaisse' => $ca,
            'impaye' => 0.0,
            'derniere_facture' => date('Y-m-d'),
            'premiere_facture' => date('Y-m-d'),
            'nb_impayees' => 0,
        ];
    }
}
