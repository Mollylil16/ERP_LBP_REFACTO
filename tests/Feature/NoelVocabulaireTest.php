<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Noel\NoelVocabulaire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Le dictionnaire du métier, tel qu'on le parle à La Belle Porte.
 *
 * Personne ne tape « Je souhaiterais accéder à l'écran de clôture de caisse ».
 * On tape « mon point passe pas », « ya dra avec ma caisse », « je veux fermer
 * ma caisse ». Ces tests gardent la promesse faite à l'administrateur : NOEL
 * comprend les questions réelles, sans intelligence artificielle, et répond
 * toujours juste plutôt que d'inventer.
 */
final class NoelVocabulaireTest extends TestCase
{
    /**
     * Les phrases que les agents tapent vraiment, et ce qu'elles veulent dire.
     *
     * @return array<string, array{0: string, 1: ?string, 2: string}>
     */
    public static function phrases(): array
    {
        return [
            // question                                        concept          intention
            'où se soumet le point' => ['ou je soumets mon point de caisse', 'point_caisse', 'ou'],
            'le point refuse' => ['mon point passe pas', 'point_caisse', 'panne'],
            'fermer sa caisse' => ['je veux fermer ma caisse', 'point_caisse', 'ou'],
            'nouchi : ya dra' => ['ya dra avec ma caisse', 'point_caisse', 'panne'],
            'nouchi : ça gâte' => ['c est gate je peux pas pointer', 'pointage', 'panne'],
            'faute de frappe' => ['la fature du client ne sort pas', 'facture', 'panne'],
            // « comment je fais pour » n'attend ni une definition ni une
            // adresse : elle attend des gestes dans l'ordre. L'intention
            // « procedure » est plus precise que « comment », qui sert aux
            // questions d'explication.
            'faute sur colis' => ['comment je fais pour enregistrer un coli', 'colis', 'procedure'],
            'demande de fonds' => ['je n arrive pas a valider une demande de fonds', 'demande_fonds', 'panne'],
            'proposition' => ['il faudrait ameliorer l ecran des colis', 'colis', 'signalement'],
            'demande de droit' => ['donne moi le droit de facturer', 'facture', 'signalement'],
            'salutation d ici' => ['bonjour noel on dit quoi', null, 'salutation'],
            'remerciement nouchi' => ['merci ya fohi', null, 'remerciement'],
            'hors sujet' => ['je veux parler de football', null, 'inconnue'],
            // Ajouts du 22/10/2026 : le dictionnaire passe de 316 a 723
            // expressions et de 17 a 23 sujets.
            'export pdf' => ['je n arrive pas a tirer l etat en pdf', 'impression', 'panne'],
            'comptabilite' => ['comment lettrer une ecriture', 'comptabilite', 'comment'],
            'flotte' => ['mon vehicule a besoin de carburant', 'flotte', 'ou'],
            'douane' => ['le dedouanement du conteneur', 'transit', 'ou'],
            'bonjour d ici' => ['yako noel c est comment ce matin', null, 'salutation'],
            'le colis ne bouge pas' => ['le tracking du colis ne bouge pas', 'colis', 'panne'],
            'demande d acces' => ['donne moi l acces au module colisage', null, 'signalement'],
        ];
    }

    #[DataProvider('phrases')]
    public function test_noel_comprend_les_phrases_reelles(string $phrase, ?string $concept, string $intention): void
    {
        $lu = NoelVocabulaire::comprendre($phrase);

        self::assertSame($concept, $lu['concept'], 'Concept mal lu dans : ' . $phrase);
        self::assertSame($intention, $lu['intention'], 'Intention mal lue dans : ' . $phrase);
    }

    public function test_le_dictionnaire_est_assez_fourni_pour_servir(): void
    {
        /*
         * Un dictionnaire de cinquante mots ne couvrirait que le vocabulaire
         * du menu — c'est-à-dire exactement celui que les agents n'emploient
         * pas. Le seuil n'est pas magique : il empêche qu'on vide la liste
         * sans s'en apercevoir.
         */
        self::assertGreaterThanOrEqual(700, NoelVocabulaire::tailleDuDictionnaire());
        self::assertGreaterThanOrEqual(22, count(NoelVocabulaire::concepts()));
    }

    public function test_le_parler_d_ici_est_reconnu(): void
    {
        /*
         * Un agent bloqué n'écrit pas « je rencontre une anomalie ». Il écrit
         * « ya dra », « ça gâte », « c'est chaud ». Les ignorer, c'est ne pas
         * comprendre la moitié des demandes.
         */
        foreach (['ya dra', 'ca gate', 'c est chaud'] as $expression) {
            self::assertContains($expression, NoelVocabulaire::PANNE, $expression . ' doit être compris.');
        }

        foreach (['on dit quoi', 'c est comment'] as $bonjour) {
            self::assertContains($bonjour, NoelVocabulaire::SALUTATION, $bonjour . ' est un bonjour d\'ici.');
        }

        self::assertContains('ya fohi', NoelVocabulaire::REMERCIEMENT);
    }

    public function test_un_bonjour_ne_mange_pas_une_vraie_question(): void
    {
        /*
         * « C'est comment ? » est un bonjour a Abidjan. Mais « bonjour, c'est
         * comment pour la caisse ? » est une vraie question, et y repondre
         * « bonjour a vous aussi » serait insupportable. Le bonjour ne
         * l'emporte donc que si la phrase ne parle de rien d'autre.
         */
        self::assertSame('salutation', NoelVocabulaire::comprendre('bonjour c est comment')['intention']);

        $vraie = NoelVocabulaire::comprendre('bonjour c est comment pour fermer ma caisse');
        self::assertSame('point_caisse', $vraie['concept']);
        self::assertNotSame('salutation', $vraie['intention']);
    }

    public function test_une_panne_passe_avant_un_remerciement(): void
    {
        /*
         * « Merci mais ça ne marche toujours pas » attend qu'on s'occupe
         * d'elle, pas qu'on dise « de rien ». Ce qui appelle une action passe
         * devant ce qui n'en appelle pas.
         */
        $lu = NoelVocabulaire::comprendre('merci mais ca marche pas encore');

        self::assertSame('panne', $lu['intention']);
    }

    public function test_une_expression_longue_l_emporte_sur_un_mot_isole(): void
    {
        // « point de caisse » doit gagner contre « caisse » croisé ailleurs.
        $lu = NoelVocabulaire::comprendre('je veux voir le point de caisse de mon agence');

        self::assertSame('point_caisse', $lu['concept']);
        self::assertGreaterThanOrEqual(3, $lu['score']);
    }

    public function test_le_bulletin_garde_une_porte_pour_un_agent_ordinaire(): void
    {
        /*
         * La paie est un écran RH, fermé au personnel. Sans cette seconde
         * adresse, NOEL n'aurait rien à répondre à qui demande son bulletin —
         * alors que chacun peut lire le sien dans son espace.
         */
        $lu = NoelVocabulaire::comprendre('ou je vois mon bulletin de paie');

        self::assertSame('paie', $lu['concept']);
        self::assertContains('espace-employe/dashboard', $lu['ecrans']);
    }

    public function test_le_dictionnaire_n_attribue_aucun_droit(): void
    {
        /*
         * Il traduit des mots en concepts, rien de plus. C'est NoelCarte qui
         * décide si la personne peut voir l'écran. Les confondre rendrait ce
         * fichier capable d'ouvrir des portes.
         */
        $source = (string) file_get_contents(BASE_PATH . '/app/Services/Noel/NoelVocabulaire.php');

        self::assertStringNotContainsString('Auth::', $source);
        self::assertStringNotContainsString('hasRole', $source);
        self::assertStringNotContainsString('isAdmin', $source);
    }

    public function test_une_phrase_vide_ne_fait_rien_dire_a_noel(): void
    {
        foreach (['', '   ', '???', '...'] as $rien) {
            $lu = NoelVocabulaire::comprendre($rien);
            self::assertNull($lu['concept'], 'Une phrase vide ne doit évoquer aucun sujet.');
            self::assertSame('inconnue', $lu['intention']);
        }
    }
}
