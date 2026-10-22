<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\View\Components\RhGuide;
use App\View\Navigation\RhNavigation;
use Tests\TestCase;

/**
 * Le guide interactif du module RH.
 *
 * Un manuel se lit une fois puis se range ; ce guide se déroule geste après
 * geste, à l'intérieur même du module. Les tests gardent les deux promesses
 * qui le rendent utilisable : les boutons « Ouvrir » mènent quelque part, et
 * l'avancement coché ne fait jamais tomber la page.
 */
final class RhGuideTest extends TestCase
{
    private function composant(): string
    {
        return (string) file_get_contents(BASE_PATH . '/app/View/Components/RhGuide.php');
    }

    private function page(): string
    {
        return RhGuide::page();
    }

    public function test_la_route_et_l_entree_de_menu_existent(): void
    {
        $routes = (string) file_get_contents(BASE_PATH . '/routes/rh.php');

        self::assertStringContainsString("get('/guide'", $routes);
        self::assertStringContainsString('RhGuideController::class', $routes);

        $cles = array_column(RhNavigation::items(), 'key');
        self::assertContains('guide', $cles);

        foreach (RhNavigation::items() as $entree) {
            if ($entree['key'] === 'guide') {
                // Le guide se cherche le jour où l'on est bloqué, donc en haut
                // du menu, pas en bas avec la configuration.
                self::assertSame('Pilotage', $entree['group']);
                self::assertSame('rh/guide', $entree['url']);
            }
        }
    }

    public function test_le_controleur_ne_redouble_pas_le_verrou_du_module(): void
    {
        /*
         * L'adresse commence par /rh : ModuleReserve a déjà refusé tout le
         * monde sauf le DG, la responsable RH et l'administrateur. Un second
         * contrôle ne fermerait rien de plus et ferait croire, à la lecture,
         * que la règle se tient à deux endroits.
         */
        $controleur = (string) file_get_contents(
            BASE_PATH . '/app/Controllers/Rh/RhGuideController.php'
        );

        self::assertStringContainsString('AuthMiddleware::check()', $controleur);
        self::assertStringNotContainsString('RoleMiddleware', $controleur);
        // Et le verrou, lui, existe bien pour ce module.
        self::assertArrayHasKey('rh', \App\Security\ModuleReserve::RESERVES);
        self::assertSame(
            ['admin', 'dg', 'responsable_rh'],
            \App\Security\ModuleReserve::RESERVES['rh']
        );
    }

    public function test_les_six_parcours_sont_complets(): void
    {
        $page = $this->page();

        foreach (['integrer', 'pointer', 'paie', 'discipline', 'depart', 'annee'] as $cle) {
            self::assertStringContainsString('data-rhg-parcours="' . $cle . '"', $page, $cle);
        }

        self::assertSame(6, substr_count($page, 'data-rhg-parcours='));

        // Un parcours d'une ou deux étapes ne serait pas un parcours.
        self::assertGreaterThanOrEqual(24, substr_count($page, 'class="rhg-coche"'));
    }

    public function test_chaque_bouton_ouvrir_mene_a_une_route_existante(): void
    {
        /*
         * C'est le test qui compte. Un guide qui envoie sur une page
         * introuvable est pire que pas de guide : il fait douter la personne
         * de ce qu'elle a compris, au lieu de la faire douter du lien.
         */
        $routes = (string) file_get_contents(BASE_PATH . '/routes/rh.php');

        preg_match_all("/'url' => '(rh\/[^']+)'/", $this->composant(), $trouves);
        $urls = array_unique($trouves[1]);

        self::assertNotEmpty($urls, 'Le guide ne propose aucun lien.');

        $introuvables = [];
        foreach ($urls as $url) {
            $chemin = explode('?', $url)[0];              // on laisse ?section=…
            $reste = substr($chemin, strlen('rh'));        // « /personnel/nouveau »
            $dernier = '/' . basename($chemin);            // « /nouveau »

            /*
             * Un chemin peut être déclaré de deux façons : comme route simple,
             * ou comme racine d'un groupe — « rh/personnel » est le group()
             * dont le get('/') interne porte la liste. Ignorer le second cas
             * ferait échouer le test sur un lien pourtant valide.
             */
            $declaree = str_contains($routes, "get('" . $reste . "'")
                || str_contains($routes, "get('" . $dernier . "'")
                || str_contains($routes, "group('" . $reste . "'");

            if (!$declaree) {
                $introuvables[] = $url;
            }
        }

        self::assertSame([], $introuvables, 'Liens du guide sans route : ' . implode(', ', $introuvables));
    }

    public function test_les_ecrans_hors_menu_sont_signales(): void
    {
        /*
         * Cinq écrans ne figurent nulle part dans la barre de gauche et ne
         * s'atteignent que depuis le tableau de bord. Sans cette étiquette, on
         * les cherche en vain dans le menu.
         */
        $page = $this->page();
        $menu = array_column(RhNavigation::items(), 'url');

        // La classe apparaît aussi dans la feuille de style : on compte les
        // étiquettes posées, pas la règle qui les dessine.
        self::assertSame(5, substr_count($page, 'class="rhg-etiquette"'));

        foreach (['rh/missions', 'rh/explications', 'rh/regles-contrats', 'rh/signataires'] as $url) {
            self::assertNotContains($url, $menu, $url . ' figure au menu : son étiquette est fausse.');
            self::assertStringContainsString($url, $page, $url . ' manque au guide.');
        }
    }

    public function test_la_page_ne_depend_de_rien_d_exterieur(): void
    {
        $page = $this->page();

        self::assertStringNotContainsString('src="http', $page);
        self::assertStringNotContainsString('<img', $page);
        self::assertStringNotContainsString('url(', $page);

        // Règle du projet : jamais d'emoji, uniquement du SVG au trait.
        self::assertSame(
            0,
            preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $page),
            'Un emoji s\'est glissé dans le guide.'
        );
        self::assertStringContainsString('stroke="currentColor"', $page);
    }

    public function test_l_avancement_ne_fait_jamais_tomber_la_page(): void
    {
        /*
         * En navigation privée, ou quand le poste bloque les données de site,
         * localStorage lève une exception à la simple lecture. Sans ces
         * protections, le guide s'afficherait vide — et la personne croirait
         * le module cassé.
         */
        $composant = $this->composant();

        self::assertSame(2, substr_count($composant, 'catch (e)'));
        self::assertStringContainsString('function lire()', $composant);
        self::assertStringContainsString('function ecrire(', $composant);

        // L'avancement reste sur le poste : savoir qui en est où dans sa
        // formation ne regarde pas l'entreprise.
        self::assertStringContainsString('localStorage', $composant);
        self::assertStringNotContainsString('fetch(', $composant);
    }

    public function test_la_recherche_traverse_les_trois_onglets(): void
    {
        /*
         * Filtrer le seul onglet ouvert répondrait « aucun résultat » sur un
         * mot pourtant présent deux onglets plus loin, et la recherche
         * passerait pour cassée.
         */
        $page = $this->page();
        $composant = $this->composant();

        self::assertStringContainsString('data-rhg-recherche', $page);
        self::assertGreaterThanOrEqual(30, substr_count($page, 'data-rhg-cherche='));
        self::assertStringContainsString('.finea-inline-pane', $composant);

        // Les accents ne doivent pas empêcher de trouver : « reference » doit
        // ramener « référence ».
        self::assertStringContainsString('normalize(\'NFD\')', $composant);
    }

    public function test_les_mots_cles_couvrent_les_champs_reellement_saisis(): void
    {
        // On cherche le mot qu'on a sous les yeux dans un formulaire, pas le
        // nom de l'écran qui le porte.
        $page = mb_strtolower($this->page());

        foreach (['cnps', 'acompte', 'matricule', 'sursalaire', 'parts fiscales', 'heures supplémentaires'] as $mot) {
            self::assertStringContainsString($mot, $page, 'Introuvable dans le guide : ' . $mot);
        }
    }
}
