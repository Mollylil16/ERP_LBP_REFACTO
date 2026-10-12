<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * L'appro a sa ligne sur le point de caisse.
 *
 * Il était fondu dans le solde théorique : l'agence voyait son attendu
 * augmenter sans savoir pourquoi, et devait ouvrir Finance / Appro Caisse
 * pour comprendre. C'est exactement le genre d'écart inexpliqué qui a fait
 * accuser des agences à tort en septembre.
 */
final class PointCaisseApproVisibleTest extends TestCase
{
    private function source(): string
    {
        return (string) file_get_contents(BASE_PATH . '/app/View/Components/Finance.php');
    }

    public function test_l_appro_du_jour_est_lu(): void
    {
        $source = $this->source();

        self::assertStringContainsString("\$activeReport['approCaisseXof']", $source);
        self::assertStringContainsString("\$activeReport['appro_caisse_xof']", $source);
    }

    public function test_l_appro_a_sa_ligne_parmi_les_canaux(): void
    {
        self::assertStringContainsString('Appro de caisse (recu du siege)', $this->source());
    }

    public function test_le_calcul_de_l_attendu_est_ecrit_en_toutes_lettres(): void
    {
        /*
         * Donner le résultat sans l'opération laissait la caissière deviner
         * d'où venait la différence entre ce qu'elle compte et ce que le
         * logiciel attend.
         */
        $source = $this->source();

        self::assertStringContainsString('Attendu dans le tiroir ce jour', $source);
        self::assertStringContainsString("d\'appro reçu du siège", $source);
        self::assertStringContainsString("Finance / Appro Caisse", $source);
    }

    public function test_rien_ne_s_affiche_sans_appro(): void
    {
        // Une ligne « Appro : 0 XOF » sous chaque point de caisse serait du
        // bruit permanent : la plupart des journées n'en ont aucun.
        $source = $this->source();

        self::assertStringContainsString('$approCaisse > 0', $source);
    }
}
