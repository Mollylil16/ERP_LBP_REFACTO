<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Shared\AvisPortail;
use App\View\Components\Avis;
use Tests\TestCase;

/**
 * L'avis de la direction sur le portail.
 *
 * Posé pour annoncer la fermeture obligatoire de la caisse au 1er octobre. Il
 * dure quelques jours et s'efface seul : un message qui reste des semaines
 * n'est plus lu, et l'on finit par ne plus voir non plus celui qui compte.
 *
 * Les dates ne sont pas recopiées ici : elles changent à chaque annonce, et un
 * test qui les fige se casse au lieu de vérifier la règle.
 */
final class AvisPortailTest extends TestCase
{
    /** Le premier jour compte : un avis de cinq jours posé le 29 se lit jusqu'au 3. */
    public function test_l_avis_dure_le_nombre_de_jours_annonce(): void
    {
        foreach (AvisPortail::tous() as $avis) {
            $jour = $avis['debut'];

            for ($n = 0; $n < $avis['jours']; $n++) {
                self::assertCount(1, AvisPortail::actifs($jour), "L'avis doit être lisible le {$jour}.");
                $jour = date('Y-m-d', (int) strtotime($jour . ' +1 day'));
            }

            self::assertSame([], AvisPortail::actifs($jour), "Le jour suivant, l'avis a disparu.");
            self::assertSame([], AvisPortail::actifs(date('Y-m-d', (int) strtotime($jour . ' +6 months'))));
        }
    }

    /** Rien ne s'affiche avant la date de publication. */
    public function test_rien_ne_s_affiche_avant_la_publication(): void
    {
        $veille = date('Y-m-d', (int) strtotime(AvisPortail::tous()[0]['debut'] . ' -1 day'));

        self::assertSame([], AvisPortail::actifs($veille));
        self::assertSame('', Avis::portail($veille));
    }

    /**
     * Ce que l'avis doit dire, dans les mots de la direction : la règle, sa
     * date, le cas de la descente anticipée et celui de l'aéroport.
     */
    public function test_l_avis_dit_la_regle_et_les_deux_cas_particuliers(): void
    {
        $html = Avis::portail(AvisPortail::tous()[0]['debut']);

        self::assertStringContainsString('1er octobre', $html);
        self::assertStringContainsString('ni facturer ni encaisser le lendemain matin', $html);
        self::assertStringContainsString('Points de Caisse', $html);
        self::assertStringContainsString('laissez-la ouverte', $html);
        self::assertStringContainsString('aéroport', $html);
        self::assertStringContainsString('Ce n&#039;est pas une sanction', $html);
    }

    /**
     * Un avis que l'on peut renvoyer d'un clic n'est pas lu : celui-ci n'a pas
     * de croix, et ne dure que quelques jours.
     */
    public function test_l_avis_ne_se_ferme_pas(): void
    {
        $html = Avis::portail(AvisPortail::tous()[0]['debut']);

        self::assertStringNotContainsString('<button', $html);
        self::assertStringNotContainsString('<script', $html);
    }

    /** Il est posé avant les tuiles, là où le regard tombe en entrant. */
    public function test_l_avis_est_en_tete_du_portail(): void
    {
        $vue = (string) file_get_contents(BASE_PATH . '/views/selection_portail/index.php');

        self::assertStringContainsString('Avis::portail()', $vue);
        self::assertLessThan(
            strpos($vue, 'ModuleCatalog::hero'),
            strpos($vue, 'Avis::portail()'),
            "L'avis se lit avant les tuiles."
        );
    }
}
