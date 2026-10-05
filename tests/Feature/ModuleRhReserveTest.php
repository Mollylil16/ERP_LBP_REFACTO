<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Router;
use App\Security\ModuleReserve;
use Tests\TestCase;

/**
 * Le module RH disparaît de la vue de tous.
 *
 * On y lit les salaires, les contrats, les sanctions et les sorties de chacun.
 * La responsable RH demande le 05/10/2026 qu'il ne soit visible que du
 * directeur, d'elle-même et de l'administrateur.
 *
 * Cacher la tuile ne suffisait pas : qui connaît l'adresse la tape. La porte
 * se ferme donc à l'entrée du routeur, avant tout contrôleur — et le refus est
 * un 404, parce qu'« accès refusé » confirmerait que le module existe.
 */
final class ModuleRhReserveTest extends TestCase
{
    public function test_les_trois_seuls_autorises(): void
    {
        self::assertSame(
            ['admin', 'dg', 'responsable_rh'],
            ModuleReserve::RESERVES['rh'],
            "Ni l'assistante DG, ni le superviseur, ni une permission RH ne suffisent plus."
        );
    }

    public function test_toutes_les_adresses_du_module_sont_couvertes(): void
    {
        foreach (['/rh', '/rh/dashboard', '/rh/personnel/12', '/rh/paie?employee_id=4'] as $chemin) {
            self::assertSame('rh', ModuleReserve::moduleDe($chemin), $chemin . ' doit être couvert.');
        }
    }

    public function test_l_espace_employe_n_est_pas_concerne(): void
    {
        // Chacun y consulte son propre dossier : le fermer priverait tout le
        // personnel de ses bulletins pour protéger ceux des autres.
        self::assertNull(ModuleReserve::moduleDe('/espace-employe'));
        self::assertNull(ModuleReserve::moduleDe('/espace-employe/bulletins'));
    }

    public function test_les_autres_modules_restent_ouverts(): void
    {
        foreach (['/finance/fonds', '/colisage/dashboard', '/rhume', '/crm'] as $chemin) {
            self::assertNull(ModuleReserve::moduleDe($chemin), $chemin . ' ne doit pas être verrouillé.');
        }
    }

    public function test_un_visiteur_sans_session_est_refuse(): void
    {
        self::assertTrue(ModuleReserve::refuse('/rh/personnel'));
        self::assertFalse(ModuleReserve::refuse('/finance/fonds'));
    }

    public function test_le_routeur_repond_introuvable_et_non_acces_refuse(): void
    {
        /*
         * « Accès refusé » dirait à qui tâtonne que le module existe, et à qui
         * s'adresser pour y entrer. Un 404 ne dit rien.
         */
        $router = new Router(
            static fn(string $chemin): ?array => null,
            static fn(string $chemin): bool => str_starts_with($chemin, '/rh')
        );

        $router->get('/rh/personnel', static function (): void {
            echo 'la liste du personnel';
        });

        ob_start();
        $router->dispatch('/rh/personnel', 'GET');
        $sortie = (string) ob_get_clean();

        self::assertStringNotContainsString('la liste du personnel', $sortie);
        self::assertStringNotContainsString('refusé', $sortie);
    }

    public function test_la_porte_est_fermee_avant_tout_controleur(): void
    {
        // Quinze contrôleurs RH, dont douze ont leur propre constructeur :
        // poser le contrôle dans chacun l'aurait oublié au prochain écran.
        $routeur = (string) file_get_contents(BASE_PATH . '/app/Router.php');

        self::assertStringContainsString('reserveResolver', $routeur);
        self::assertStringContainsString('ModuleReserve::class', $routeur);
    }

    public function test_la_tuile_du_portail_suit_la_meme_regle(): void
    {
        // Une tuile qui s'affiche pour qui sera refusé ensuite est pire que
        // pas de tuile du tout : elle désigne la porte.
        $portail = (string) file_get_contents(BASE_PATH . '/app/Controllers/Portal/SelectionPortailController.php');

        self::assertStringContainsString("ModuleReserve::peutOuvrir('rh')", $portail);
        self::assertStringNotContainsString(
            "Auth::hasAnyRole(['dg', 'assistant_dg']) || Auth::isAdmin()) {\n                    return true;\n                }\n                \$requirements",
            $portail
        );
    }
}
