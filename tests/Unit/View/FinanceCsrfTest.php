<?php

declare(strict_types=1);

namespace Tests\Unit\View;

use Tests\TestCase;

/**
 * Cohérence entre les routes POST protégées et les formulaires qui les appellent.
 *
 * Ajouter la vérification du jeton côté contrôleur sans l'ajouter côté formulaire
 * casse silencieusement la fonctionnalité : l'utilisateur clique, rien ne se passe,
 * et il ne comprend pas pourquoi. Ce test garde les deux côtés alignés.
 */
final class FinanceCsrfTest extends TestCase
{
    /** Méthodes POST du contrôleur Finance qui doivent vérifier le jeton. */
    private const METHODES_PROTEGEES = [
        'factureStore',
        'factureRelancerTout',
        'facturePayerPortefeuille',
        'factureRelancer',
        'factureEncaisser',
        'factureReinitialiser',
        'factureDelete',
        'clotureSoumettre',
        'clotureConsolider',
        'depenseStore',
        'depenseValider',
        'ecritureManuelleStore',
        'lettrer',
        'contrePasser',
        'planComptableStore',
        'portefeuilleCrediter',
    ];

    public function test_chaque_methode_post_verifie_le_jeton(): void
    {
        $source = $this->controleur();
        $manquantes = [];

        foreach (self::METHODES_PROTEGEES as $methode) {
            if (!str_contains($this->corpsDeMethode($source, $methode), 'Csrf::verify')) {
                $manquantes[] = $methode;
            }
        }

        self::assertSame([], $manquantes, 'Méthodes POST sans vérification du jeton : ' . implode(', ', $manquantes));
    }

    public function test_chaque_formulaire_post_porte_le_jeton(): void
    {
        $composant = (string) file_get_contents(BASE_PATH . '/app/View/Components/Finance.php');

        preg_match_all('/<form[^>]*method="post"[^>]*>/i', $composant, $trouves, PREG_OFFSET_CAPTURE);

        $sansJeton = [];
        foreach ($trouves[0] as [$balise, $position]) {
            $fenetre = substr($composant, $position, 900);

            if (!str_contains($fenetre, '_csrf_token')) {
                preg_match("/View::url\('([^']+)'/", $fenetre, $url);
                $sansJeton[] = $url[1] ?? substr($balise, 0, 60);
            }
        }

        self::assertSame([], $sansJeton, 'Formulaires POST sans jeton : ' . implode(', ', $sansJeton));
    }

    public function test_la_verification_renvoie_vers_une_page_du_module(): void
    {
        $source = $this->controleur();

        foreach (['clotureSoumettre', 'depenseValider', 'ecritureManuelleStore'] as $methode) {
            $corps = $this->corpsDeMethode($source, $methode);

            self::assertMatchesRegularExpression(
                "/Csrf::verify.*?View::url\('finance\//s",
                $corps,
                "La méthode {$methode} doit rediriger vers une page Finance après un jeton refusé."
            );
        }
    }

    public function test_le_jeton_est_verifie_apres_le_controle_de_role(): void
    {
        $source = $this->controleur();
        $corps = $this->corpsDeMethode($source, 'depenseValider');

        $role = strpos($corps, 'RoleMiddleware::check');
        $jeton = strpos($corps, 'Csrf::verify');

        self::assertNotFalse($role);
        self::assertNotFalse($jeton);
        self::assertLessThan($jeton, $role, 'L\'habilitation se vérifie avant le jeton : inutile de parler de session à qui n\'a pas le droit.');
    }

    // -----------------------------------------------------------------

    private function controleur(): string
    {
        return (string) file_get_contents(BASE_PATH . '/app/Controllers/Finance/FinanceController.php');
    }

    private function corpsDeMethode(string $source, string $methode): string
    {
        $debut = strpos($source, 'public function ' . $methode . '(');
        if ($debut === false) {
            return '';
        }

        $suivante = strpos($source, 'public function ', $debut + 20);

        return $suivante === false ? substr($source, $debut) : substr($source, $debut, $suivante - $debut);
    }
}
