<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * L'état du suivi colisage dit ce qu'il y a dans les colis.
 *
 * Il ne portait que le poids et le nombre. Or deux colis de même poids n'ont
 * rien de commun : la douane, le client et l'agence de destination veulent
 * savoir si ce sont des vêtements ou du poisson fumé.
 */
final class SuiviColisageNatureTest extends TestCase
{
    private function controleur(): string
    {
        return (string) file_get_contents(
            BASE_PATH . '/app/Controllers/Logistique/LogistiqueColisageController.php'
        );
    }

    public function test_la_requete_ramene_la_nature_des_produits(): void
    {
        $source = $this->controleur();

        self::assertStringContainsString('FROM lbp_marchandises m', $source);
        self::assertStringContainsString('AS marchandises', $source);
    }

    public function test_les_marchandises_passent_par_une_sous_requete(): void
    {
        /*
         * Un colis porte plusieurs lignes de marchandise. Une jointure
         * l'aurait compté autant de fois, et le nombre de colis comme le poids
         * total du pied de tableau auraient été multipliés d'autant.
         */
        $source = $this->controleur();

        $extrait = substr($source, (int) strpos($source, 'private function fetchColisData'), 2500);

        self::assertStringContainsString('WHERE m.colis_id = c.id', $extrait);
        self::assertStringNotContainsString('JOIN lbp_marchandises', $extrait);
    }

    public function test_les_deux_etats_portent_la_colonne(): void
    {
        foreach (['colisage_pdf.php', 'colisage_excel.php'] as $vue) {
            $source = (string) file_get_contents(BASE_PATH . '/views/logistique/' . $vue);

            self::assertStringContainsString('Nature des produits', $source, $vue);
            self::assertStringContainsString("\$p['marchandises']", $source, $vue);
        }
    }

    public function test_une_nature_absente_se_dit_en_toutes_lettres(): void
    {
        // « Non précisée » et non un tiret : c'est une saisie manquante, pas
        // une absence de contenu — et c'est à l'agence de la compléter.
        $pdf = (string) file_get_contents(BASE_PATH . '/views/logistique/colisage_pdf.php');

        self::assertStringContainsString('Non précisée', $pdf);
    }

    public function test_le_pied_de_tableau_reste_aligne(): void
    {
        /*
         * Douze colonnes désormais : le libellé du total en couvre six, puis
         * viennent le nombre et le poids, puis les quatre dernières. Une
         * colonne ajoutée sans toucher aux colspan décale tout le pied.
         */
        $pdf = (string) file_get_contents(BASE_PATH . '/views/logistique/colisage_pdf.php');

        // Les fermetures, et non les ouvertures : « <thead> » commence lui
        // aussi par « <th » et fausserait le compte.
        self::assertSame(12, substr_count($pdf, '</th>'), 'Douze colonnes attendues.');
        self::assertStringContainsString('<td colspan="6" class="text-right">TOTAL GENERAL :</td>', $pdf);
        self::assertStringContainsString('<td colspan="12" class="text-center"', $pdf);
    }
}
