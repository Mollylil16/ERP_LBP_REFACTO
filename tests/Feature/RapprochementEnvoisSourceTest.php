<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Finance\RapprochementEnvoisRegles as Regles;
use App\View\Components\RapprochementEnvois;
use Tests\TestCase;

/**
 * D'où viennent les lignes du rapprochement.
 *
 * L'écran est resté vide en production, et ses deux filtres avec lui : il
 * listait les dossiers d'envoi, alors que les agences groupent leurs colis par
 * « Groupage & Expéditions », qui crée une expédition sans ouvrir de dossier.
 * Le comptable a cru l'écran cassé.
 *
 * Depuis le 30/09/2026, la ligne rapprochée est le départ lui-même. Les colis
 * et le poids de l'agence se remplissent seuls — comptage figé du dossier, ou
 * somme des colis rattachés au départ ; la compagnie, le numéro de LTA et les
 * chiffres du document restent à la main du comptable, qui ne les connaît qu'à
 * réception de la facture.
 */
final class RapprochementEnvoisSourceTest extends TestCase
{
    private function sql(): string
    {
        return (string) file_get_contents(BASE_PATH . '/app/Repositories/Finance/RapprochementEnvoisRepository.php');
    }

    /**
     * Une ligne est un envoi : une date, une compagnie, une LTA — le grain du
     * tableur que la direction tenait à la main. Partir du départ d'une seule
     * agence ne montrait jamais la somme que la compagnie facture.
     */
    public function test_les_lignes_sont_des_envois(): void
    {
        $sql = $this->sql();

        self::assertStringContainsString('FROM lbp_rappro_envois e', $sql);
        self::assertStringContainsString('e.date_envoi AS date_reference', $sql);
        self::assertStringContainsString('lbp_rappro_envois_agences', $sql);
    }

    /**
     * Les colis et le poids se somment sur les agences cochées, par leur date
     * de départ prévue : c'est le suivi de colisage que chacune remet la veille
     * ou le matin même, et il est déjà dans le logiciel.
     */
    public function test_les_chiffres_des_agences_se_somment_seuls(): void
    {
        $sql = $this->sql();

        self::assertStringContainsString('FROM lbp_colis c', $sql);
        self::assertStringContainsString('COALESCE(c.date_depart_prevue, DATE(c.created_at)) = ?', $sql);
        // Un envoi de plusieurs colis compte pour autant, jamais pour un seul.
        self::assertStringContainsString('SUM(COALESCE(NULLIF(c.nombre_colis, 0), 1)) AS colis', $sql);
        // Deux compagnies peuvent partir le même jour : seules les agences
        // cochées entrent dans la somme.
        self::assertStringContainsString('AND c.agence_depart_id IN ({$marques})', $sql);
    }

    /** Un envoi sans facture encore reçue tient debout : il est en attente. */
    public function test_un_envoi_sans_facture_compose_une_ligne_complete(): void
    {
        $ligne = Regles::composer([
            'id' => 42,
            'dossier_id' => null,
            'numero' => 'DEP-20260930-AB12',
            'date_reference' => '2026-09-30',
            'transporteur' => null,
            'transporteur_id' => null,
            'numero_document' => null,
            'agence_depart' => 'Abobo Dokui',
            'colis_erp' => 11,
            'poids_erp_kg' => 268.5,
            'nb_colis_declare' => null,
            'poids_brut_kg' => null,
        ]);

        self::assertSame(42, $ligne['id'], "La ligne s'identifie par son envoi.");
        self::assertSame(11, $ligne['colis_agence']);
        self::assertSame(268.5, $ligne['poids_agence']);
        self::assertNull($ligne['colis_lta'], "Le document n'est pas encore arrivé.");
        self::assertSame('EN_ATTENTE_LTA', $ligne['etat']);
    }

    /**
     * À réception de la facture, le comptable inscrit la compagnie et le numéro
     * de LTA que le départ groupé ne portait pas.
     */
    public function test_la_compagnie_et_le_numero_de_lta_se_saisissent(): void
    {
        ['valeurs' => $valeurs, 'erreurs' => $erreurs] = Regles::lireSaisie([
            'transporteur_id' => '3',
            'numero_document' => '483-20428520',
            'colis_lta' => '11',
            'poids_lta_kg' => '275',
        ], ['colis_declare' => null, 'poids_declare' => null, 'motif_correction' => null]);

        self::assertSame(3, $valeurs['transporteur_id']);
        self::assertSame('483-20428520', $valeurs['numero_lta']);
        self::assertSame(11, $valeurs['colis_factures']);
        self::assertSame(275.0, $valeurs['poids_facture_kg']);
        self::assertNotContains('', $erreurs);
    }

    /** Une compagnie non choisie ne s'enregistre pas comme la compagnie n° 0. */
    public function test_une_compagnie_non_choisie_reste_vide(): void
    {
        ['valeurs' => $valeurs] = Regles::lireSaisie(
            ['transporteur_id' => '', 'numero_document' => '  '],
            ['colis_declare' => null, 'poids_declare' => null]
        );

        self::assertNull($valeurs['transporteur_id']);
        self::assertNull($valeurs['numero_lta']);
    }

    /** Le formulaire du comptable porte les deux champs qu'il doit remplir. */
    public function test_le_panneau_de_saisie_offre_la_compagnie_et_la_lta(): void
    {
        $ligne = Regles::composer([
            'id' => 42,
            'dossier_id' => null,
            'numero' => 'DEP-20260930-AB12',
            'date_reference' => '2026-09-30',
            'colis_erp' => 11,
            'poids_erp_kg' => 268.5,
        ]);

        $html = RapprochementEnvois::page([
            'lignes' => [$ligne],
            'totaux' => Regles::totaux([$ligne]),
            'compagnies' => [['id' => 3, 'name' => 'SOTRACOM']],
            'agences' => [['id' => 3402, 'name' => 'Abobo Dokui']],
            'filtres' => [
                'du' => '2026-09-01', 'au' => '2026-09-30',
                'transporteur_id' => 0, 'agence_id' => 0,
                'reglement' => '', 'q' => '', 'ecarts_seulement' => false,
            ],
            'peutSaisir' => true,
        ]);

        self::assertStringContainsString('name="transporteur_id"', $html);
        self::assertStringContainsString('name="numero_document"', $html);
        self::assertStringContainsString('finance/rapprochement-envois/42/enregistrer', $html);
        // La saisie des agences s'affiche, elle ne se saisit pas.
        self::assertStringNotContainsString('name="colis_agence"', $html);
    }
}
