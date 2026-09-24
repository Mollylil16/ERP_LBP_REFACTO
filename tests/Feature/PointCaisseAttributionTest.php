<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * À qui appartient la vente, à qui appartient l'argent.
 *
 * Signalé par toutes les agences, mesuré le 24/09/2026 sur la base réelle :
 * Abobo Dokui comptait 69 250 FCFA de plus que le logiciel, Adjamé voyait
 * 70 000 FCFA d'encaissements qu'elle n'avait jamais faits. L'argent était
 * bien là, rangé dans la mauvaise colonne.
 *
 * Deux causes, et deux règles pour y répondre :
 *
 * 1. L'agence d'une facture était celle de l'« agence de départ » du colis,
 *    choisie librement dans une liste. Elle est désormais celle de l'agent qui
 *    établit la facture : la vente appartient à qui l'a faite.
 *
 * 2. Le point de caisse rattachait l'encaissement à l'agence de la facture.
 *    Il suit désormais l'agence où le billet a été pris : un tiroir ne peut se
 *    comparer qu'à ce qu'il contient.
 */
final class PointCaisseAttributionTest extends TestCase
{
    private function fichier(string $chemin): string
    {
        return (string) file_get_contents(BASE_PATH . '/' . $chemin);
    }

    /**
     * La vente suit l'agent qui facture, et l'agence de départ du colis n'est
     * plus qu'une information de logistique.
     */
    public function test_la_facture_prend_l_agence_de_l_agent_qui_l_etablit(): void
    {
        $depot = $this->fichier('app/Repositories/Finance/FactureRepository.php');

        $position = strpos($depot, 'function resoudreAgenceDeFacturation(');
        self::assertNotFalse($position, 'La règle doit vivre à un seul endroit.');

        $methode = substr($depot, $position, 600);

        // L'agence de l'agent est consultée, et le repli sur l'agence du colis
        // n'intervient qu'après : c'est l'ordre qui porte la règle.
        self::assertStringContainsString('Auth::agenceId()', $methode);
        self::assertLessThan(
            strpos($methode, 'return $this->resolveValidAgencyId($agenceDepartColis)'),
            strpos($methode, 'Auth::agenceId()'),
            "L'agence de l'agent doit primer sur l'agence de départ du colis."
        );

        // Les deux chemins de création de facture passent par cette règle.
        self::assertStringContainsString('resoudreAgenceDeFacturation(', $depot);
        self::assertStringContainsString(
            'resoudreAgenceDeFacturation(',
            $this->fichier('app/Controllers/Finance/FinanceController.php')
        );

        // L'ancienne règle ne doit subsister nulle part.
        foreach (['app/Repositories/Finance/FactureRepository.php', 'app/Controllers/Finance/FinanceController.php'] as $chemin) {
            self::assertStringNotContainsString(
                "\$candidateAgenceId = !empty(\$colis['agence_depart_id'])",
                $this->fichier($chemin),
                "L'agence de départ du colis ne décide plus de la facture : {$chemin}"
            );
        }
    }

    /**
     * L'agence est figée sur le paiement au moment où l'argent est pris. La
     * déduire plus tard du compte de la caissière ferait bouger les journées
     * déjà closes au moindre changement d'affectation.
     */
    public function test_l_encaissement_retient_l_agence_ou_il_a_ete_pris(): void
    {
        $depot = $this->fichier('app/Repositories/Finance/PaiementRepository.php');

        self::assertStringContainsString('INSERT INTO lbp_paiements (facture_id, caissiere_id, agence_id', $depot);
        self::assertStringContainsString('Auth::agenceId()', $depot);
        // Un compte sans agence — direction, administrateur — ne doit pas
        // produire un encaissement orphelin : 15 300 FCFA flottaient ainsi.
        self::assertStringContainsString('SELECT agence_id FROM lbp_factures', $depot);

        $modele = $this->fichier('app/Models/Finance/Paiement.php');
        self::assertStringContainsString('$agenceId', $modele);

        $migration = $this->fichier('app/Database/MigrationRunner.php');
        self::assertStringContainsString("addColumnIfMissing('lbp_paiements', 'agence_id'", $migration);
    }

    /**
     * Le point de caisse compare un tiroir : il ne peut compter que l'argent
     * qui y est entré.
     */
    public function test_le_point_de_caisse_compte_l_argent_la_ou_il_a_ete_pris(): void
    {
        $depot = $this->fichier('app/Repositories/Finance/EtatJournalierRepository.php');

        // Toute requête filtrant des encaissements par agence doit suivre le
        // tiroir. Le COALESCE couvre les encaissements antérieurs, avant que
        // l'agence ne soit enregistrée sur le paiement.
        self::assertSame(
            0,
            substr_count($depot, 'WHERE f.agence_id = :agence_id AND DATE(p.date_paiement)'),
            "Un encaissement ne se compte plus dans l'agence de la facture."
        );

        self::assertGreaterThanOrEqual(
            3,
            substr_count($depot, 'COALESCE(p.agence_id, f.agence_id) = :agence_id'),
            'Les totaux, la ventilation et le détail du jour suivent tous le tiroir.'
        );
    }

    /**
     * La vente, elle, ne bouge pas : elle reste à l'agence de la facture.
     * Confondre les deux ferait disparaître le chiffre d'affaires de l'agence
     * qui a vendu.
     */
    public function test_la_vente_reste_rattachee_a_l_agence_de_la_facture(): void
    {
        $depot = $this->fichier('app/Repositories/Finance/EtatJournalierRepository.php');

        self::assertGreaterThanOrEqual(
            3,
            substr_count($depot, 'WHERE f.agence_id = :agence_id AND DATE(f.date_emission)'),
            'Les factures émises restent comptées dans leur agence.'
        );
    }
}
