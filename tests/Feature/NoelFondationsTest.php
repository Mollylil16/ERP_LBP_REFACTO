<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Security\NoelAcces;
use App\Security\ModuleReserve;
use App\Services\Noel\NoelCarte;
use Tests\TestCase;

/**
 * Les fondations de NOEL, l'assistant interne de La Belle Porte.
 *
 * Le plus important n'est pas ce que NOEL sait dire : c'est ce qu'il ne peut
 * pas dire. Nous avons passé des semaines à fermer des fuites — la caisse
 * d'une autre agence atteignable par l'adresse, les créances de tout le réseau
 * sur un écran d'agence, le module RH rendu invisible. Un assistant qui
 * interrogerait la base sans repasser par ces règles les annulerait toutes
 * d'un coup, et sans bruit.
 */
final class NoelFondationsTest extends TestCase
{
    public function test_la_carte_se_construit_sur_le_catalogue_existant(): void
    {
        /*
         * Et non sur une seconde liste écrite à côté, qui dériverait. C'est
         * exactement ce qui était arrivé aux demandes de fonds : la liste
         * reconnaissait le comptable que la fiche détaillée avait oublié.
         */
        $source = (string) file_get_contents(BASE_PATH . '/app/Services/Noel/NoelCarte.php');

        self::assertStringContainsString('ModuleDashboardService', $source);
        self::assertStringContainsString("\$this->modules->dashboard(\$slug)['navigation']", $source);
    }

    public function test_un_ecran_refuse_par_les_droits_n_entre_pas_dans_la_carte(): void
    {
        /*
         * Chaque entrée de navigation porte déjà son « available » — le même
         * contrôle qui décide si la tuile s'affiche. NOEL s'arrête dessus.
         */
        $source = (string) file_get_contents(BASE_PATH . '/app/Services/Noel/NoelCarte.php');

        self::assertStringContainsString("(\$entree['available'] ?? false) !== true", $source);

        // Et la preuve par les chiffres : sans personne de connectée, les
        // écrans réservés de Finance sont écartés.
        $carte = new NoelCarte();
        $finance = $carte->ecransDuModule('finance');
        $urls = array_column($finance, 'url');

        self::assertNotEmpty($finance, 'La carte de Finance ne doit pas être vide.');
        self::assertNotContains('finance/controle-caisse', $urls, 'Le contrôle des caisses est réservé à la direction.');
        self::assertNotContains('finance/mouvements-caisse', $urls, 'Les mouvements de caisse sont réservés.');
    }

    public function test_noel_ignore_un_module_reserve_au_lieu_de_le_refuser(): void
    {
        /*
         * Répondre « je ne peux pas vous en parler » vendrait la mèche : cela
         * confirmerait l'existence du module RH et qu'il y a une porte à
         * demander. NOEL l'ignore, purement et simplement — comme le routeur
         * répond 404 et non « accès refusé ».
         */
        self::assertArrayHasKey('rh', ModuleReserve::RESERVES);
        self::assertFalse(NoelAcces::peutEvoquerModule('rh'), 'Sans droits, le module RH ne doit pas exister pour NOEL.');
        self::assertTrue(NoelAcces::peutEvoquerModule('finance'));

        $modules = array_keys((new NoelCarte())->modulesVisibles());
        self::assertNotContains('rh', $modules);
    }

    public function test_les_modules_gardes_par_leur_controleur_sont_fermes_aussi(): void
    {
        /*
         * Constaté le 28/10/2026 : NOEL annonçait au comptable les sept écrans
         * de l'Administration et les cinq du Pilotage DG. Leurs entrées de
         * menu portent « available => true » en dur — c'est le contrôleur qui
         * refuse, à l'ouverture. NOEL envoyait donc sur des portes fermées,
         * ce qui est la pire façon d'aider quelqu'un.
         */
        self::assertFalse(NoelAcces::peutEvoquerModule('admin'));
        self::assertFalse(NoelAcces::peutEvoquerModule('pilotage-dg'));

        // Et les modules dont le menu porte le vrai contrôle restent ouverts.
        self::assertTrue(NoelAcces::peutEvoquerModule('finance'));
        self::assertTrue(NoelAcces::peutEvoquerModule('colisage'));

        $modules = array_keys((new NoelCarte())->modulesVisibles());
        self::assertNotContains('admin', $modules);
        self::assertNotContains('pilotage-dg', $modules);
    }

    public function test_le_verrou_suit_ce_que_les_controleurs_exigent(): void
    {
        // Si un jour l'Administration s'ouvre à un rôle, ce test tombe et
        // oblige à mettre NOEL d'accord avec le logiciel.
        $middleware = (string) file_get_contents(BASE_PATH . '/app/Middleware/AdminMiddleware.php');
        self::assertStringContainsString('!$user->isAdmin', $middleware);

        $pilotage = (string) file_get_contents(
            BASE_PATH . '/app/Controllers/PilotageDg/PilotageDgDashboardController.php'
        );
        self::assertStringContainsString("RoleMiddleware::check(['dg', 'admin'])", $pilotage);
    }

    public function test_la_carte_ne_coute_aucune_requete(): void
    {
        /*
         * NOEL dresse sa carte à chaque question posée. Si elle coûtait seize
         * requêtes, il deviendrait le premier consommateur de la base. Le
         * catalogue est fait de tableaux littéraux : ce test le garde ainsi.
         */
        $carte = new NoelCarte();

        $debut = microtime(true);
        $carte->modulesVisibles();
        $carte->modulesVisibles();
        $duree = microtime(true) - $debut;

        self::assertLessThan(0.5, $duree, 'La carte met trop longtemps : une requête s\'y est glissée.');
        self::assertGreaterThanOrEqual(10, count($carte->modulesVisibles()));
    }

    public function test_la_recherche_mene_au_bon_ecran(): void
    {
        $carte = new NoelCarte();

        $cas = [
            'où je soumets mon point de caisse' => 'finance/clotures',
            'je veux créer une demande de fonds' => 'finance/fonds',
            'suivi colisage des agences' => 'logistique/colisage',
        ];

        foreach ($cas as $question => $attendu) {
            $urls = array_column($carte->chercherEcrans($question, 5), 'url');
            self::assertContains($attendu, $urls, 'Question restée sans réponse : ' . $question);
        }
    }

    public function test_la_recherche_ecarte_la_grammaire(): void
    {
        // « comment » et « pour » n'apprennent rien ; « caisse » si.
        $mots = NoelCarte::motsUtiles('comment est-ce que je fais pour ouvrir ma caisse');

        self::assertContains('caisse', $mots);
        self::assertContains('ouvrir', $mots);
        self::assertNotContains('comment', $mots);
        self::assertNotContains('pour', $mots);
    }

    public function test_la_recherche_ignore_les_accents(): void
    {
        // « reglement » doit trouver « Règlements », sinon la recherche n'est
        // utilisable que par ceux qui accentuent en tapant.
        self::assertSame('reglement depose', NoelCarte::sansAccent('Règlement déposé'));
    }

    public function test_l_interrupteur_existe_et_la_cle_n_est_pas_dans_le_depot(): void
    {
        $config = require BASE_PATH . '/config/noel.php';

        self::assertArrayHasKey('actif', $config);
        self::assertArrayHasKey('messages_par_jour_par_agent', $config);
        self::assertArrayHasKey('messages_par_jour_total', $config);

        /*
         * Aucune clé écrite en dur, contrairement à config/infobip.php qui en
         * porte une en clair dans le dépôt. Ce fichier part sur GitHub.
         */
        $source = (string) file_get_contents(BASE_PATH . '/config/noel.php');
        self::assertStringContainsString("getenv('NOEL_CLE_API') ?: ''", $source);
        self::assertSame('', $config['cle_api'], 'Une clé d\'API s\'est glissée dans le dépôt.');
    }

    public function test_noel_sait_dire_qu_il_ne_peut_pas_converser(): void
    {
        /*
         * Sans clé, NOEL garde sa valeur : il connaît la carte du logiciel et
         * sait où se trouve un écran. Il doit le dire plutôt que de faire
         * semblant — un assistant qui invente une procédure est pire qu'aucun.
         */
        self::assertFalse(NoelAcces::saitConverser(), 'Sans clé configurée, NOEL ne converse pas.');
    }

    public function test_seuls_le_dg_et_l_administrateur_recoivent_les_rapports(): void
    {
        // Décidé le 22/10/2026 : « personne n'a le droit de voir les rapports
        // à part moi seul ».
        $source = (string) file_get_contents(BASE_PATH . '/app/Security/NoelAcces.php');

        self::assertStringContainsString('estDestinataireDesRapports', $source);
        self::assertStringContainsString("Auth::isAdmin() || Auth::hasAnyRole(['dg'])", $source);
        self::assertFalse(NoelAcces::estDestinataireDesRapports(), 'Sans compte, personne ne reçoit les rapports.');
    }
}
