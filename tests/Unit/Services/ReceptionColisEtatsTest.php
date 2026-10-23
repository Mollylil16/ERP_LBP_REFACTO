<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Repositories\Colisage\PointageColisRepository;
use App\Services\Colisage\PointageColisService;
use App\Services\Colisage\ReceptionColisRegles as Regles;
use Tests\Support\BaseColisSqlite;
use Tests\TestCase;

/**
 * Les trois choix par colis à l'arrivée (octobre 2026), sur les vraies
 * requêtes du dépôt : Reçu, Reçu endommagé, Non reçu, avec commentaire ;
 * chaque changement tracé ; « Non reçu » manquant tout de suite ; la règle
 * des 24 heures intacte pour les colis jamais pointés ; une erreur se corrige.
 */
final class ReceptionColisEtatsTest extends TestCase
{
    private BaseColisSqlite $base;
    private PointageColisService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = new BaseColisSqlite();
        $this->service = new PointageColisService($this->base->pdo, new PointageColisRepository($this->base->pdo));
    }

    public function test_les_trois_choix_sont_enregistres_avec_leur_commentaire(): void
    {
        $d = $this->base->depart(3);
        [$a, $b, $c] = $d['colis'];

        self::assertTrue($this->service->pointer($a, Regles::RECU, 3400, 10)['ok']);
        self::assertTrue($this->service->pointer($b, Regles::ENDOMMAGE, 3400, 10, 'Carton ouvert')['ok']);
        $r = $this->service->pointer($c, Regles::NON_RECU, 3400, 10, 'Absent de la palette');
        self::assertTrue($r['ok']);

        self::assertSame('RECU', $this->base->colis($a)['etat_reception']);
        self::assertSame('arrive', $this->base->colis($a)['statut']);
        self::assertNotNull($this->base->colis($a)['date_reception']);

        self::assertSame('ENDOMMAGE', $this->base->colis($b)['etat_reception']);
        self::assertSame('Carton ouvert', $this->base->colis($b)['commentaire_reception']);
        self::assertNotNull($this->base->colis($b)['date_reception'], 'Un colis endommagé est en main.');

        self::assertSame('NON_RECU', $this->base->colis($c)['etat_reception']);
        self::assertNull($this->base->colis($c)['date_reception']);
        self::assertSame('en_transit', $this->base->colis($c)['statut']);
        self::assertSame(10, (int) $this->base->colis($c)['pointe_par_id']);
        self::assertSame($this->base->maintenant, $this->base->colis($c)['pointe_le']);

        $depart = $r['depart'];
        self::assertSame(2, $depart['recus']);
        self::assertSame(1, $depart['endommages']);
        self::assertSame(1, $depart['recus_intacts']);
        self::assertSame(1, $depart['non_recus']);
        self::assertSame(0, $depart['en_attente']);
    }

    public function test_chaque_changement_est_trace_avec_l_etat_avant_et_apres(): void
    {
        $colis = $this->base->depart(1)['colis'][0];

        $this->service->pointer($colis, Regles::NON_RECU, 3400, 10, 'Pas sur la palette');
        $this->base->maintenant = '2026-10-23 15:00:00';
        $this->service->pointer($colis, Regles::RECU, 3400, 11);

        $journal = $this->base->journal($colis);
        self::assertCount(2, $journal);

        self::assertSame(['NON_RECU', 'EN_ATTENTE', 'NON_RECU', 'Pas sur la palette', 10, '2026-10-23 10:00:00', 3400], [
            $journal[0]['action'], $journal[0]['etat_avant'], $journal[0]['etat_apres'], $journal[0]['commentaire'],
            (int) $journal[0]['user_id'], $journal[0]['created_at'], (int) $journal[0]['agence_id'],
        ]);
        self::assertSame(['RECU', 'NON_RECU', 'RECU', null, 11, '2026-10-23 15:00:00'], [
            $journal[1]['action'], $journal[1]['etat_avant'], $journal[1]['etat_apres'], $journal[1]['commentaire'],
            (int) $journal[1]['user_id'], $journal[1]['created_at'],
        ]);
    }

    public function test_une_erreur_se_corrige_de_non_recu_a_recu(): void
    {
        $d = $this->base->depart(2);
        $this->service->pointer($d['colis'][0], Regles::RECU, 3400, 10);
        $this->service->pointer($d['colis'][1], Regles::NON_RECU, 3400, 10);

        $avant = $this->service->detailDepart($d['id'], 3400);
        self::assertSame(PointageColisService::ETAT_MANQUANTS, $avant['depart']['etat']);

        $r = $this->service->pointer($d['colis'][1], Regles::RECU, 3400, 10);
        self::assertTrue($r['ok']);
        self::assertSame(PointageColisService::ETAT_COMPLET, $r['depart']['etat']);
        self::assertSame(0, $r['depart']['manquants']);
        self::assertSame('CLOTURE', $this->base->pdo->query('SELECT statut FROM lbp_expeditions WHERE id = ' . $d['id'])->fetchColumn());
    }

    public function test_un_colis_non_recu_est_manquant_tout_de_suite(): void
    {
        $d = $this->base->depart(10);
        $r = $this->service->pointer($d['colis'][0], Regles::NON_RECU, 3400, 10);

        self::assertSame(PointageColisService::ETAT_MANQUANTS, $r['depart']['etat']);
        self::assertSame(1, $r['depart']['manquants'], 'Le colis dit non reçu est manquant sans attendre 24 h.');
        self::assertSame(9, $r['depart']['en_attente'], 'Les neuf autres restent en attente.');
        self::assertFalse($r['depart']['delai_depasse']);

        $detail = $this->service->detailDepart($d['id'], null);
        $etats = array_count_values(array_column($detail['colis'], 'etat'));
        self::assertSame(['NON_RECU' => 1, 'ATTENDU' => 9], $etats);
    }

    public function test_les_colis_jamais_pointes_gardent_la_regle_des_24_heures(): void
    {
        $d = $this->base->depart(3);
        $this->base->maintenant = '2026-10-23 08:00:00';
        $this->service->pointer($d['colis'][0], Regles::RECU, 3400, 10);

        $this->base->maintenant = '2026-10-24 07:59:59';
        $detail = $this->service->detailDepart($d['id'], null);
        self::assertSame(PointageColisService::ETAT_EN_COURS, $detail['depart']['etat']);
        self::assertSame(0, $detail['depart']['manquants']);
        self::assertSame('2026-10-24 08:00:00', $detail['depart']['echeance']);

        $this->base->maintenant = '2026-10-24 08:00:00';
        $detail = $this->service->detailDepart($d['id'], null);
        self::assertSame(PointageColisService::ETAT_MANQUANTS, $detail['depart']['etat']);
        self::assertSame(2, $detail['depart']['manquants']);
        self::assertSame(['RECU', 'MANQUANT', 'MANQUANT'], array_column($detail['colis'], 'etat'));
    }

    public function test_declarer_un_colis_non_recu_fait_arriver_le_depart(): void
    {
        $d = $this->base->depart(2);
        $this->service->pointer($d['colis'][0], Regles::NON_RECU, 3400, 10);

        $exp = $this->base->pdo->query('SELECT statut, date_premiere_reception FROM lbp_expeditions WHERE id = ' . $d['id'])->fetch();
        self::assertSame('ARRIVE', $exp['statut']);
        self::assertSame($this->base->maintenant, $exp['date_premiere_reception'], 'Le délai des 24 h court dès le premier pointage.');

        // Tout défait : l'horloge repart de zéro.
        $this->service->pointer($d['colis'][0], Regles::EN_ATTENTE, 3400, 10);
        $exp = $this->base->pdo->query('SELECT statut, date_premiere_reception FROM lbp_expeditions WHERE id = ' . $d['id'])->fetch();
        self::assertSame(['EN_TRANSIT', null], [$exp['statut'], $exp['date_premiere_reception']]);
        self::assertNull($this->base->colis($d['colis'][0])['etat_reception']);
        self::assertNull($this->base->colis($d['colis'][0])['pointe_par_id']);
    }

    public function test_endommage_exige_un_commentaire(): void
    {
        $colis = $this->base->depart(1)['colis'][0];

        $r = $this->service->pointer($colis, Regles::ENDOMMAGE, 3400, 10, '  ');
        self::assertFalse($r['ok']);
        self::assertStringContainsString('Décrivez le dommage', $r['message']);
        self::assertNull($this->base->colis($colis)['etat_reception']);
        self::assertSame([], $this->base->journal($colis), 'Un refus ne laisse aucune trace au journal.');
    }

    public function test_le_meme_etat_avec_le_meme_commentaire_ne_change_rien(): void
    {
        $colis = $this->base->depart(1)['colis'][0];
        $this->service->pointer($colis, Regles::ENDOMMAGE, 3400, 10, 'Coin écrasé');

        $r = $this->service->pointer($colis, Regles::ENDOMMAGE, 3400, 10, 'Coin écrasé');
        self::assertTrue($r['ok']);
        self::assertStringContainsString('Rien à changer', $r['message']);
        self::assertCount(1, $this->base->journal($colis));

        // Un autre commentaire est un changement, tracé.
        $this->service->pointer($colis, Regles::ENDOMMAGE, 3400, 10, 'Coin écrasé, contenu intact');
        self::assertCount(2, $this->base->journal($colis));
        self::assertSame('Coin écrasé, contenu intact', $this->base->colis($colis)['commentaire_reception']);
    }

    public function test_une_autre_agence_ne_pointe_pas_les_colis_de_paris(): void
    {
        $colis = $this->base->depart(1)['colis'][0];

        $r = $this->service->pointer($colis, Regles::RECU, 3403, 11);
        self::assertFalse($r['ok']);
        self::assertNull($this->base->colis($colis)['etat_reception']);
    }

    public function test_deux_agents_sur_le_meme_colis_ne_s_ecrasent_pas(): void
    {
        $colis = $this->base->depart(1)['colis'][0];
        $repo = new PointageColisRepository($this->base->pdo);

        self::assertTrue($repo->appliquerEtat($colis, Regles::EN_ATTENTE, Regles::NON_RECU, null, 10));
        // Le second croyait le colis encore en attente.
        self::assertFalse($repo->appliquerEtat($colis, Regles::EN_ATTENTE, Regles::RECU, null, 11));
        self::assertSame('NON_RECU', $this->base->colis($colis)['etat_reception']);
    }

    public function test_tout_cocher_marque_recus_les_seuls_colis_en_attente(): void
    {
        $d = $this->base->depart(4);
        $this->service->pointer($d['colis'][0], Regles::NON_RECU, 3400, 10);
        $this->service->pointer($d['colis'][1], Regles::ENDOMMAGE, 3400, 10, 'Humide');

        $r = $this->service->toutPointer($d['id'], 3400, 10);
        self::assertSame(2, $r['nb']);
        self::assertSame('NON_RECU', $this->base->colis($d['colis'][0])['etat_reception'], 'Un geste collectif n’efface pas un « non reçu ».');
        self::assertSame('ENDOMMAGE', $this->base->colis($d['colis'][1])['etat_reception']);
        self::assertSame('RECU', $this->base->colis($d['colis'][2])['etat_reception']);
        self::assertSame(['TOUT', 'EN_ATTENTE', 'RECU'], [
            $this->base->journal($d['colis'][3])[0]['source'], $this->base->journal($d['colis'][3])[0]['etat_avant'], $this->base->journal($d['colis'][3])[0]['etat_apres'],
        ]);
    }

    public function test_la_douchette_marque_recu_et_corrige_un_non_recu(): void
    {
        $d = $this->base->depart(2);
        $this->service->pointer($d['colis'][0], Regles::NON_RECU, 3400, 10);
        $code = (string) $this->base->colis($d['colis'][0])['numero_tracking'];

        $r = $this->service->scanner($code, 3400, 10);
        self::assertTrue($r['ok']);
        self::assertArrayHasKey('depart', $r);
        self::assertStringContainsString('corrigé', $r['message']);
        self::assertSame('RECU', $this->base->colis($d['colis'][0])['etat_reception']);

        $encore = $this->service->scanner($code, 3400, 10);
        self::assertTrue($encore['ok']);
        self::assertArrayNotHasKey('depart', $encore, 'Un second scan ne réenregistre rien.');
        self::assertCount(2, $this->base->journal($d['colis'][0]));
    }

    public function test_un_colis_pointe_avant_les_trois_choix_compte_comme_recu(): void
    {
        $d = $this->base->depart(1);
        $this->base->pdo->exec("UPDATE lbp_colis SET date_reception = '2026-10-22 09:00:00', statut = 'arrive', recu_par_id = 10 WHERE id = " . $d['colis'][0]);

        self::assertSame(Regles::RECU, Regles::etatDe($this->base->colis($d['colis'][0])));
        $detail = $this->service->detailDepart($d['id'], null);
        self::assertSame('RECU', $detail['colis'][0]['etat']);
        self::assertSame('Agent Paris', $detail['colis'][0]['pointe_par']);
        self::assertSame('2026-10-22 09:00:00', $detail['colis'][0]['pointe_le']);

        // Et il se corrige comme les autres.
        self::assertTrue($this->service->pointer($d['colis'][0], Regles::ENDOMMAGE, 3400, 10, 'Vu au déballage')['ok']);
        self::assertSame('2026-10-22 09:00:00', $this->base->colis($d['colis'][0])['date_reception'], 'L’heure de réception reste la première.');
    }

    public function test_l_ancienne_case_a_cocher_reste_comprise(): void
    {
        $colis = $this->base->depart(1)['colis'][0];

        $this->service->pointer($colis, true, 3400, 10);
        self::assertSame('RECU', $this->base->colis($colis)['etat_reception']);

        $this->service->pointer($colis, false, 3400, 10);
        self::assertNull($this->base->colis($colis)['etat_reception']);
        self::assertNull($this->base->colis($colis)['date_reception']);
    }

    public function test_les_regles_refusent_un_choix_inconnu(): void
    {
        self::assertFalse(Regles::transition(Regles::EN_ATTENTE, 'PERDU', null)['ok']);
        self::assertSame(str_repeat('a', 500), Regles::commentaire(str_repeat('a', 600)));
        self::assertSame('deux mots', Regles::commentaire("  deux\n\tmots "));
        self::assertNull(Regles::commentaire(''));
        // Trouvé en vrai le 23/10/2026 : un « é » en Windows-1252 effaçait tout le commentaire.
        self::assertSame('Carton écrasé', Regles::commentaire("Carton \xE9cras\xE9"));
    }
}
