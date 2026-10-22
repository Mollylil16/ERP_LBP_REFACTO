<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Noel\NoelRapport;
use Tests\TestCase;

/**
 * Le rapport du soir, pour le directeur et pour l'administrateur.
 *
 * Une liste de chiffres se lit une fois puis s'ignore. Ce qu'un directeur veut
 * savoir, ce n'est pas « Adjamé a facturé 1,2 million » mais « Adjamé a facturé
 * 40 % de moins qu'un mardi ordinaire ». Même donnée, autre valeur.
 */
final class NoelRapportTest extends TestCase
{
    private function source(): string
    {
        return (string) file_get_contents(BASE_PATH . '/app/Services/Noel/NoelRapport.php');
    }

    public function test_le_rapport_ne_parle_que_du_travail(): void
    {
        /*
         * La cloison posée le 22/10/2026 avec l'administrateur. Jamais un mot
         * de conversation, jamais rien de ce que quelqu'un aura confié à NOEL.
         * Sans elle, le personnel cesserait de lui parler en deux semaines.
         */
        $source = $this->source();

        // Ce qu'on interdit, c'est la requête — pas le mot, qui figure
        // légitimement dans le commentaire qui pose la règle.
        foreach (['msg_messages', 'msg_conversations', 'msg_membres'] as $table) {
            self::assertStringNotContainsString($table, $source, 'Le rapport lit ' . $table . '.');
        }

        self::assertStringNotContainsString('NoelVocabulaire', $source);
        self::assertStringNotContainsString('NoelEnigme', $source);
    }

    public function test_chaque_chiffre_est_compare_a_son_ordinaire(): void
    {
        $source = $this->source();

        self::assertSame(4, NoelRapport::SEMAINES_DE_REFERENCE);
        self::assertStringContainsString("' -' . \$semaine . ' week'", $source);
        self::assertStringContainsString("'ordinaire' =>", $source);
        self::assertStringContainsString("'ecart' =>", $source);
    }

    public function test_on_compare_un_mardi_a_des_mardis(): void
    {
        /*
         * Le samedi d'une entreprise de fret ne ressemble à aucun autre jour.
         * Le comparer à la moyenne de la semaine rendrait tous les écarts
         * faux. Reculer de semaine en semaine garde le même jour.
         */
        $source = $this->source();

        self::assertStringContainsString('week', $source);
        self::assertStringNotContainsString("' day'", $source);
    }

    public function test_un_ecart_depuis_zero_ne_veut_rien_dire(): void
    {
        /*
         * Un jour sans aucune facture suivi d'un jour à trois ne vaut pas
         * « +300 % ». Mieux vaut ne rien annoncer qu'annoncer un chiffre faux.
         */
        self::assertNull(NoelRapport::ecart(3.0, 0.0));
        self::assertNull(NoelRapport::ecart(0.0, 0.0));

        self::assertSame(-40.0, NoelRapport::ecart(60.0, 100.0));
        self::assertSame(25.0, NoelRapport::ecart(125.0, 100.0));
    }

    public function test_un_petit_ecart_ne_merite_pas_d_etre_signale(): void
    {
        // Un rapport qui commente chaque variation de 3 % noie ce qui compte.
        self::assertSame(20.0, NoelRapport::ECART_NOTABLE);
        self::assertStringContainsString(
            'abs($ecart) < self::ECART_NOTABLE',
            $this->source()
        );
    }

    public function test_les_encaissements_viennent_des_paiements_pas_des_factures(): void
    {
        /*
         * Le 15/10/2026, une facture corrigée à la main affichait 100 000 F
         * alors que ses deux lignes de paiement en disaient 200 000. C'est la
         * somme des paiements qui fait foi : c'est elle qui remplit le tiroir.
         */
        $source = $this->source();

        self::assertStringContainsString('FROM lbp_paiements', $source);
        self::assertStringContainsString('SUM(montant)', $source);
    }

    public function test_la_ventilation_evite_le_piege_des_deux_colonnes(): void
    {
        /*
         * Seule `mode` est réellement écrite ; `mode_paiement` vaut toujours
         * 'ESPECES' et donnerait une ventilation entièrement fausse. On passe
         * donc par MODE_SQL, qui lit la bonne.
         */
        $source = $this->source();

        self::assertStringContainsString('EtatJournalierRepository::MODE_SQL', $source);
        self::assertStringNotContainsString("p.mode_paiement =", $source);
    }

    public function test_le_point_de_caisse_garde_la_meme_definition_partout(): void
    {
        // « soumis » ou « consolidé » valent clôture, comme dans
        // CronAlertesDirection et NoelVeille. Trois définitions du même fait
        // finiraient par se contredire.
        self::assertStringContainsString(
            "['soumis', 'consolide']",
            $this->source()
        );
    }

    public function test_noel_parle_aussi_quand_tout_va_bien(): void
    {
        /*
         * Un gardien qui n'ouvre la bouche que pour gronder finit par être
         * fermé. Demande de l'administrateur le 22/10/2026.
         */
        self::assertStringContainsString(
            'Toutes les agences ont soumis leur point de caisse.',
            $this->source()
        );
    }

    public function test_les_nombres_se_lisent_a_la_francaise(): void
    {
        self::assertSame('408 300', NoelRapport::nombre(408300.0));
        self::assertSame('134,50', NoelRapport::nombre(134.5));
        self::assertSame('0', NoelRapport::nombre(0.0));
    }

    public function test_le_jour_se_dit_en_toutes_lettres(): void
    {
        // « 40 % de moins qu'un 2026-10-15 » ne se lit pas.
        self::assertSame('jeudi', NoelRapport::nomDuJour('2026-10-15'));
        self::assertSame('lundi', NoelRapport::nomDuJour('2026-10-26'));
    }

    public function test_aucune_intelligence_artificielle_dans_le_rapport(): void
    {
        $source = $this->source();

        self::assertStringNotContainsString('cle_api', $source);
        self::assertStringNotContainsString('api.anthropic', $source);
    }
}
