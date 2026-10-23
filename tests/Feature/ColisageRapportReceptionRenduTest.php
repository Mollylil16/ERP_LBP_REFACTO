<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Colisage\PointageColisService;
use App\View\Components\ColisagePointage;
use App\View\Components\ColisageReceptionRapport;
use Tests\TestCase;

/**
 * Le rapport de réception à l'écran, en PDF et en Excel : rendu vide puis
 * rempli, nombres bruts dans le tableur, heures de Paris, et rien de ce
 * qu'un agent a tapé (commentaire, nom de client) ne passe en HTML.
 */
final class ColisageRapportReceptionRenduTest extends TestCase
{
    private const INJECTION = '<script>alert(1)</script>';

    public function test_l_ecran_vide_se_rend(): void
    {
        $html = ColisageReceptionRapport::page($this->rapport([]));

        self::assertStringContainsString('Aucun départ sur la période', $html);
        self::assertStringContainsString('rapport-reception/pdf?periode=jour&amp;date=2026-10-23', $html);
        self::assertStringContainsString('rapport-reception/excel?periode=jour&amp;date=2026-10-23', $html);
    }

    public function test_un_compte_sans_agence_est_prevenu(): void
    {
        $html = html_entity_decode(ColisageReceptionRapport::page($this->rapport([], ['sans_agence' => true])), ENT_QUOTES);

        self::assertStringContainsString("n'est rattaché à aucune agence", $html);
    }

    public function test_l_ecran_rempli_donne_les_comptes_le_taux_et_le_detail(): void
    {
        $html = html_entity_decode(ColisageReceptionRapport::page($this->rapport([$this->depart()])), ENT_QUOTES);

        self::assertStringContainsString('DEP-20261022-AB12', $html);
        self::assertStringContainsString('50,0 %', $html);
        self::assertStringContainsString('Colis du départ DEP-20261022-AB12', $html);
        self::assertStringContainsString('Carton ouvert', $html);
        self::assertStringContainsString('23/10/2026 14 h 05 à Paris (12 h 05 à Abidjan)', $html, 'Pointé à l’heure de Paris, Abidjan entre parenthèses.');
        self::assertStringContainsString('Agent Paris', $html);
        self::assertStringContainsString("Agence d'envoi", $html, 'La Direction filtre par agence d’envoi.');
    }

    public function test_une_agence_ne_choisit_pas_l_agence_d_envoi(): void
    {
        $html = html_entity_decode(ColisageReceptionRapport::page($this->rapport([], ['peut_choisir' => false])), ENT_QUOTES);

        self::assertStringNotContainsString('name="agence_envoi"', $html);
        self::assertStringContainsString('name="agence_arrivee"', $html);
    }

    public function test_le_pdf_porte_le_nom_du_fichier_et_le_detail(): void
    {
        $html = ColisageReceptionRapport::pdf($this->rapport([$this->depart()]));

        self::assertStringContainsString('<title>rapport_reception_paris-bobigny_2026-10-23</title>', $html);
        self::assertStringContainsString('window.print()', $html);
        self::assertStringContainsString('1 000', $html);
        self::assertStringContainsString('Reçu endommagé', $html);
        self::assertStringContainsString('Non reçu', $html);
    }

    public function test_l_excel_garde_les_nombres_bruts(): void
    {
        $html = ColisageReceptionRapport::excel($this->rapport([$this->depart()]));

        self::assertStringContainsString('x:num="1000">1000<', $html, 'Pas de séparateur de milliers dans le tableur.');
        self::assertStringContainsString('x:num="500">500<', $html);
        self::assertStringContainsString('x:num="50.0">50.0<', $html, 'Le taux en nombre, point décimal.');
        self::assertStringContainsString('Pointé le (heure de l', $html);
        self::assertStringContainsString('<td>23/10/2026 14 h 05</td><td>23/10/2026 12 h 05</td>', $html, 'Heure de Paris puis heure d’Abidjan.');
    }

    public function test_rien_de_saisi_ne_passe_en_html(): void
    {
        $i = self::INJECTION;
        $depart = $this->depart(['reference' => $i, 'agence_depart' => $i, 'agence_arrivee' => $i]);
        $depart['colis'][0] = ['numero_tracking' => $i, 'expediteur' => $i, 'destinataire' => $i, 'commentaire_reception' => $i, 'pointe_par' => $i] + $depart['colis'][0];
        $rapport = $this->rapport([$depart], ['libelles_filtres' => [$i], 'edite_par' => $i, 'options_departs' => [['id' => 7, 'libelle' => $i]]]);

        $reception = ColisagePointage::receptionPage(['agences' => [], 'agence_id' => 3400, 'peut_choisir' => false, 'departs' => [$depart]]);

        foreach (['écran' => ColisageReceptionRapport::page($rapport), 'PDF' => ColisageReceptionRapport::pdf($rapport),
            'Excel' => ColisageReceptionRapport::excel($rapport), 'réception' => $reception] as $nom => $html) {
            self::assertStringNotContainsString($i, $html, "Le rendu {$nom} laisse passer du HTML.");
        }
    }

    /**
     * @param array<int, array<string, mixed>> $departs
     * @param array<string, mixed> $plus
     * @return array<string, mixed>
     */
    private function rapport(array $departs, array $plus = []): array
    {
        return $plus + [
            'filtres' => PointageColisService::filtresRapport(['periode' => 'jour', 'date' => '2026-10-23'], '2026-10-23'),
            'fuseau' => 'Europe/Paris',
            'departs' => $departs,
            'options_departs' => [],
            'totaux' => PointageColisService::totauxRapport($departs),
            'edite_le' => '2026-10-23 16:00:00',
            'nom_fichier' => 'rapport_reception_paris-bobigny_2026-10-23',
            'agences' => [['id' => 3400, 'name' => 'Paris Bobigny'], ['id' => 3403, 'name' => 'Agence Abobo Dokui']],
            'peut_choisir' => true,
            'libelles_filtres' => ['Agence : Paris Bobigny'],
            'edite_par' => 'Admin',
        ];
    }

    /**
     * @param array<string, mixed> $plus
     * @return array<string, mixed>
     */
    private function depart(array $plus = []): array
    {
        $service = new PointageColisService(new \PDO('sqlite::memory:'), new \App\Repositories\Colisage\PointageColisRepository(new \PDO('sqlite::memory:')));
        $depart = $service->enrichir($plus + [
            'id' => 7, 'reference' => 'DEP-20261022-AB12', 'statut' => 'ARRIVE', 'est_reprise' => 0,
            'agence_depart_id' => 3403, 'agence_arrivee_id' => 3400, 'agence_depart' => 'Agence Abobo Dokui', 'agence_arrivee' => 'Paris Bobigny',
            'date_depart' => '2026-10-22 16:20:00', 'date_premiere_reception' => '2026-10-23 08:00:00',
            'fuseau_depart' => 'Africa/Abidjan', 'fuseau_arrivee' => 'Europe/Paris',
            'envoyes' => 1000, 'recus' => 500, 'endommages' => 20, 'non_recus' => 300,
        ], new \DateTimeImmutable('2026-10-23 16:00:00'));

        $depart['colis'] = [
            ['id' => 1, 'numero_tracking' => 'LB-FR-001', 'statut' => 'arrive', 'etat' => 'ENDOMMAGE', 'etat_reception' => 'ENDOMMAGE',
                'commentaire_reception' => 'Carton ouvert', 'date_reception' => '2026-10-23 12:05:00', 'pointe_le' => '2026-10-23 12:05:00',
                'pointe_par' => 'Agent Paris', 'expediteur' => 'Client Abidjan', 'destinataire' => 'Client Paris', 'nombre_colis' => 1, 'poids_total' => 5],
            ['id' => 2, 'numero_tracking' => 'LB-FR-002', 'statut' => 'en_transit', 'etat' => 'NON_RECU', 'etat_reception' => 'NON_RECU',
                'commentaire_reception' => null, 'date_reception' => null, 'pointe_le' => '2026-10-23 12:06:00',
                'pointe_par' => 'Agent Paris', 'expediteur' => 'Client Abidjan', 'destinataire' => 'Client Paris', 'nombre_colis' => 1, 'poids_total' => 5],
        ];

        return $depart;
    }
}
