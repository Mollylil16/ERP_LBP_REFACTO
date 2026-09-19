<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Finance\RapprochementEnvoisRegles as Regles;
use Tests\TestCase;

/**
 * Les règles du rapprochement des envois.
 *
 * Les cas sont ceux du fichier Excel que la direction tenait à la main, repris
 * tels quels : le départ SOTRACOM du 17/08/2026 sans colis sur la LTA, celui
 * d'AIR FRET du 20/08 avec 945 kg d'écart, et les départs AIR CI de septembre
 * dont la LTA n'était pas encore arrivée.
 */
final class RapprochementEnvoisReglesTest extends TestCase
{
    /** @param array<string, mixed> $valeurs */
    private function depart(array $valeurs = []): array
    {
        return array_merge([
            'id' => 1,
            'numero' => 'ENV-ABJ-2608-0001',
            'date_reference' => '2026-08-17',
            'transporteur' => 'SOTRACOM',
            'numero_document' => '483-20428520',
            'agence_depart' => 'Aéroport Port Bouët Fret',
            'agence_arrivee' => 'Paris',
            'colis_erp' => 230,
            'poids_erp_kg' => 2244.0,
            'nb_colis_declare' => null,
            'poids_brut_kg' => 2858.0,
            'taux_eur_xof' => 655.957,
        ], $valeurs);
    }

    /**
     * Le cas qui a motivé l'écran. Dans le tableur, une cellule vide moins 230
     * affichait « −230 colis » : un écart qui n'existe pas.
     */
    public function test_une_lta_incomplete_n_est_pas_un_ecart(): void
    {
        $ligne = Regles::composer($this->depart());

        self::assertSame('LTA_A_COMPLETER', $ligne['etat']);
        self::assertNull($ligne['ecart_colis'], "Un colis non renseigné sur la LTA ne produit aucun écart.");
        self::assertSame(614.0, $ligne['ecart_poids']['valeur'], 'Le poids, lui, est bien renseigné : son écart est calculé.');
    }

    public function test_un_depart_sans_aucun_document_reste_en_attente(): void
    {
        $ligne = Regles::composer($this->depart([
            'date_reference' => '2026-09-04',
            'transporteur' => 'AIR CI',
            'numero_document' => null,
            'colis_erp' => 315,
            'poids_erp_kg' => 4454.0,
            'nb_colis_declare' => null,
            'poids_brut_kg' => null,
        ]));

        self::assertSame('EN_ATTENTE_LTA', $ligne['etat']);
        self::assertNull($ligne['ecart_colis']);
        self::assertNull($ligne['ecart_poids']);
    }

    public function test_un_ecart_au_dela_du_seuil_doit_etre_justifie(): void
    {
        $ligne = Regles::composer($this->depart([
            'transporteur' => 'AIR FRET',
            'colis_erp' => 315,
            'poids_erp_kg' => 2561.0,
            'nb_colis_declare' => 350,
            'poids_brut_kg' => 3506.0,
        ]));

        self::assertSame('ECART_A_JUSTIFIER', $ligne['etat']);
        self::assertSame(945.0, $ligne['ecart_poids']['valeur']);
        self::assertSame(36.9, $ligne['ecart_poids']['pourcent']);
        self::assertTrue($ligne['ecart_poids']['depasse']);
    }

    public function test_une_observation_vaut_justification(): void
    {
        $ligne = Regles::composer($this->depart([
            'nb_colis_declare' => 350,
            'colis_erp' => 315,
            'poids_erp_kg' => 2561.0,
            'poids_brut_kg' => 3506.0,
            'r_observation' => 'Poids relevé par la compagnie, 35 colis ajoutés au dernier moment.',
        ]));

        self::assertSame('ECART_JUSTIFIE', $ligne['etat']);
    }

    public function test_un_depart_conforme_est_rapproche(): void
    {
        $ligne = Regles::composer($this->depart([
            'colis_erp' => 320,
            'poids_erp_kg' => 3501.0,
            'nb_colis_declare' => 320,
            'poids_brut_kg' => 3501.0,
        ]));

        self::assertSame('RAPPROCHE', $ligne['etat']);
        self::assertSame(0.0, $ligne['ecart_colis']['valeur']);
        self::assertFalse($ligne['ecart_poids']['depasse']);
    }

    /**
     * La valeur du comptable prime sur celle lue par l'agent export, mais la
     * saisie des agences reste intacte : c'est elle que la direction compare.
     */
    public function test_la_correction_du_comptable_ne_remplace_pas_la_saisie_des_agences(): void
    {
        $ligne = Regles::composer($this->depart([
            'nb_colis_declare' => 240,
            'r_colis_lta' => 260,
            'r_motif_correction' => 'LTA reçue par mail le 18/08.',
        ]));

        self::assertSame(260, $ligne['colis_lta']);
        self::assertSame(240, $ligne['colis_declare'], "La lecture de l'agent export reste lisible.");
        self::assertSame(230, $ligne['colis_agence'], 'La saisie des agences reste intacte.');
        self::assertTrue($ligne['colis_corrige']);
    }

    public function test_le_montant_en_euros_est_converti_au_taux_fige(): void
    {
        $ligne = Regles::composer($this->depart([
            'r_montant_compagnie' => 510.85,
            'r_devise_compagnie' => 'EUR',
            'r_taux_eur_xof' => 655.957,
        ]));

        // Le chiffre du fichier de la direction : 510,85 € = 335 096 FCFA arrondis.
        self::assertSame(335095.63, $ligne['montant_xof']);
        self::assertSame(335095.63, $ligne['reste_a_regler'], "Tant qu'il n'est pas réglé, tout reste dû.");
    }

    public function test_un_montant_regle_ne_reste_plus_a_regler(): void
    {
        $ligne = Regles::composer($this->depart([
            'r_montant_compagnie' => 417090.0,
            'r_devise_compagnie' => 'XOF',
            'r_mode_reglement' => 'CHEQUE',
            'r_numero_cheque' => '4412',
            'r_date_reglement' => '2026-09-02',
        ]));

        self::assertTrue($ligne['regle']);
        self::assertSame(0.0, $ligne['reste_a_regler']);
    }

    // ------------------------------------------------------------------
    // Cumuls
    // ------------------------------------------------------------------

    /**
     * Le cumul du tableur donnait −1 914 colis et −12 640 kg parce qu'il
     * comptait les départs sans document. Sur les mêmes données, l'écran en
     * retient les seuls départs documentés.
     */
    public function test_les_totaux_ignorent_les_departs_sans_document(): void
    {
        $lignes = [
            Regles::composer($this->depart(['colis_erp' => 320, 'poids_erp_kg' => 3501.0, 'nb_colis_declare' => 320, 'poids_brut_kg' => 3501.0])),
            Regles::composer($this->depart(['colis_erp' => 315, 'poids_erp_kg' => 2561.0, 'nb_colis_declare' => 350, 'poids_brut_kg' => 3506.0])),
            Regles::composer($this->depart(['colis_erp' => 315, 'poids_erp_kg' => 4454.0, 'nb_colis_declare' => null, 'poids_brut_kg' => null])),
            Regles::composer($this->depart(['colis_erp' => 415, 'poids_erp_kg' => 2934.0, 'nb_colis_declare' => null, 'poids_brut_kg' => null])),
        ];

        $totaux = Regles::totaux($lignes);

        self::assertSame(4, $totaux['envois']);
        self::assertSame(2, $totaux['documentes']);
        self::assertSame(2, $totaux['en_attente']);
        self::assertSame(35, $totaux['ecart_colis'], 'Seul le départ AIR FRET porte un écart de colis.');
        self::assertSame(945.0, $totaux['ecart_poids']);
        self::assertSame(1, $totaux['a_justifier']);
    }

    // ------------------------------------------------------------------
    // Saisie du comptable
    // ------------------------------------------------------------------

    public function test_corriger_sans_motif_est_refuse(): void
    {
        $ligne = Regles::composer($this->depart(['nb_colis_declare' => 240]));

        ['erreurs' => $erreurs] = Regles::lireSaisie(['colis_lta' => '260'], $ligne);

        self::assertNotSame([], $erreurs);
        self::assertStringContainsString('motif', implode(' ', $erreurs));
    }

    public function test_saisir_la_meme_valeur_que_le_document_ne_demande_pas_de_motif(): void
    {
        $ligne = Regles::composer($this->depart(['nb_colis_declare' => 240]));

        ['erreurs' => $erreurs] = Regles::lireSaisie(['colis_lta' => '240'], $ligne);

        self::assertSame([], $erreurs);
    }

    public function test_un_reglement_date_exige_son_moyen_et_le_numero_du_cheque(): void
    {
        $ligne = Regles::composer($this->depart(['r_montant_compagnie' => 500.0]));

        ['erreurs' => $sansMoyen] = Regles::lireSaisie(['date_reglement' => '2026-09-02', 'montant_compagnie' => '500'], $ligne);
        self::assertStringContainsString('moyen', implode(' ', $sansMoyen));

        ['erreurs' => $sansNumero] = Regles::lireSaisie(
            ['date_reglement' => '2026-09-02', 'mode_reglement' => 'CHEQUE', 'montant_compagnie' => '500'],
            $ligne
        );
        self::assertStringContainsString('numéro', implode(' ', $sansNumero));
    }

    public function test_un_reglement_sans_montant_facture_est_refuse(): void
    {
        $ligne = Regles::composer($this->depart());

        ['erreurs' => $erreurs] = Regles::lireSaisie(
            ['date_reglement' => '2026-09-02', 'mode_reglement' => 'VIREMENT'],
            $ligne
        );

        self::assertStringContainsString('montant', implode(' ', $erreurs));
    }

    /** Le comptable tape « 2 858 » ou « 2858,5 » : les deux doivent passer. */
    public function test_les_nombres_saisis_acceptent_espaces_et_virgule(): void
    {
        $ligne = Regles::composer($this->depart(['nb_colis_declare' => 260, 'poids_brut_kg' => 2858.5]));

        ['valeurs' => $valeurs, 'erreurs' => $erreurs] = Regles::lireSaisie(
            ['colis_lta' => '260', 'poids_lta_kg' => '2 858,5'],
            $ligne
        );

        self::assertSame([], $erreurs);
        self::assertSame(260, $valeurs['colis_lta']);
        self::assertSame(2858.5, $valeurs['poids_lta_kg']);
    }

    public function test_une_case_laissee_vide_reste_inconnue_et_ne_vaut_pas_zero(): void
    {
        $ligne = Regles::composer($this->depart());

        ['valeurs' => $valeurs] = Regles::lireSaisie(['colis_lta' => '', 'montant_compagnie' => ''], $ligne);

        self::assertNull($valeurs['colis_lta']);
        self::assertNull($valeurs['montant_compagnie']);
    }
}
