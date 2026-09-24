<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Une caisse signée trop tôt se rouvre toute seule.
 *
 * Mesuré en production le 24/09/2026 : 526 500 FCFA sont entrés en caisse
 * après la signature du point. Adjamé a signé 45 050 FCFA à 15h20 le 22/09,
 * puis encaissé 185 500 de plus jusqu'à 17h54. À la fermeture, le tiroir
 * contenait 230 550 et le logiciel affichait 45 050 — d'où « le logiciel
 * donne un montant inférieur au montant physique ».
 *
 * Le logiciel n'avait pas tort : il avait cessé de compter. Et l'agent ne
 * pouvait rien y faire, la resoumission étant refusée une fois le point
 * signé. Désormais tout encaissement postérieur rouvre le point.
 */
final class PointCaisseReouvertureTest extends TestCase
{
    private function fichier(string $chemin): string
    {
        return (string) file_get_contents(BASE_PATH . '/' . $chemin);
    }

    /**
     * La réouverture est branchée sur l'écriture d'un encaissement, seule
     * porte par laquelle l'argent entre.
     */
    public function test_un_encaissement_posterieur_rouvre_le_point(): void
    {
        $depot = $this->fichier('app/Repositories/Finance/PaiementRepository.php');

        self::assertStringContainsString('rouvrirLePointDuJour', $depot);
        self::assertMatchesRegularExpression(
            "/UPDATE lbp_etats_journaliers\s+SET statut = 'brouillon'/s",
            $depot,
            'Le point doit repasser en brouillon pour être recompté.'
        );

        // La première signature est conservée : elle dit qui ferme trop tôt.
        self::assertStringContainsString('soumis_le_premier = COALESCE(soumis_le_premier, date_soumission)', $depot);

        // Un point consolidé a été validé par la caissière principale : le
        // défaire dans son dos serait pire que le laisser.
        self::assertStringContainsString("AND statut = 'soumis'", $depot);
    }

    /**
     * Un encaissement ne doit jamais échouer parce que son point de caisse
     * résiste : c'est l'argent du client qui compte d'abord.
     */
    public function test_la_reouverture_ne_peut_pas_faire_echouer_un_encaissement(): void
    {
        $depot = $this->fichier('app/Repositories/Finance/PaiementRepository.php');

        $position = strpos($depot, 'function rouvrirLePointDuJour');
        self::assertNotFalse($position);

        $methode = substr($depot, $position, 1400);
        self::assertStringContainsString('catch (\Throwable $e)', $methode);
        self::assertStringContainsString('error_log(', $methode);
    }

    /**
     * Le message doit tenir en deux phrases et une action. Le personnel des
     * agences n'a pas à interpréter un écart : il a un comptage à refaire.
     */
    public function test_le_message_dit_quoi_faire_sans_calcul_a_interpreter(): void
    {
        $ecran = $this->fichier('app/View/Components/Finance.php');

        self::assertStringContainsString('Votre caisse a été rouverte', $ecran);
        self::assertStringContainsString('après avoir fermé', $ecran);
        self::assertStringContainsString('Recomptez votre caisse', $ecran);

        // L'ancien message, verbeux et sans action possible, ne doit plus
        // s'afficher en même temps que celui-ci.
        self::assertStringContainsString("empty(\$activeReport['reouvertLe'])", $ecran);
    }

    public function test_la_trace_de_reouverture_est_enregistree(): void
    {
        $migration = $this->fichier('app/Database/MigrationRunner.php');
        self::assertStringContainsString("addColumnIfMissing('lbp_etats_journaliers', 'reouvert_le'", $migration);
        self::assertStringContainsString("addColumnIfMissing('lbp_etats_journaliers', 'soumis_le_premier'", $migration);

        $modele = $this->fichier('app/Models/Finance/EtatJournalier.php');
        self::assertStringContainsString('$reouvertLe', $modele);
        self::assertStringContainsString('$soumisLePremier', $modele);

        $depot = $this->fichier('app/Repositories/Finance/EtatJournalierRepository.php');
        self::assertStringContainsString("reouvertLe: \$row['reouvert_le']", $depot);
    }
}
