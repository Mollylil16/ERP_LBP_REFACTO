<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Noel\NoelCarte;
use App\Services\Noel\NoelProcedure;
use App\Services\Noel\NoelRepondeur;
use App\Services\Noel\NoelVocabulaire;
use Tests\TestCase;

/**
 * Présenter un module, et donner la marche à suivre.
 *
 * Constaté en production le 23/10/2026 : « parle-moi du module finance »
 * restait sans réponse. NOEL savait où étaient les écrans, mais pas raconter
 * ce qu'on y fait — alors que c'est par là qu'on commence quand on découvre le
 * logiciel.
 */
final class NoelPresentationTest extends TestCase
{
    private function noel(): NoelRepondeur
    {
        return new NoelRepondeur(new NoelCarte());
    }

    public function test_une_demande_de_presentation_est_reconnue(): void
    {
        foreach ([
            'parle moi du module finance',
            'c est quoi la logistique',
            'a quoi sert le crm',
            'explique moi les entrepots',
        ] as $question) {
            $lu = NoelVocabulaire::comprendre($question);

            self::assertSame(
                NoelVocabulaire::INTENTION_PRESENTATION,
                $lu['intention'],
                'Mal lu : ' . $question
            );
            self::assertNotNull($lu['module'], 'Module non reconnu : ' . $question);
        }
    }

    public function test_un_module_nomme_seul_vaut_une_presentation(): void
    {
        $lu = NoelVocabulaire::comprendre('la logistique');

        self::assertSame('logistique', $lu['module']);
        self::assertSame(NoelVocabulaire::INTENTION_PRESENTATION, $lu['intention']);
    }

    public function test_l_expression_la_plus_longue_gagne(): void
    {
        // « transit douane » doit l'emporter sur « transit » seul, sinon on
        // présente le mauvais module.
        self::assertSame('transit-douane', NoelVocabulaire::comprendre('le transit douane')['module']);
    }

    public function test_une_recherche_d_ecran_ne_devient_pas_une_presentation(): void
    {
        /*
         * « où je facture » attend une adresse, « c'est quoi la facturation »
         * attend une explication. Répondre un cours à la première serait aussi
         * inutile que répondre un lien à la seconde.
         */
        $lu = NoelVocabulaire::comprendre('ou je soumets mon point de caisse');

        self::assertSame(NoelVocabulaire::INTENTION_OU, $lu['intention']);
    }

    public function test_la_presentation_dit_a_quoi_sert_le_module(): void
    {
        $reponse = $this->noel()->repondre('parle moi du module finance');

        self::assertStringContainsString('Finance', $reponse['texte']);
        self::assertStringContainsString('ecrans', $reponse['texte']);
        self::assertStringContainsString('groupes', $reponse['texte']);
        self::assertNotEmpty($reponse['liens'], 'Une présentation doit proposer par où entrer.');
    }

    public function test_la_presentation_amorce_la_question_suivante(): void
    {
        /*
         * Terminer par « dites-moi ce que vous voulez faire » laisse la
         * personne devant une page blanche. Un exemple concret lui donne le
         * ton et le vocabulaire.
         */
        $reponse = $this->noel()->repondre('parle moi du module finance');

        self::assertStringContainsString('comment je fais pour', $reponse['texte']);
        self::assertStringContainsString('point de caisse', $reponse['texte']);
    }

    public function test_un_module_ferme_n_est_jamais_decrit(): void
    {
        /*
         * Le module RH est invisible pour le reste du personnel. Le présenter
         * reviendrait à apprendre à quelqu'un qu'il y a une porte — ce que ce
         * module interdit précisément.
         */
        $reponse = $this->noel()->repondre('explique moi le module rh');

        self::assertStringNotContainsString('Ressources humaines', $reponse['texte']);
        self::assertStringNotContainsString('ecrans', $reponse['texte']);
        self::assertSame([], $reponse['liens']);
    }

    public function test_une_demande_de_marche_a_suivre_est_reconnue(): void
    {
        foreach ([
            'comment je fais pour encaisser un reglement',
            'quelles sont les etapes pour enregistrer un colis',
            'par ou je commence pour soumettre mon point',
        ] as $question) {
            self::assertSame(
                NoelVocabulaire::INTENTION_PROCEDURE,
                NoelVocabulaire::comprendre($question)['intention'],
                'Mal lu : ' . $question
            );
        }
    }

    public function test_la_marche_a_suivre_donne_les_gestes_dans_l_ordre(): void
    {
        $reponse = $this->noel()->repondre('comment faire pour encaisser un reglement');

        self::assertStringContainsString('1. ', $reponse['texte']);
        self::assertStringContainsString('5. ', $reponse['texte']);
        self::assertStringContainsString('A retenir', $reponse['texte']);
    }

    public function test_une_procedure_se_refuse_comme_un_ecran_se_refuse(): void
    {
        /*
         * Raconter comment intégrer un collaborateur à quelqu'un qui n'a pas
         * accès aux Ressources humaines reviendrait à lui décrire une pièce
         * fermée à clef — et à lui apprendre qu'elle existe.
         */
        $reponse = $this->noel()->repondre('comment je fais pour integrer un collaborateur');

        self::assertStringNotContainsString('1. ', $reponse['texte']);
        self::assertStringContainsString('vous ne pouvez pas ouvrir', $reponse['texte']);
        self::assertSame([], $reponse['liens']);
    }

    public function test_aucune_procedure_n_est_improvisee(): void
    {
        /*
         * NOEL ne doit jamais inventer une marche à suivre : quelqu'un la
         * suivrait, et elle serait fausse. Quand elle n'est pas écrite, il le
         * dit. C'est la contrepartie de l'absence d'intelligence artificielle :
         * ce qu'il explique, un humain l'a écrit et relu.
         */
        self::assertNull(NoelProcedure::pour('comptabilite'));
        self::assertNull(NoelProcedure::pour(null, null));
        self::assertNotNull(NoelProcedure::pour('point_caisse'));
    }

    public function test_chaque_procedure_est_complete(): void
    {
        foreach (NoelProcedure::gestesConnus() as $geste) {
            $procedure = NoelProcedure::pour($geste);

            self::assertNotNull($procedure, $geste);
            self::assertNotSame('', trim($procedure['titre']), $geste);
            self::assertGreaterThanOrEqual(4, count($procedure['etapes']), $geste . ' : trop peu d\'étapes.');
            self::assertNotEmpty($procedure['ecrans'], $geste . ' : aucun écran rattaché.');
        }
    }

    public function test_les_procedures_portent_les_pieges_appris_cette_semaine(): void
    {
        // Ce que NOEL explique doit porter ce qu'on a corrigé, sinon le guide
        // répète les erreurs que le code ne fait plus.
        self::assertStringContainsString(
            'Annuler ce reglement',
            (string) NoelProcedure::pour('facture')['attention']
        );
        self::assertStringContainsString(
            'reintegrez l ancien',
            (string) NoelProcedure::pour('dossier_personnel')['attention']
        );
        self::assertStringContainsString(
            'personne ne decaisse ce qu il a autorise',
            (string) NoelProcedure::pour('decaissement')['attention']
        );
    }
}
