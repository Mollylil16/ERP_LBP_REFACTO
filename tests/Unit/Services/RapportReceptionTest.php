<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Repositories\Colisage\PointageColisRepository;
use App\Services\Colisage\PointageColisService;
use App\Services\Colisage\ReceptionColisRegles as Regles;
use App\Services\Colisage\ResumeReceptionService;
use Tests\Support\BaseColisSqlite;
use Tests\TestCase;

/**
 * Le rapport de réception (comptes, taux, périodes, droits par agence) et le
 * résumé du soir dans la messagerie (texte, fenêtre, idempotence).
 */
final class RapportReceptionTest extends TestCase
{
    private BaseColisSqlite $base;
    private PointageColisService $service;
    private PointageColisRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = new BaseColisSqlite();
        $this->repo = new PointageColisRepository($this->base->pdo);
        $this->service = new PointageColisService($this->base->pdo, $this->repo);
    }

    // ------------------------------------------------------------------
    // Rapport
    // ------------------------------------------------------------------

    public function test_le_taux_de_reception(): void
    {
        self::assertSame(50.0, PointageColisService::taux(500, 1000));
        self::assertSame(99.9, PointageColisService::taux(999, 1000), 'Jamais 100 % tant qu’il manque un colis.');
        self::assertSame(100.0, PointageColisService::taux(1000, 1000));
        self::assertSame(33.3, PointageColisService::taux(1, 3));
        self::assertSame(0.0, PointageColisService::taux(0, 0));
    }

    public function test_les_comptes_d_un_depart(): void
    {
        $d = $this->base->depart(10);
        $c = $d['colis'];
        foreach ([0, 1, 2, 3] as $i) {
            $this->service->pointer($c[$i], Regles::RECU, 3400, 10);
        }
        $this->service->pointer($c[4], Regles::ENDOMMAGE, 3400, 10, 'Carton déchiré');
        $this->service->pointer($c[5], Regles::NON_RECU, 3400, 10);
        $this->service->pointer($c[6], Regles::NON_RECU, 3400, 10);

        $rapport = $this->service->rapport($this->filtres(['periode' => 'jour', 'date' => '2026-10-23']), null, true);

        self::assertCount(1, $rapport['departs']);
        $depart = $rapport['departs'][0];
        self::assertSame([10, 5, 4, 1, 2, 3, 2, 50.0], [
            $depart['envoyes'], $depart['recus'], $depart['recus_intacts'], $depart['endommages'],
            $depart['non_recus'], $depart['en_attente'], $depart['manquants'], $depart['taux'],
        ]);
        self::assertCount(10, $depart['colis']);
        $endommage = array_values(array_filter($depart['colis'], static fn (array $l): bool => (int) $l['id'] === $c[4]))[0];
        self::assertSame(['ENDOMMAGE', 'Carton déchiré', 'Agent Paris', '2026-10-23 10:00:00'], [
            $endommage['etat'], $endommage['commentaire_reception'], $endommage['pointe_par'], $endommage['pointe_le'],
        ]);

        self::assertSame(['departs' => 1, 'attendus' => 10, 'recus' => 5, 'endommages' => 1, 'non_recus' => 2, 'en_attente' => 3, 'manquants' => 2, 'taux' => 50.0], $rapport['totaux']);
    }

    public function test_une_agence_ne_voit_que_ses_departs_et_ses_receptions(): void
    {
        $versParis = $this->base->depart(2, 3403, 3400);
        $versDakar = $this->base->depart(3, 3403, 3401);
        $filtres = $this->filtres(['periode' => 'jour', 'date' => '2026-10-23']);

        $ids = static fn (array $r): array => array_map(static fn (array $d): int => (int) $d['id'], $r['departs']);

        self::assertEqualsCanonicalizing([$versParis['id'], $versDakar['id']], $ids($this->service->rapport($filtres, null, false)), 'La Direction voit tout.');
        self::assertEqualsCanonicalizing([$versParis['id'], $versDakar['id']], $ids($this->service->rapport($filtres, 3403, false)), 'L’agence d’envoi voit ses départs.');
        self::assertSame([$versParis['id']], $ids($this->service->rapport($filtres, 3400, false)), 'Paris ne voit que ce qui lui arrive.');

        // Choisir un départ d'une autre agence ne l'ouvre pas.
        $autre = $this->filtres(['periode' => 'jour', 'date' => '2026-10-23', 'depart' => (string) $versDakar['id']]);
        self::assertSame([], $this->service->rapport($autre, 3400, false)['departs']);
        self::assertSame([$versDakar['id']], $ids($this->service->rapport($autre, 3403, false)));

        // La Direction filtre par agence d'arrivée.
        $dakar = $this->filtres(['periode' => 'jour', 'date' => '2026-10-23', 'agence_arrivee' => '3401']);
        self::assertSame([$versDakar['id']], $ids($this->service->rapport($dakar, null, false)));
    }

    public function test_un_depart_ancien_entre_dans_le_rapport_du_jour_ou_il_est_pointe(): void
    {
        $this->base->maintenant = '2026-10-01 09:00:00';
        $d = $this->base->depart(2);

        $this->base->maintenant = '2026-10-23 09:00:00';
        $filtres = $this->filtres(['periode' => 'jour', 'date' => '2026-10-23']);
        self::assertSame([], $this->service->rapport($filtres, null, false)['departs'], 'Parti le 1er, rien pointé le 23 : absent.');

        $this->service->pointer($d['colis'][0], Regles::RECU, 3400, 10);
        self::assertCount(1, $this->service->rapport($filtres, null, false)['departs']);
    }

    public function test_la_journee_de_paris_va_de_minuit_a_minuit_a_paris(): void
    {
        $this->base->maintenant = '2026-10-01 09:00:00';
        $d = $this->base->depart(1);

        // 22 h 30 à Abidjan le 22 : 0 h 30 le 23 à Paris.
        $this->base->maintenant = '2026-10-22 22:30:00';
        $this->service->pointer($d['colis'][0], Regles::RECU, 3400, 10);

        $le23 = $this->service->rapport($this->filtres(['periode' => 'jour', 'date' => '2026-10-23', 'agence_arrivee' => '3400']), null, false);
        self::assertCount(1, $le23['departs']);
        self::assertSame('Europe/Paris', $le23['fuseau']);

        $le22 = $this->service->rapport($this->filtres(['periode' => 'jour', 'date' => '2026-10-22', 'agence_arrivee' => '3400']), null, false);
        self::assertSame([], $le22['departs']);
    }

    public function test_les_periodes(): void
    {
        $semaine = PointageColisService::filtresRapport(['periode' => 'semaine', 'date' => '2026-10-23'], '2026-10-23');
        self::assertSame(['2026-10-19', '2026-10-25'], [$semaine['du'], $semaine['au']], 'Du lundi au dimanche.');

        $jour = PointageColisService::filtresRapport([], '2026-10-23');
        self::assertSame(['jour', '2026-10-23', '2026-10-23'], [$jour['periode'], $jour['du'], $jour['au']]);

        $inverse = PointageColisService::filtresRapport(['periode' => 'periode', 'du' => '2026-10-20', 'au' => '2026-10-01'], '2026-10-23');
        self::assertSame(['2026-10-01', '2026-10-20'], [$inverse['du'], $inverse['au']]);

        $abime = PointageColisService::filtresRapport(['periode' => 'mois', 'date' => '23/10', 'agence_arrivee' => 'x', 'depart' => '-4'], '2026-10-23');
        self::assertSame(['jour', '2026-10-23', null, null], [$abime['periode'], $abime['date'], $abime['agence_arrivee'], $abime['depart']]);
    }

    public function test_le_nom_du_fichier_porte_l_agence_et_la_periode(): void
    {
        self::assertSame('rapport_reception_paris-17-chemin-des-vignes-93000-bobigny_2026-10-19_2026-10-25',
            PointageColisService::nomFichierRapport('Paris 17 chemin des Vignes 93000 Bobigny', '2026-10-19', '2026-10-25'));
        self::assertSame('rapport_reception_agence-adjame-pharmacie-latin_2026-10-23',
            PointageColisService::nomFichierRapport('Agence Adjamé Pharmacie Latin', '2026-10-23', '2026-10-23'));
        self::assertSame('rapport_reception_toutes-agences_2026-10-23', PointageColisService::nomFichierRapport('', '2026-10-23', '2026-10-23'));

        $rapport = $this->service->rapport($this->filtres(['periode' => 'jour', 'date' => '2026-10-23']), 3400, false);
        self::assertSame('rapport_reception_paris-bobigny_2026-10-23', $rapport['nom_fichier']);
    }

    // ------------------------------------------------------------------
    // Résumé du soir
    // ------------------------------------------------------------------

    public function test_le_texte_du_resume(): void
    {
        $depart = $this->service->enrichir([
            'id' => 7, 'reference' => 'DEP-20261022-AB12', 'agence_arrivee' => 'Paris Bobigny',
            'envoyes' => 1000, 'recus' => 500, 'endommages' => 20, 'non_recus' => 300, 'date_premiere_reception' => '2026-10-23 08:00:00',
        ], new \DateTimeImmutable('2026-10-23 18:00:00'));

        $texte = ResumeReceptionService::texte($depart, 'https://erp.test/colisage/rapport-reception?depart=7');

        self::assertSame(
            "Paris Bobigny a reçu 500 colis sur 1\u{202F}000 du départ n° DEP-20261022-AB12 : 480 reçus en bon état, 20 endommagés, "
            . '300 déclarés non reçus, 200 encore en attente. Taux de réception : 50,0 %. 300 colis manquants. '
            . 'Rapport : https://erp.test/colisage/rapport-reception?depart=7',
            $texte
        );
    }

    public function test_la_fenetre_va_de_18_heures_la_veille_a_18_heures(): void
    {
        self::assertSame(['2026-10-22 18:00:00', '2026-10-23 18:00:00'], ResumeReceptionService::fenetre('2026-10-23'));
    }

    public function test_un_seul_resume_par_depart_et_par_jour(): void
    {
        $this->base->maintenant = '2026-10-23 09:00:00';
        $d = $this->base->depart(3);
        $this->service->pointer($d['colis'][0], Regles::RECU, 3400, 10);
        $this->service->pointer($d['colis'][1], Regles::NON_RECU, 3400, 10);
        $this->base->maintenant = '2026-10-23 18:00:00';

        $publies = [];
        $resume = $this->resume(function (int $agence, string $texte) use (&$publies): array {
            $publies[] = [$agence, $texte];

            return ['conversation_id' => 21, 'message_id' => 100 + count($publies)];
        });

        $premier = $resume->executer('2026-10-23', 'https://erp.test');
        self::assertSame([1, 1, 0], [$premier['departs'], $premier['envoyes'], $premier['deja_faits']]);
        self::assertSame(3403, $publies[0][0], 'Le résumé part dans le groupe de l’agence d’envoi.');
        self::assertStringContainsString('a reçu 1 colis sur 3', $publies[0][1]);
        self::assertStringContainsString('periode=jour&date=2026-10-23&depart=' . $d['id'], $publies[0][1]);

        $second = $resume->executer('2026-10-23', 'https://erp.test');
        self::assertSame([1, 0, 1], [$second['departs'], $second['envoyes'], $second['deja_faits']]);
        self::assertCount(1, $publies, 'Relancée, la commande ne renvoie rien.');

        $ligne = $this->base->pdo->query('SELECT conversation_id, message_id FROM lbp_colis_resumes_reception')->fetch();
        self::assertSame([21, 101], [(int) $ligne['conversation_id'], (int) $ligne['message_id']]);
    }

    public function test_l_essai_n_envoie_ni_ne_reserve_rien(): void
    {
        $d = $this->base->depart(1);
        $this->service->pointer($d['colis'][0], Regles::RECU, 3400, 10);
        $this->base->maintenant = '2026-10-23 18:00:00';

        $resume = $this->resume(static function (): array {
            throw new \LogicException('Un essai ne publie pas.');
        });
        $bilan = $resume->executer('2026-10-23', 'https://erp.test', true);

        self::assertSame('a_envoyer', $bilan['messages'][0]['statut']);
        self::assertSame(0, (int) $this->base->pdo->query('SELECT COUNT(*) FROM lbp_colis_resumes_reception')->fetchColumn());
    }

    public function test_un_message_qui_ne_part_pas_rend_sa_place(): void
    {
        $d = $this->base->depart(1);
        $this->service->pointer($d['colis'][0], Regles::RECU, 3400, 10);
        $this->base->maintenant = '2026-10-23 18:00:00';

        $panne = $this->resume(static function (): array {
            throw new \RuntimeException('base de la messagerie indisponible');
        })->executer('2026-10-23', 'https://erp.test');
        self::assertSame(1, $panne['erreurs']);

        $sansGroupe = $this->resume(static fn (): ?array => null)->executer('2026-10-23', 'https://erp.test');
        self::assertSame(1, $sansGroupe['sans_groupe']);

        $reprise = $this->resume(static fn (): array => ['conversation_id' => 1, 'message_id' => 2])->executer('2026-10-23', 'https://erp.test');
        self::assertSame(1, $reprise['envoyes'], 'Le lancement suivant réessaie.');
    }

    public function test_les_pointages_apres_18_heures_vont_au_resume_du_lendemain(): void
    {
        $d = $this->base->depart(2);
        $this->base->maintenant = '2026-10-23 18:30:00';
        $this->service->pointer($d['colis'][0], Regles::RECU, 3400, 10);

        $publies = 0;
        $publier = function () use (&$publies): array {
            $publies++;

            return ['conversation_id' => 1, 'message_id' => $publies];
        };

        self::assertSame(0, $this->resume($publier)->executer('2026-10-23', 'https://erp.test')['departs']);
        $this->base->maintenant = '2026-10-24 18:00:00';
        self::assertSame(1, $this->resume($publier)->executer('2026-10-24', 'https://erp.test')['envoyes']);
    }

    public function test_un_depart_qui_passe_en_manquants_a_son_resume_meme_sans_pointage(): void
    {
        $this->base->maintenant = '2026-10-22 08:00:00';
        $d = $this->base->depart(3);
        $this->service->pointer($d['colis'][0], Regles::RECU, 3400, 10);

        $textes = [];
        $publier = function (int $agence, string $texte) use (&$textes): array {
            $textes[] = $texte;

            return ['conversation_id' => 1, 'message_id' => count($textes)];
        };

        // Le 22 au soir : pointé dans la journée.
        $this->base->maintenant = '2026-10-22 18:00:00';
        $this->resume($publier)->executer('2026-10-22', 'https://erp.test');
        // Le 23 au soir : aucun pointage, mais le délai est échu à 8 h.
        $this->base->maintenant = '2026-10-23 18:00:00';
        $bilan = $this->resume($publier)->executer('2026-10-23', 'https://erp.test');

        self::assertSame(1, $bilan['envoyes']);
        self::assertStringContainsString('2 colis manquants', $textes[1]);
    }

    /** @param array<string, string> $entree */
    private function filtres(array $entree): array
    {
        return PointageColisService::filtresRapport($entree, '2026-10-23');
    }

    private function resume(\Closure $publier): ResumeReceptionService
    {
        return new ResumeReceptionService($this->repo, $this->service, $publier);
    }
}
