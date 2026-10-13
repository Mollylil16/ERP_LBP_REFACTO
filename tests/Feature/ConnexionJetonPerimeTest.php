<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Helpers\Csrf;
use App\Helpers\Session;
use Tests\TestCase;

/**
 * La page de connexion ne doit jamais se fermer sur un cul-de-sac.
 *
 * Quand le jeton de la page n'etait plus reconnu — un onglet laisse ouvert la
 * veille suffit —, le logiciel affichait « Jeton CSRF invalide » puis renvoyait
 * l'agent sur la page precedente. La phrase ne dit rien a un agent, et le
 * retour dependait du referent du navigateur : quand il manque, on retombe sur
 * la meme page morte. KOUAKOU SALES est reste dehors du 09 au 13/10/2026 pour
 * cette seule raison, compte actif et mot de passe bon.
 */
final class ConnexionJetonPerimeTest extends TestCase
{
    private function controleur(): string
    {
        return (string) file_get_contents(
            BASE_PATH . '/app/Controllers/Auth/AuthController.php'
        );
    }

    public function test_le_message_technique_a_disparu(): void
    {
        // Le libelle reste cite en commentaire, pour la memoire du defaut ; ce
        // qui ne doit plus exister, c'est son affichage a l'agent.
        self::assertStringNotContainsString(
            "flash('error', 'Jeton CSRF invalide.')",
            $this->controleur()
        );
    }

    public function test_le_formulaire_est_reaffiche_au_lieu_du_retour_arriere(): void
    {
        /*
         * Le retour arriere etait le defaut : sans referent, il ramenait sur la
         * page expiree. On rend la page nous-memes, donc avec un jeton frais.
         */
        $source = $this->controleur();

        $debut = (int) strpos($source, 'public function login');
        $branche = substr($source, $debut, (int) strpos($source, 'authService->login') - $debut);

        self::assertStringNotContainsString('$this->back()', $branche);
        self::assertStringContainsString('jetonPerime', $branche);
        self::assertStringContainsString("\$this->view('auth/login'", $source);
    }

    public function test_les_deux_causes_sont_distinguees(): void
    {
        /*
         * Cookie refuse par le navigateur et page trop vieille demandent deux
         * gestes differents. Les confondre renvoie l'agent sur une fausse piste.
         */
        $source = $this->controleur();

        self::assertStringContainsString('session_name()', $source);
        self::assertStringContainsString("n'a pas accepté le cookie", $source);
        self::assertStringContainsString('ouverte depuis trop longtemps', $source);
    }

    public function test_la_page_rendue_porte_le_message_et_un_jeton_utilisable(): void
    {
        Session::flash(
            'error',
            "Cette page de connexion était ouverte depuis trop longtemps et n'était plus valide."
        );

        ob_start();
        $pageTitle = 'Connexion';
        require BASE_PATH . '/views/auth/login.php';
        $html = (string) ob_get_clean();

        self::assertStringContainsString('ouverte depuis trop longtemps', $html);

        // Le jeton present dans la page doit etre celui de la session en cours,
        // sans quoi la nouvelle tentative echouerait exactement comme la precedente.
        self::assertStringContainsString(
            'name="_csrf_token" value="' . Csrf::token() . '"',
            $html
        );
    }
}
