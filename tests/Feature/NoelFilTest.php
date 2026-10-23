<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Noel\NoelCarte;
use App\Services\Noel\NoelFil;
use App\Services\Noel\NoelRepondeur;
use App\Services\Noel\NoelVocabulaire;
use Tests\TestCase;

/**
 * Ce que NOEL retient du fil de la conversation.
 *
 * Sans cela il posait des questions qu'il n'entendait pas : « voulez-vous que
 * je transmette ? » — « oui » — « je n'ai pas compris votre question ». C'est
 * pire que de ne rien proposer : quelqu'un qui dit oui et n'obtient rien cesse
 * de faire confiance.
 */
final class NoelFilTest extends TestCase
{
    private const FIL = 42;

    protected function setUp(): void
    {
        parent::setUp();
        NoelFil::toutOublier();
    }

    private function noel(): NoelRepondeur
    {
        return new NoelRepondeur(new NoelCarte());
    }

    public function test_oui_apres_une_proposition_transmet(): void
    {
        $noel = $this->noel();

        $proposition = $noel->repondre('je veux parler de mon weekend', self::FIL);
        self::assertSame('transmission', $proposition['attente']);

        $suite = $noel->repondre('oui', self::FIL);

        self::assertStringContainsString('transmis', $suite['texte']);
        self::assertNull($suite['attente'], "L'attente doit être levée une fois la question partie.");
    }

    public function test_non_apres_une_proposition_ne_transmet_rien(): void
    {
        $noel = $this->noel();
        $noel->repondre('raconte moi une histoire', self::FIL);

        $suite = $noel->repondre('non merci', self::FIL);

        self::assertStringContainsString('je ne transmets rien', $suite['texte']);
        self::assertNull($suite['attente']);
    }

    public function test_une_nouvelle_question_annule_l_attente_sans_harceler(): void
    {
        /*
         * Quelqu'un qui passe à autre chose n'a pas dit non : il a juste autre
         * chose à faire. Réclamer une réponse à une question qu'il a oubliée
         * serait pénible.
         */
        $noel = $this->noel();
        $noel->repondre('je veux parler de mon weekend', self::FIL);

        $suite = $noel->repondre('ou je soumets mon point de caisse', self::FIL);

        self::assertNotEmpty($suite['liens']);
        self::assertSame('/finance/clotures', $suite['liens'][0]['url']);
    }

    public function test_une_relance_reprend_le_sujet_precedent(): void
    {
        /*
         * « Et comment je fais ? » ne nomme rien : c'est le fil qui porte le
         * sujet. Sans mémoire, NOEL répondait « je n'ai pas compris » à une
         * question limpide pour qui a lu les deux lignes précédentes.
         */
        $noel = $this->noel();
        $noel->repondre('parle moi du module finance', self::FIL);

        $suite = $noel->repondre('et comment je fais ?', self::FIL);

        self::assertStringContainsString('1. ', $suite['texte'], 'Une relance doit donner les étapes.');
        self::assertStringContainsString('point de caisse', mb_strtolower($suite['texte']));
    }

    public function test_deux_conversations_ne_se_melangent_pas(): void
    {
        // Le contexte appartient au fil, pas à la personne : NOEL parle à
        // plusieurs collègues en même temps.
        $noel = $this->noel();

        $noel->repondre('parle moi du module finance', 1);
        $noel->repondre('je veux parler de mon weekend', 2);

        self::assertSame('finance', NoelFil::dernier(1)['module']);
        self::assertNull(NoelFil::dernier(1)['attente']);
        self::assertSame('transmission', NoelFil::dernier(2)['attente']);
    }

    public function test_sans_identifiant_de_fil_noel_reste_utilisable(): void
    {
        // Les appels hors messagerie — un test, une console — ne doivent pas
        // echouer faute de conversation.
        $reponse = $this->noel()->repondre('ou je soumets mon point de caisse');

        self::assertNotEmpty($reponse['liens']);
    }

    public function test_un_oui_doit_etre_court_pour_compter(): void
    {
        /*
         * « Oui » est un acquiescement. « Oui mais au fait, où est la
         * caisse ? » est une nouvelle question, et la traiter comme un oui
         * transmettrait la mauvaise chose.
         */
        self::assertTrue(NoelVocabulaire::estOui('oui'));
        self::assertTrue(NoelVocabulaire::estOui('Vas-y'));
        self::assertTrue(NoelVocabulaire::estOui('ok'));

        self::assertFalse(NoelVocabulaire::estOui('oui mais au fait ou est la caisse'));
        self::assertFalse(NoelVocabulaire::estOui(''));
    }

    public function test_le_non_est_reconnu_sous_ses_formes_courantes(): void
    {
        foreach (['non', 'non merci', 'pas la peine', 'laisse tomber'] as $refus) {
            self::assertTrue(NoelVocabulaire::estNon($refus), $refus);
        }

        self::assertFalse(NoelVocabulaire::estNon('oui'));
    }

    public function test_la_memoire_ne_va_jamais_en_base(): void
    {
        /*
         * Savoir qui a séché sur l'énigme de mercredi ne regarde personne, et
         * une question en attente de confirmation n'a pas à survivre à la
         * journée : « oui » une heure plus tard ne doit rien transmettre.
         */
        $source = (string) file_get_contents(BASE_PATH . '/app/Services/Noel/NoelFil.php');

        self::assertStringContainsString('$_SESSION', $source);
        self::assertStringNotContainsString('INSERT', $source);
        self::assertStringNotContainsString('Database', $source);
    }

    public function test_la_session_ne_gonfle_pas_indefiniment(): void
    {
        // Un agent qui écrit à vingt collègues n'a pas besoin qu'on se
        // souvienne de tout.
        for ($fil = 1; $fil <= 15; $fil++) {
            NoelFil::retenir($fil, ['concept' => 'colis', 'attente' => null]);
        }

        self::assertLessThanOrEqual(10, count($_SESSION['noel_fils'] ?? []));

        // Et ce sont les plus récents qu'on garde.
        self::assertSame('colis', NoelFil::dernier(15)['concept']);
        self::assertNull(NoelFil::dernier(1)['concept']);
    }

    public function test_l_attente_d_enigme_laisse_passer_une_vraie_question(): void
    {
        /*
         * Demander « où est ma caisse » pendant l'énigme ne doit pas coûter un
         * essai à quelqu'un qui ne jouait pas.
         */
        NoelFil::retenir(self::FIL, [
            'concept' => null, 'module' => null, 'attente' => 'enigme', 'essais' => 0,
        ]);

        $reponse = $this->noel()->repondre('ou je soumets mon point de caisse', self::FIL);

        self::assertNotEmpty($reponse['liens']);
        self::assertSame(0, NoelFil::dernier(self::FIL)['essais']);
    }
}
