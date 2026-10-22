<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Noel\NoelCompte;
use App\Services\Noel\NoelMessagerie;
use Tests\TestCase;

/**
 * NOEL dans la messagerie : il répond quand c'est à lui qu'on écrit.
 *
 * Il n'a pas d'écran à lui, et c'est voulu : deux messageries dans le même
 * logiciel obligeraient les agents à en apprendre deux. NOEL est un contact
 * comme un autre.
 */
final class NoelMessagerieTest extends TestCase
{
    private function source(): string
    {
        return (string) file_get_contents(BASE_PATH . '/app/Services/Noel/NoelMessagerie.php');
    }

    public function test_le_branchement_tient_en_un_seul_appel(): void
    {
        /*
         * Le fichier qui envoie les messages appartient à la messagerie, pas à
         * NOEL. Plus on y touche, plus on gêne ceux qui le font évoluer — et
         * plus le risque est grand qu'une refonte fasse disparaître
         * l'accrochage sans que personne ne s'en aperçoive.
         */
        self::assertStringContainsString('public static function surMessage', $this->source());
        self::assertStringContainsString('NoelMessagerie::surMessage(', NoelMessagerie::ACCROCHE);
        self::assertSame(1, substr_count(trim(NoelMessagerie::ACCROCHE), "\n") + 1);
    }

    public function test_noel_ne_casse_jamais_la_messagerie(): void
    {
        /*
         * Quand NOEL échoue, le message de la personne est déjà enregistré et
         * part normalement. Un assistant en panne ne doit pas emporter la
         * messagerie de l'entreprise avec lui.
         */
        $source = $this->source();

        $debut = (int) strpos($source, 'public static function surMessage');
        $methode = substr($source, $debut, 1600);

        self::assertStringContainsString('catch (Throwable $e)', $methode);
        self::assertStringNotContainsString('throw ', $methode);
    }

    public function test_il_ne_parle_jamais_dans_un_groupe(): void
    {
        /*
         * NOEL qui s'invite dans une discussion entre collègues serait au
         * mieux pénible, au pire indiscret — et il n'aurait aucun moyen de
         * savoir à qui il répond.
         */
        $source = $this->source();

        self::assertStringContainsString("!== 'direct'", $source);
        self::assertStringContainsString('return false;', $source);
    }

    public function test_il_ne_se_repond_pas_a_lui_meme(): void
    {
        // Sans quoi la boucle n'aurait aucune raison de s'arrêter.
        self::assertStringContainsString('$auteurId === $noel', $this->source());
    }

    public function test_l_interrupteur_le_fait_taire(): void
    {
        // Le jour où il dit une bêtise devant tout le personnel, on ne cherche
        // pas quel fichier modifier.
        self::assertStringContainsString('NoelAcces::estAllume()', $this->source());
    }

    public function test_il_repond_dans_le_fil_et_pas_ailleurs(): void
    {
        $source = $this->source();

        self::assertStringContainsString("'conversation_id' => \$conversationId", $source);
        self::assertStringContainsString("'auteur_id' => (int) NoelCompte::id()", $source);
    }

    public function test_la_cle_de_discussion_suit_la_convention_de_la_messagerie(): void
    {
        /*
         * « petit-grand » des deux identifiants, exactement comme
         * MessagerieService::ouvrirDirecte. Une convention différente créerait
         * deux discussions parallèles pour la même paire.
         */
        $source = $this->source();
        $messagerie = (string) file_get_contents(
            BASE_PATH . '/app/Services/Messagerie/MessagerieService.php'
        );

        self::assertStringContainsString("min(\$noel, \$utilisateurId) . '-' . max(", $source);
        self::assertStringContainsString("min(\$autreId, \$this->utilisateurId) . '-' . max(", $messagerie);
    }

    public function test_personne_ne_peut_se_connecter_avec_le_compte_de_noel(): void
    {
        /*
         * Un compte de service qui garde le droit de parler à tout le monde
         * serait une belle porte d'entrée s'il était ouvrable. Son mot de
         * passe est tiré au hasard et conservé nulle part : il n'y a aucune
         * combinaison à deviner, parce qu'il n'y en a aucune.
         */
        $compte = (string) file_get_contents(BASE_PATH . '/app/Services/Noel/NoelCompte.php');

        self::assertStringContainsString('password_hash(bin2hex(random_bytes(32))', $compte);
        // Ni administrateur, ni rattache a une agence : ses droits propres ne
        // servent qu'a poser un message dans un fil.
        self::assertStringContainsString('is_admin, created_at', $compte);
        self::assertStringContainsString('VALUES (:nom, :email, :hash, \'active\', 0, NOW())', $compte);
    }

    public function test_le_compte_se_cree_a_la_premiere_utilisation_pas_a_chaque_requete(): void
    {
        /*
         * run() de MigrationRunner passe à chaque requête HTTP, et deux pertes
         * de données sont déjà venues d'y avoir mis des écritures. La ligne de
         * NOEL n'est touchée que le jour où quelqu'un lui parle.
         */
        $compte = (string) file_get_contents(BASE_PATH . '/app/Services/Noel/NoelCompte.php');
        $migration = (string) file_get_contents(BASE_PATH . '/app/Database/MigrationRunner.php');

        self::assertStringContainsString('INSERT INTO users', $compte);
        self::assertStringNotContainsString('NoelCompte', $migration);
    }

    public function test_une_creation_simultanee_ne_fait_pas_deux_comptes(): void
    {
        // L'email est unique : si l'insertion échoue, on relit avant
        // d'abandonner plutôt que de rendre NOEL muet.
        $compte = (string) file_get_contents(BASE_PATH . '/app/Services/Noel/NoelCompte.php');

        $debut = (int) strpos($compte, 'private static function creer');
        $methode = substr($compte, $debut, 1600);

        self::assertStringContainsString('return self::chercher($pdo);', $methode);
    }
}
