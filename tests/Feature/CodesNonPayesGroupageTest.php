<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * L'écran « Codes Non Payés » du responsable groupage général.
 *
 * Il existe depuis le 23/09/2026 et lui est accessible, mais il tenait encore
 * son relevé dans un classeur Excel. Deux raisons, trouvées le 10/10/2026 en
 * interrogeant la production :
 *
 * - Son classeur range les codes sous A1, A2 et A3, ses trois agences. Le
 *   logiciel sait affecter ce groupe colis par colis, mais sur 802 colis
 *   AUCUN n'en portait : tout retombait dans un seul tas « Sans Groupe ».
 * - L'écran ne bornait personne à son agence. Un chef d'agence y lisait la
 *   dette client de tout le réseau — 16 317 286 F sur quatre agences — et
 *   l'export la lui donnait en fichier, téléphones des clients compris.
 */
final class CodesNonPayesGroupageTest extends TestCase
{
    private function source(): string
    {
        return (string) file_get_contents(
            BASE_PATH . '/app/Controllers/Logistique/GroupageCodesNonPayesController.php'
        );
    }

    public function test_le_repli_de_groupe_est_l_agence(): void
    {
        /*
         * Sans ce repli, le relevé n'a qu'un seul groupe et ne ressemble en
         * rien au classeur. L'agence donne exactement son découpage A1/A2/A3
         * sans qu'il ait quoi que ce soit à saisir.
         */
        $source = $this->source();

        // Une seule ligne, et non le bloc entier : le fichier est en retours
        // Windows, un motif multi-lignes n'y correspondrait jamais.
        self::assertStringContainsString(
            ": (string) \$r['agence_nom'];",
            $source,
            "Le groupe non renseigné doit retomber sur l'agence."
        );
        self::assertStringContainsString("\$groupeDisplay = !empty(\$r['groupe_code'])", $source);

        // La référence d'expédition éclatait le relevé en autant de groupes
        // que de départs : elle ne doit plus servir de repli.
        self::assertStringNotContainsString("'Sans Groupe'", $source);
    }

    public function test_un_role_d_agence_est_borne_a_la_sienne(): void
    {
        $source = $this->source();

        self::assertStringContainsString('private function agenceDemandee(): string', $source);
        self::assertStringContainsString('Auth::ROLES_PORTEE_AGENCE', $source);
        self::assertStringContainsString('Auth::ROLES_PORTEE_RESEAU', $source);
    }

    public function test_le_verrou_vaut_aussi_pour_les_deux_exports(): void
    {
        /*
         * Le vrai risque n'est pas l'écran, c'est le fichier : un export qui
         * échappe au contrôle donne la dette de tout le réseau, nominative.
         * Les trois entrées doivent passer par la même porte.
         */
        $source = $this->source();

        self::assertSame(
            3,
            substr_count($source, '$selectedAgence = $this->agenceDemandee();'),
            'L\'écran, le PDF et le tableur doivent tous les trois passer par le verrou.'
        );
        self::assertStringNotContainsString(
            "\$selectedAgence = trim((string) (\$_GET['agence_id'] ?? 'all'));",
            $source,
            "Plus aucune lecture directe de l'agence demandée ne doit subsister."
        );
    }

    public function test_le_responsable_groupage_garde_les_trois_agences(): void
    {
        /*
         * Sa portée est nommée dans la liste de référence, elle ne doit rien
         * à l'absence d'agence de rattachement. C'est ce qui le distingue d'un
         * chef d'agence, et le verrou ne doit pas l'attraper.
         */
        $auth = (string) file_get_contents(BASE_PATH . '/app/Helpers/Auth.php');

        self::assertStringContainsString("'responsable_groupage',", $auth);

        $reseau = substr($auth, (int) strpos($auth, 'ROLES_PORTEE_RESEAU'), 400);
        self::assertStringContainsString('responsable_groupage', $reseau);

        $agence = substr($auth, (int) strpos($auth, 'ROLES_PORTEE_AGENCE'), 400);
        self::assertStringNotContainsString('responsable_groupage', $agence);
    }
}
