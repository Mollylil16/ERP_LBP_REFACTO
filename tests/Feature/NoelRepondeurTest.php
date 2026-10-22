<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Noel\NoelCarte;
use App\Services\Noel\NoelRepondeur;
use Tests\TestCase;

/**
 * La bouche de NOEL : ce qu'il répond quand on lui écrit.
 *
 * Tout le reste était le cerveau — la carte, le dictionnaire, le journal, la
 * veille, le mot du matin. Cette classe les assemble, et elle seule décide de
 * ce qui sort.
 */
final class NoelRepondeurTest extends TestCase
{
    private function noel(): NoelRepondeur
    {
        return new NoelRepondeur(new NoelCarte());
    }

    private function source(): string
    {
        return (string) file_get_contents(BASE_PATH . '/app/Services/Noel/NoelRepondeur.php');
    }

    public function test_il_oriente_vers_un_ecran_ouvrable(): void
    {
        $reponse = $this->noel()->repondre('ou je soumets mon point de caisse');

        self::assertNotEmpty($reponse['liens']);
        self::assertSame('/finance/clotures', $reponse['liens'][0]['url']);
    }

    public function test_le_module_annonce_est_le_bon(): void
    {
        /*
         * Plusieurs modules citent le même écran : « finance/clotures » figure
         * au menu de Finance et à celui de Recherche & Audit. En écrasant au
         * fur et à mesure, NOEL annonçait « Recherche & Audit » à une
         * caissière qui cherchait simplement sa clôture.
         */
        $reponse = $this->noel()->repondre('ou je soumets mon point de caisse');

        self::assertStringStartsWith('Finance >', $reponse['liens'][0]['label']);
        self::assertStringContainsString('if (!isset($visibles[$ecran[\'url\']]))', $this->source());
    }

    public function test_il_ne_nomme_jamais_un_ecran_ferme(): void
    {
        /*
         * La règle la plus importante. Si le concept est compris mais qu'aucun
         * écran n'est ouvert à la personne, NOEL ne dit pas « c'est dans
         * Finance, mais vous n'y avez pas droit » : nommer une porte fermée,
         * c'est apprendre à quelqu'un qu'il y a une porte.
         */
        $source = $this->source();

        self::assertStringContainsString(
            "Je ne trouve pas d'ecran que vous puissiez ouvrir",
            $source
        );

        // On vérifie le code, pas les commentaires : la phrase interdite
        // figure justement dans celui qui pose la règle.
        $code = $this->sansCommentaires($source);

        self::assertStringNotContainsString("vous n'y avez pas droit", $code);
        self::assertStringNotContainsString('acces refuse', mb_strtolower($code));
    }

    /** Le code seul, sans les commentaires qui le documentent. */
    private function sansCommentaires(string $source): string
    {
        $sansBlocs = (string) preg_replace('#/\*.*?\*/#s', '', $source);

        return (string) preg_replace('#^\s*//.*$#m', '', $sansBlocs);
    }

    public function test_il_avoue_plutot_que_d_inventer(): void
    {
        // Un assistant qui improvise une procédure est pire qu'aucun
        // assistant : on suit son conseil, et c'est faux.
        $reponse = $this->noel()->repondre('je veux parler de mon weekend');

        self::assertStringContainsString("n'ai pas compris", $reponse['texte']);
        self::assertSame('transmission', $reponse['attente']);
        self::assertSame([], $reponse['liens']);
    }

    public function test_il_ne_transmet_rien_sans_accord(): void
    {
        /*
         * Une question incomprise peut être une demande d'aide comme une
         * confidence. NOEL propose, la personne décide.
         */
        $reponse = $this->noel()->repondre('je veux parler de mon weekend');

        self::assertStringContainsString('Voulez-vous', $reponse['texte']);
        self::assertStringContainsString('que si vous me le demandez', $reponse['texte']);
    }

    public function test_une_demande_d_acces_part_en_signalement(): void
    {
        $reponse = $this->noel()->repondre('donne moi l acces a la facturation');

        self::assertStringContainsString("C'est note", $reponse['texte']);
        self::assertSame('transmission', $reponse['attente']);
    }

    public function test_le_francais_reste_correct(): void
    {
        // « au sujet de les factures » ne se dit pas : les libellés portent
        // déjà leur article.
        $reponse = $this->noel()->repondre('donne moi l acces a la facturation');

        self::assertStringNotContainsString('de les ', $reponse['texte']);
        self::assertStringContainsString('concernant les factures', $reponse['texte']);
    }

    public function test_un_bonjour_recoit_le_mot_du_matin(): void
    {
        $reponse = $this->noel()->repondre('bonjour noel on dit quoi');

        self::assertMatchesRegularExpression('/^(Bonjour|Bonsoir)/', $reponse['texte']);
        self::assertGreaterThan(30, mb_strlen($reponse['texte']), 'Un bonjour sec ne vaut pas un mot du matin.');
    }

    public function test_un_merci_recoit_une_reponse_breve(): void
    {
        // Répondre un paragraphe à « merci » est la meilleure façon de lasser.
        $reponse = $this->noel()->repondre('merci ya fohi');

        self::assertLessThan(80, mb_strlen($reponse['texte']));
        self::assertSame([], $reponse['liens']);
    }

    public function test_une_panne_propose_l_ecran_et_la_transmission(): void
    {
        $reponse = $this->noel()->repondre('mon point passe pas');

        self::assertNotEmpty($reponse['liens']);
        self::assertSame('transmission', $reponse['attente']);
    }

    public function test_le_decaissement_refuse_recoit_sa_vraie_cause(): void
    {
        /*
         * C'est le cas qui a motivé tout le travail sur le journal. « Le
         * décaissement se fait sur la fiche » ne sert à rien à quelqu'un pour
         * qui le panneau n'existera jamais.
         */
        self::assertStringContainsString(
            'vous avez vous-meme valide cette demande',
            $this->source()
        );
        self::assertStringContainsString('14 octobre', $this->source());
    }

    public function test_la_reponse_se_met_en_message_avec_ses_liens(): void
    {
        $message = NoelRepondeur::enMessage([
            'texte' => "C'est ici.",
            'liens' => [['label' => 'Finance > Points de Caisse', 'url' => '/finance/clotures']],
        ]);

        self::assertStringContainsString("C'est ici.", $message);
        self::assertStringContainsString('Finance > Points de Caisse : /finance/clotures', $message);
    }

    public function test_le_repondeur_tient_sans_base_de_donnees(): void
    {
        /*
         * Database::getConnection() n'échoue pas quand la base manque : il
         * arrête le processus. Sans cette précaution, le répondeur ferait
         * tomber toute la suite de tests dès la première question.
         */
        $source = $this->source();

        self::assertStringContainsString('private static function enTest()', $source);
        self::assertStringContainsString('if (self::enTest())', $source);
    }

    public function test_une_mesure_qui_echoue_n_empeche_pas_la_reponse(): void
    {
        // Compter est utile ; répondre l'est davantage.
        $source = $this->source();

        $debut = (int) strpos($source, 'private function mesurer');
        $methode = substr($source, $debut, 900);

        self::assertStringContainsString('catch (Throwable $e)', $methode);
        self::assertStringNotContainsString('throw', $methode);
    }
}
