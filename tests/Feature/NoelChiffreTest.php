<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Noel\NoelCarte;
use App\Services\Noel\NoelRepondeur;
use App\Services\Noel\NoelVocabulaire;
use Tests\TestCase;

/**
 * Quand on demande un chiffre, NOEL donne un chiffre.
 *
 * Demandé par la direction le 03/11/2026 : « combien de colis aujourd'hui ? »
 * et « quel est le solde de la caisse ? » recevaient une adresse. La personne
 * savait déjà où regarder — elle demandait le nombre.
 *
 * Deux règles gouvernent tout ce fichier :
 *
 *  1. le chiffre s'arrête à ce que la personne a le droit de voir. Un agent de
 *     Dokui qui demande « combien de colis » parle de Dokui, et NOEL ne doit
 *     pas lui apprendre ce qui se passe à Adjamé ;
 *  2. faute de chiffre, NOEL oriente. Il n'en invente jamais un : c'est la
 *     contrepartie de l'absence d'intelligence artificielle — ce qu'il
 *     avance, une requête relue l'a compté.
 *
 * Les deux phrases sont des fonctions pures d'un tableau, et se vérifient
 * donc ici sans base. Les requêtes, elles, ont été éprouvées sur la base le
 * 03/11/2026 : quatre colis en deux saisies pour l'administrateur, zéro pour
 * un agent d'une autre agence, le colis annulé exclu des deux.
 */
final class NoelChiffreTest extends TestCase
{
    private function source(string $classe): string
    {
        return (string) file_get_contents(BASE_PATH . '/app/Services/Noel/' . $classe . '.php');
    }

    /**
     * Le corps d'une methode, coupe net avant la suivante.
     *
     * Une fenetre de taille fixe debordait sur la methode d'apres, et le test
     * y trouvait un SELECT qui n'etait pas celui qu'il cherchait.
     */
    private function methode(string $classe, string $nom): string
    {
        $source = $this->source($classe);
        $debut = strpos($source, 'function ' . $nom . '(');

        self::assertNotFalse($debut, $classe . '::' . $nom . ' introuvable.');

        $fin = strpos($source, "
    }
", (int) $debut);

        return substr($source, (int) $debut, (int) $fin - (int) $debut);
    }

    // ------------------------------------------------------------------
    // Reconnaitre qu'on demande un chiffre
    // ------------------------------------------------------------------

    public function test_les_deux_questions_de_la_direction_appellent_un_chiffre(): void
    {
        foreach ([
            "combien de colis aujourd'hui ?",
            'quel est le solde de la caisse ?',
            'ca fait combien de colis ce matin',
            'ou en est la caisse',
        ] as $question) {
            self::assertSame(
                NoelVocabulaire::INTENTION_CHIFFRE,
                NoelVocabulaire::comprendre($question)['intention'],
                $question
            );
        }
    }

    /**
     * « C'est quoi le solde de la veille » demande ce que c'est, pas combien
     * il fait. L'explication passe donc avant le chiffre.
     */
    public function test_une_demande_d_explication_ne_devient_pas_un_chiffre(): void
    {
        self::assertSame(
            NoelVocabulaire::INTENTION_PRESENTATION,
            NoelVocabulaire::comprendre("c'est quoi le solde de la veille ?")['intention']
        );
    }

    /** Et « où je saisis un colis » attend toujours une adresse. */
    public function test_une_demande_d_adresse_ne_devient_pas_un_chiffre(): void
    {
        self::assertSame(
            NoelVocabulaire::INTENTION_OU,
            NoelVocabulaire::comprendre('ou je saisis un colis')['intention']
        );
    }

    /**
     * Un « combien » qui ne porte sur rien de connu ne bascule pas : NOEL
     * n'aurait rien à compter.
     */
    public function test_un_combien_sans_objet_ne_bascule_pas(): void
    {
        self::assertNotSame(
            NoelVocabulaire::INTENTION_CHIFFRE,
            NoelVocabulaire::comprendre('combien de temps avant la pluie')['intention']
        );
    }

    // ------------------------------------------------------------------
    // Ce qu'il dit du chiffre
    // ------------------------------------------------------------------

    public function test_il_donne_le_nombre_de_colis_et_le_nombre_de_saisies(): void
    {
        $texte = NoelRepondeur::phraseDesColis([
            'colis' => 39, 'saisies' => 12, 'portee' => 'a Abobo Dokui', 'reseau' => false,
        ]);

        self::assertStringContainsString('39 colis', $texte);
        self::assertStringContainsString('12 saisies', $texte);
        self::assertStringContainsString('Abobo Dokui', $texte, 'Le chiffre doit dire de quelle agence il parle.');
    }

    /**
     * Un colis saisi peut en porter plusieurs. Quand les deux nombres sont
     * égaux, le répéter n'apprendrait rien.
     */
    public function test_il_tait_le_nombre_de_saisies_quand_il_est_le_meme(): void
    {
        $texte = NoelRepondeur::phraseDesColis([
            'colis' => 7, 'saisies' => 7, 'portee' => 'a Adjame', 'reseau' => false,
        ]);

        self::assertStringContainsString('7 colis', $texte);
        self::assertStringNotContainsString('saisie', $texte);
    }

    /** Les milliers se séparent : « 1 699 colis » et non « 1699 colis ». */
    public function test_les_milliers_se_lisent(): void
    {
        $texte = NoelRepondeur::phraseDesColis([
            'colis' => 1699, 'saisies' => 531, 'portee' => 'sur l ensemble du reseau', 'reseau' => true,
        ]);

        self::assertStringContainsString('1 699 colis', $texte);
        self::assertStringContainsString('531 saisies', $texte);
    }

    /**
     * Zéro est une réponse. Et pour qui ne voit que son agence, la piste de
     * l'autre agence vaut d'être donnée — elle explique la moitié des
     * « mes colis ont disparu ».
     */
    public function test_zero_colis_est_une_reponse_et_donne_une_piste(): void
    {
        $texte = NoelRepondeur::phraseDesColis([
            'colis' => 0, 'saisies' => 0, 'portee' => 'a Adjame', 'reseau' => false,
        ]);

        self::assertStringContainsString('Aucun colis', $texte);
        self::assertStringContainsString('une autre agence', $texte);
    }

    /** Proposer cette piste à qui voit tout le réseau serait absurde. */
    public function test_la_piste_de_l_autre_agence_est_tue_a_qui_voit_tout(): void
    {
        $texte = NoelRepondeur::phraseDesColis([
            'colis' => 0, 'saisies' => 0, 'portee' => 'sur l ensemble du reseau', 'reseau' => true,
        ]);

        self::assertStringContainsString('Aucun colis', $texte);
        self::assertStringNotContainsString('une autre agence', $texte);
    }

    public function test_il_donne_le_solde_et_ses_termes(): void
    {
        $texte = NoelRepondeur::phraseDeLaCaisse([
            'ouverture' => 300000.0,
            'mouvement' => 1130000.0,
            'cloture' => 1430000.0,
            'entrees' => 1250000.0,
            'sorties' => 120000.0,
        ]);

        // Le solde, puis ses termes : un chiffre sans ses termes ne se vérifie pas.
        self::assertStringContainsString('1 430 000 F', $texte);
        self::assertStringContainsString('300 000 F', $texte);
        self::assertStringContainsString('1 250 000 F', $texte);
        self::assertStringContainsString('120 000 F', $texte);

        // Et il dit d'où vient le chiffre, et jusqu'où il porte.
        self::assertStringContainsString('Mouvements de Caisse', $texte);
        self::assertStringContainsString('droit de voir', $texte);
    }

    // ------------------------------------------------------------------
    // Ne jamais inventer
    // ------------------------------------------------------------------

    /**
     * Sans journal — en test, il n'y a pas de base — NOEL oriente comme
     * avant. Il ne sort pas un chiffre de nulle part.
     */
    public function test_sans_journal_il_oriente_plutot_que_d_inventer(): void
    {
        $reponse = (new NoelRepondeur(new NoelCarte()))->repondre("combien de colis aujourd'hui ?");

        self::assertNotEmpty($reponse['liens'], 'Faute de chiffre, il montre où regarder.');
        self::assertStringNotContainsString('colis enregistre', $reponse['texte']);
    }

    /** Et le chemin de repli est explicite dans le code, pas accidentel. */
    public function test_le_repli_sur_l_orientation_est_ecrit(): void
    {
        $bloc = $this->methode('NoelRepondeur', 'chiffrer');

        self::assertStringContainsString('if ($journal === null)', $bloc);
        // Deux sorties de secours : faute de journal, et faute de chiffre.
        self::assertSame(2, substr_count($bloc, '$this->orienter($lu)'));
    }

    // ------------------------------------------------------------------
    // La portee, dans la requete elle-meme
    // ------------------------------------------------------------------

    /**
     * Le compte des colis se borne à l'agence, sauf pour qui voit le réseau.
     */
    public function test_le_compte_des_colis_se_borne_a_l_agence(): void
    {
        $bloc = $this->methode('NoelJournal', 'colisDuJour');

        self::assertStringContainsString('ROLES_PORTEE_RESEAU', $bloc, 'La portée réseau décide de la borne.');
        self::assertStringContainsString('c.agence_depart_id = :agence', $bloc);
        self::assertStringContainsString("c.statut <> 'annule'", $bloc, "Un colis annulé n'a pas été enregistré.");
        self::assertStringContainsString('CURDATE()', $bloc);

        // Sans agence ni portée réseau, on ne compte rien plutôt que tout.
        self::assertStringContainsString('if (!$reseau && $agence <= 0)', $bloc);
    }

    /**
     * Le solde ne se recalcule pas ici.
     *
     * L'écran Mouvements de Caisse sait déjà le faire, avec la portée, les
     * deux monnaies et le report de la veille. Refaire la somme donnerait un
     * jour deux chiffres différents pour la même caisse.
     */
    public function test_le_solde_est_celui_de_l_ecran_et_non_un_second_calcul(): void
    {
        $bloc = $this->methode('NoelJournal', 'soldeDeCaisse');

        self::assertStringContainsString('MouvementsCaisseService::creer()', $bloc);
        self::assertStringNotContainsString('SELECT', $bloc, 'Aucune requête de plus : le service fait foi.');
        self::assertStringNotContainsString('lbp_paiements', $bloc);
    }

    /**
     * Et il ne réveille pas la base en test.
     *
     * Database::getConnection() n'échoue pas : il arrête le processus. Un
     * try/catch ne protégerait de rien, et toute la suite tomberait dès que
     * NOEL compte.
     */
    public function test_le_solde_s_abstient_en_test(): void
    {
        $bloc = $this->methode('NoelJournal', 'soldeDeCaisse');

        self::assertStringContainsString("=== 'testing'", $bloc);
        self::assertStringContainsString('return null;', $bloc);
    }
}
