<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Noel\NoelApprentissage;
use App\Services\Noel\NoelMesure;
use Tests\TestCase;

/**
 * Ce qu'on apprend à NOEL, et ce qu'on mesure de lui.
 *
 * Les 723 expressions de son dictionnaire, c'est moi qui les ai devinées. Le
 * vrai dictionnaire s'écrira avec les mots du personnel : chaque question que
 * NOEL ne comprend pas peut devenir une nouvelle entrée.
 *
 * Mais rien ne part sans que la personne l'ait voulu. Une question incomprise
 * peut être une demande d'aide — comme une confidence.
 */
final class NoelApprentissageTest extends TestCase
{
    private function sourceApprentissage(): string
    {
        return (string) file_get_contents(BASE_PATH . '/app/Services/Noel/NoelApprentissage.php');
    }

    private function sourceMesure(): string
    {
        return (string) file_get_contents(BASE_PATH . '/app/Services/Noel/NoelMesure.php');
    }

    public function test_la_mesure_ne_conserve_aucun_texte(): void
    {
        /*
         * Le cœur de la cloison du 22/10/2026. On peut savoir si NOEL sert
         * sans jamais garder ce que quelqu'un lui a écrit : des compteurs par
         * jour et par sujet, rien d'autre.
         */
        $migration = (string) file_get_contents(BASE_PATH . '/app/Database/MigrationRunner.php');

        $debut = (int) strpos($migration, 'CREATE TABLE IF NOT EXISTS lbp_noel_mesures');
        $table = substr($migration, $debut, 700);

        self::assertStringNotContainsString('TEXT', $table, 'La table de mesure ne doit porter aucun texte.');
        self::assertStringNotContainsString('question', $table);
        self::assertStringNotContainsString('user_id', $table, 'Même le nom de la personne est de trop.');
        self::assertStringContainsString('comprises', $table);
        self::assertStringContainsString('incomprises', $table);
    }

    public function test_rien_ne_part_sans_que_la_personne_l_ait_voulu(): void
    {
        /*
         * NOEL propose, la personne accepte. Transmettre d'office aurait été
         * plus simple et aurait coûté la confiance de tout le monde.
         */
        $proposition = NoelApprentissage::proposition();

        self::assertStringContainsString("n'ai pas compris", $proposition);
        self::assertStringContainsString('Voulez-vous', $proposition);
        self::assertStringContainsString('que si vous me le demandez', $proposition);
    }

    public function test_noel_dit_qu_il_ne_sait_pas_plutot_que_de_repondre_a_cote(): void
    {
        // Un assistant qui invente une procédure est pire qu'aucun assistant.
        self::assertStringContainsString(
            'prefere le dire plutot que de repondre a cote',
            NoelApprentissage::proposition()
        );
    }

    public function test_les_questions_gardees_ne_se_lisent_pas_par_tout_le_monde(): void
    {
        /*
         * Ce sont les mots d'agents identifiés, pas un tableau de bord public.
         * Réservés à l'administrateur et au directeur.
         */
        $source = $this->sourceApprentissage();

        self::assertSame(
            3,
            substr_count($source, 'NoelAcces::estDestinataireDesRapports()'),
            'Lecture, réponse et changement de statut doivent tous être protégés.'
        );
    }

    public function test_une_question_vide_ne_se_transmet_pas(): void
    {
        $source = $this->sourceApprentissage();

        self::assertStringContainsString("if (\$question === '')", $source);
        self::assertStringContainsString('return null;', $source);
    }

    public function test_une_question_apprise_n_est_pas_effacee(): void
    {
        /*
         * La ligne dit ce que NOEL a appris et quand. C'est le seul moyen de
         * savoir si le dictionnaire grandit vraiment, ou si l'on se contente
         * de répondre une fois et d'oublier.
         */
        $source = $this->sourceApprentissage();

        self::assertStringContainsString('marquerApprise', $source);
        self::assertStringNotContainsString('DELETE FROM lbp_noel_apprentissages', $source);
    }

    public function test_la_personne_est_prevenue_quand_c_est_regle(): void
    {
        /*
         * Quelqu'un qui signale un blocage et n'en entend plus parler ne
         * signalera pas le suivant.
         */
        $retour = NoelApprentissage::retour("Votre accès a été ouvert.");

        self::assertStringContainsString('a repondu', $retour);
        self::assertStringContainsString('Votre accès a été ouvert.', $retour);
        self::assertStringContainsString('actualiser', $retour);
    }

    public function test_la_mesure_n_empeche_jamais_noel_de_repondre(): void
    {
        /*
         * Compter est utile ; aider l'est davantage. Une mesure qui échoue ne
         * doit pas faire tomber la réponse.
         */
        $source = $this->sourceMesure();

        $debut = (int) strpos($source, 'private function incrementer');
        $methode = substr($source, $debut, 1400);

        self::assertStringContainsString('catch (Throwable $e)', $methode);
        self::assertStringNotContainsString('throw', $methode);
    }

    public function test_deux_agents_simultanes_ne_perdent_pas_un_comptage(): void
    {
        /*
         * Un SELECT puis un UPDATE se marcheraient dessus : deux questions
         * posées à la même seconde, et l'un des deux comptages disparaît.
         */
        self::assertStringContainsString('ON DUPLICATE KEY UPDATE', $this->sourceMesure());
    }

    public function test_le_bilan_se_dit_en_une_phrase(): void
    {
        self::assertSame(
            "Personne n'a sollicite NOEL.",
            NoelMesure::enPhrase(['questions' => 0, 'comprises' => 0, 'incomprises' => 0, 'taux' => null])
        );

        $phrase = NoelMesure::enPhrase([
            'questions' => 14, 'comprises' => 11, 'incomprises' => 3, 'taux' => 78.6,
        ]);

        self::assertStringContainsString('14 questions posees', $phrase);
        self::assertStringContainsString('79 % comprises', $phrase);
        self::assertStringContainsString('3 restees sans reponse', $phrase);
    }

    public function test_les_points_faibles_disent_ou_enrichir_le_dictionnaire(): void
    {
        /*
         * Inutile d'ajouter cent mots sur la comptabilité si tout le monde
         * bute sur le point de caisse.
         */
        $source = $this->sourceMesure();

        self::assertStringContainsString('pointsFaibles', $source);
        self::assertStringContainsString('ORDER BY SUM(incomprises) DESC', $source);
    }

    public function test_les_deux_tables_sont_creees_sans_reprise_de_donnees(): void
    {
        /*
         * run() passe à chaque requête HTTP, et deux incidents de perte de
         * données sont déjà venus de là. Uniquement de la structure.
         */
        $migration = (string) file_get_contents(BASE_PATH . '/app/Database/MigrationRunner.php');

        $debut = (int) strpos($migration, 'private function createNoelTables');
        $methode = substr($migration, $debut, 2600);

        self::assertStringContainsString("tableExists('lbp_noel_apprentissages')", $methode);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS lbp_noel_mesures', $methode);
        self::assertStringNotContainsString('UPDATE ', $methode);
        self::assertStringNotContainsString('DELETE', $methode);
        self::assertStringNotContainsString('INSERT', $methode);
    }
}
