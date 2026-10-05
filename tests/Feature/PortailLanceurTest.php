<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\View\Components\ModuleCatalog;
use Tests\TestCase;

/**
 * Le portail est un lanceur, pas une page de lecture.
 *
 * Il affichait dix-huit tuiles de poids égal, chacune avec sa description, et
 * un bandeau de 240 pixels pour une phrase de politesse. Rien ne guidait
 * l'œil, et il fallait défiler pour atteindre ce qu'on ouvre tous les matins.
 *
 * Décidé le 05/10/2026 : tuiles compactes rangées par métier, description au
 * survol seulement, et une rangée en tête pour les modules du quotidien.
 */
final class PortailLanceurTest extends TestCase
{
    /** @return array<int, array<string, mixed>> */
    private function modules(): array
    {
        $faire = static fn (string $cle, string $nom, string $code, string $desc): array => [
            'key' => $cle, 'label' => $nom, 'code' => $code, 'icon' => 'admin',
            'description' => $desc, 'url' => '/' . $cle . '/dashboard',
            'class' => 'module-' . $cle, 'status' => 'Disponible', 'is_maintenance' => false,
        ];

        return [
            $faire('finance', 'Finance', 'FIN', 'Caisses, fonds, factures.'),
            $faire('colisage', 'Colisage', 'COL', 'Colis, factures clients.'),
            $faire('logistique', 'Logistique', 'LOG', 'Magasin et emballages.'),
            $faire('crm', 'CRM', 'CRM', 'Clients et prospects.'),
            $faire('admin', 'Admin', 'ADM', 'Utilisateurs et droits.'),
        ];
    }

    public function test_les_tuiles_sont_rangees_par_metier(): void
    {
        $html = ModuleCatalog::moduleGrid($this->modules());

        self::assertStringContainsString('>Exploitation</h2>', $html);
        self::assertStringContainsString('>Finance &amp; administration</h2>', $html);
        self::assertStringContainsString('>Relation client</h2>', $html);
    }

    public function test_un_module_non_range_reste_visible(): void
    {
        /*
         * Un portail qui perd une tuile en silence est pire qu'un portail mal
         * rangé : personne ne vient dire « il me manque un module » tant qu'il
         * n'en a pas besoin, et ce jour-là il est bloqué.
         */
        $modules = $this->modules();
        $modules[] = [
            'key' => 'module-de-demain', 'label' => 'Module de demain', 'code' => 'DEM',
            'icon' => 'admin', 'description' => '', 'url' => '/demain', 'is_maintenance' => false,
        ];

        $html = ModuleCatalog::moduleGrid($modules);

        self::assertStringContainsString('>Autres outils</h2>', $html);
        self::assertStringContainsString('data-module-key="module-de-demain"', $html);
    }

    public function test_la_rangee_du_haut_propose_le_quotidien(): void
    {
        $html = ModuleCatalog::moduleGrid($this->modules());

        self::assertStringContainsString('data-portail-rapides', $html);
        self::assertStringContainsString('Accès rapides', $html);
        // Les tuiles rapides ne portent pas data-module-card : sans quoi le
        // filtre de recherche compterait chaque module deux fois.
        self::assertSame(
            count($this->modules()),
            substr_count($html, 'data-module-card'),
            'Chaque module ne doit être compté qu\'une fois par la recherche.'
        );
    }

    public function test_pas_de_rangee_du_haut_pour_un_seul_module(): void
    {
        // Une tuile seule sur fond sombre n'est pas une rangée, c'est un vide
        // qui prend de la hauteur.
        $html = ModuleCatalog::moduleGrid([$this->modules()[0]]);

        self::assertStringNotContainsString('data-portail-rapides', $html);
    }

    public function test_la_description_est_rendue_mais_hors_du_flux(): void
    {
        $html = ModuleCatalog::moduleCard($this->modules()[0]);

        // Elle reste dans le document pour les lecteurs d'écran ; le CSS la
        // montre au survol seulement.
        self::assertStringContainsString('portail-desc', $html);
        self::assertStringContainsString('Caisses, fonds, factures.', $html);
    }

    public function test_le_portail_salue_par_le_prenom(): void
    {
        // « Bonjour BRUNELL OMEPIEUR » sonne comme une convocation.
        $hero = ModuleCatalog::hero('BRUNELL OMEPIEUR', 18);

        self::assertStringContainsString('Bonjour Brunell.', $hero);
        self::assertStringNotContainsString('OMEPIEUR', $hero);
    }

    public function test_un_module_en_maintenance_ne_s_ouvre_pas(): void
    {
        $module = $this->modules()[0];
        $module['is_maintenance'] = true;
        $module['maintenance_reason'] = 'Migration de la base en cours.';

        $html = ModuleCatalog::moduleCard($module);

        self::assertStringContainsString('aria-disabled="true"', $html);
        self::assertStringContainsString('Migration de la base en cours.', $html);
        self::assertStringNotContainsString('<a ', $html);
    }

    public function test_le_style_a_quitte_le_php(): void
    {
        /*
         * Le portail portait 180 lignes de CSS au milieu du PHP et des
         * style="..." en dur : c'est pour cela qu'il vieillissait à part du
         * reste du logiciel, personne n'allant le retoucher là.
         */
        $source = (string) file_get_contents(BASE_PATH . '/app/View/Components/ModuleCatalog.php');

        self::assertStringNotContainsString('<style>', $source);
        self::assertFileExists(BASE_PATH . '/public/assets/css/portail.css');
    }

    public function test_la_feuille_de_style_est_chargee(): void
    {
        $vue = (string) file_get_contents(BASE_PATH . '/views/selection_portail/index.php');
        $gabarit = (string) file_get_contents(BASE_PATH . '/views/layouts/app.php');

        self::assertStringContainsString("'css/portail.css'", $vue);
        self::assertStringContainsString('additionalStyles', $gabarit);
    }
}
