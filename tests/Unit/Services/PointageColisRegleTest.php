<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Repositories\Colisage\PointageColisRepository;
use App\Services\Colisage\PointageColisService;
use DateTimeImmutable;
use PDO;
use Tests\TestCase;

/**
 * La règle des manquants, telle qu'arrêtée avec la direction le 14/09/2026.
 *
 * L'arrivée d'un départ, c'est son premier colis coché. Tant que 24 heures ne
 * sont pas écoulées depuis, un colis décoché est simplement « attendu ». Passé
 * ce délai, il est manquant. Un départ dont aucun colis n'a été coché est « en
 * route », quel que soit son âge : un envoi par bateau prend des semaines.
 */
final class PointageColisRegleTest extends TestCase
{
    private PointageColisService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // enrichir() ne lit pas la base : une connexion vide suffit.
        $pdo = new PDO('sqlite::memory:');
        $this->service = new PointageColisService($pdo, new PointageColisRepository($pdo));
    }

    public function test_un_depart_sans_colis_coche_est_en_route_meme_ancien(): void
    {
        $depart = $this->service->enrichir(
            ['envoyes' => 150, 'recus' => 0, 'date_premiere_reception' => null],
            new DateTimeImmutable('2026-12-31 12:00:00')
        );

        self::assertSame(PointageColisService::ETAT_EN_ROUTE, $depart['etat']);
        self::assertSame(0, $depart['manquants']);
        self::assertSame(150, $depart['restants']);
    }

    public function test_avant_24_heures_les_colis_decoches_sont_seulement_attendus(): void
    {
        $depart = $this->service->enrichir(
            ['envoyes' => 150, 'recus' => 120, 'date_premiere_reception' => '2026-09-15 08:00:00'],
            new DateTimeImmutable('2026-09-16 07:59:59')
        );

        self::assertSame(PointageColisService::ETAT_EN_COURS, $depart['etat']);
        self::assertSame(0, $depart['manquants']);
        self::assertSame(30, $depart['restants']);
        self::assertSame('2026-09-16 08:00:00', $depart['echeance']);
    }

    public function test_a_24_heures_pile_les_colis_decoches_deviennent_manquants(): void
    {
        $depart = $this->service->enrichir(
            ['envoyes' => 150, 'recus' => 120, 'date_premiere_reception' => '2026-09-15 08:00:00'],
            new DateTimeImmutable('2026-09-16 08:00:00')
        );

        self::assertSame(PointageColisService::ETAT_MANQUANTS, $depart['etat']);
        self::assertSame(30, $depart['manquants']);
    }

    public function test_un_depart_entierement_recu_est_complet_meme_apres_le_delai(): void
    {
        $depart = $this->service->enrichir(
            ['envoyes' => 150, 'recus' => 150, 'date_premiere_reception' => '2026-09-01 08:00:00'],
            new DateTimeImmutable('2026-09-20 08:00:00')
        );

        self::assertSame(PointageColisService::ETAT_COMPLET, $depart['etat']);
        self::assertSame(0, $depart['manquants']);
    }

    /**
     * Un colis retiré compte comme reçu : sans ce plafond, un départ pourrait
     * afficher plus de reçus que d'envoyés.
     */
    public function test_les_recus_ne_depassent_jamais_les_envoyes(): void
    {
        $depart = $this->service->enrichir(
            ['envoyes' => 3, 'recus' => 5, 'date_premiere_reception' => '2026-09-15 08:00:00'],
            new DateTimeImmutable('2026-09-15 09:00:00')
        );

        self::assertSame(3, $depart['recus']);
        self::assertSame(0, $depart['restants']);
    }

    public function test_le_delai_des_manquants_est_de_24_heures(): void
    {
        self::assertSame(24, PointageColisService::DELAI_MANQUANT_HEURES);
    }
}
