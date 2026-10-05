<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Finance\RapprochementEnvoisRegles as Regles;
use App\View\Components\RapprochementEnvois;
use Tests\TestCase;

/**
 * Le rapprochement se saisit à même le tableau, comme dans le classeur Excel
 * que la direction tient aujourd'hui.
 *
 * Le comptable dépouille une facture hebdomadaire : il remplit les colis
 * expédiés, le poids final et le montant ligne après ligne, voit l'écart avec
 * ce que les agences ont enregistré, puis enregistre une seule fois. Ouvrir un
 * panneau par ligne lui coûtait un aller-retour pour chaque envoi.
 */
final class RapprochementGrilleTest extends TestCase
{
    /** @return array<string, mixed> */
    private function donnees(bool $peutSaisir = true): array
    {
        $lignes = [
            Regles::composer([
                'id' => 7,
                'numero' => 'ENV-ABJ-2608-0005',
                'date_reference' => '2026-08-17',
                'transporteur' => 'SOTRACOM',
                'numero_document' => '483-20428520',
                'agence_depart' => 'Aéroport Port Bouët Fret',
                'colis_erp' => 230,
                'poids_erp_kg' => 2244.0,
                'poids_brut_kg' => 2858.0,
                'r_devise_compagnie' => 'XOF',
                'r_mode_reglement' => 'CHEQUE',
                'r_numero_cheque' => '0042189',
                'taux_eur_xof' => 655.957,
                'detail_agences' => [
                    ['agence' => 'Agence Abobo Dokui', 'colis' => 142, 'poids' => 1180.0],
                    ['agence' => 'Agence Adjamé Pharmacie Latin', 'colis' => 96, 'poids' => 840.5],
                    ['agence' => 'Aéroport Port Bouët Fret', 'colis' => 72, 'poids' => 573.5],
                ],
            ]),
        ];

        return [
            'lignes' => $lignes,
            'totaux' => Regles::totaux($lignes),
            'compagnies' => [['id' => 3, 'name' => 'SOTRACOM']],
            'agences' => [['id' => 3402, 'name' => 'Aéroport Port Bouët Fret']],
            'filtres' => [
                'du' => '2026-08-01', 'au' => '2026-09-30',
                'transporteur_id' => 0, 'agence_id' => 0, 'reglement' => '', 'q' => '',
                'ecarts_seulement' => false,
            ],
            'peutSaisir' => $peutSaisir,
            'edite_par' => 'Comptable LBP',
        ];
    }

    public function test_les_cases_manuelles_sont_saisissables_dans_le_tableau(): void
    {
        $html = RapprochementEnvois::page($this->donnees());

        foreach (['colis_lta', 'poids_lta_kg', 'montant_compagnie', 'observation', 'numero_document'] as $champ) {
            self::assertStringContainsString(
                'name="lignes[7][' . $champ . ']"',
                $html,
                "La case « {$champ} » ne se saisit pas dans le tableau."
            );
        }
    }

    public function test_chaque_agence_a_sa_ligne_sous_le_total(): void
    {
        /*
         * La direction compare un total a la facture de la compagnie ; mais
         * quand l ecart apparait, la premiere question est « laquelle des
         * trois ? ». Le detail dormait dans un panneau qu il fallait deplier
         * envoi par envoi.
         */
        $html = RapprochementEnvois::page($this->donnees());

        foreach (['Agence Abobo Dokui', 'Pharmacie Latin', 'Port Bou'] as $agence) {
            self::assertStringContainsString($agence, $html, "L'agence « {$agence} » n'apparaît pas sous le total.");
        }

        self::assertStringContainsString('lbp-rappro-detail', $html);
        // 142 + 96 + 72 = 310 : le total reste celui de la ligne principale.
        self::assertStringContainsString('142', $html);
        self::assertStringContainsString('1 180,0', $html);
    }

    public function test_le_detail_ne_se_saisit_pas(): void
    {
        // Les lignes de detail ne doivent pas etre prises pour des lignes a
        // enregistrer, ni compter dans la recherche.
        $html = RapprochementEnvois::page($this->donnees());

        $morceau = substr($html, (int) strpos($html, 'lbp-rappro-detail'), 700);

        self::assertStringNotContainsString('data-rappro-ligne', $morceau);
        self::assertStringNotContainsString('name="lignes[', $morceau);
    }

    public function test_le_tableur_porte_aussi_le_detail(): void
    {
        $xls = RapprochementEnvois::exportExcel($this->donnees());

        self::assertStringContainsString('Détail par agence', $xls);
        self::assertStringContainsString('Agence Abobo Dokui : 142 colis', $xls);
    }

    public function test_les_colonnes_automatiques_ne_se_saisissent_pas(): void
    {
        // Colis et poids enregistrés viennent des agences : les rendre
        // modifiables inviterait à corriger la source au lieu de l'écart.
        $html = RapprochementEnvois::page($this->donnees());

        self::assertStringNotContainsString('name="lignes[7][colis_agence]"', $html);
        self::assertStringNotContainsString('name="lignes[7][poids_agence]"', $html);
        self::assertStringContainsString('lbp-rappro-auto', $html);
    }

    public function test_le_tableau_s_enregistre_d_un_seul_coup(): void
    {
        $html = RapprochementEnvois::page($this->donnees());

        self::assertStringContainsString('rapprochement-envois/enregistrer-lot', $html);
        self::assertStringContainsString('Enregistrer le tableau', $html);
        self::assertStringContainsString('data-rappro-barre', $html);
    }

    public function test_la_colonne_observation_existe(): void
    {
        $html = RapprochementEnvois::page($this->donnees());

        self::assertStringContainsString('>Observation</th>', $html);
    }

    public function test_le_reglement_deja_saisi_n_est_pas_efface_par_la_grille(): void
    {
        /*
         * Le serveur relit toute la ligne à chaque enregistrement. Sans ces
         * champs cachés, enregistrer le tableau remettrait la devise à l'euro
         * et effacerait le chèque, alors que personne n'y a touché.
         */
        $html = RapprochementEnvois::page($this->donnees());

        self::assertStringContainsString('name="lignes[7][devise_compagnie]" value="XOF"', $html);
        self::assertStringContainsString('name="lignes[7][mode_reglement]" value="CHEQUE"', $html);
        self::assertStringContainsString('name="lignes[7][numero_cheque]" value="0042189"', $html);
    }

    public function test_un_profil_en_lecture_ne_voit_aucune_case(): void
    {
        $html = RapprochementEnvois::page($this->donnees(false));

        self::assertStringNotContainsString('name="lignes[7]', $html);
        self::assertStringNotContainsString('enregistrer-lot', $html);
    }

    public function test_la_premiere_saisie_de_la_facture_n_exige_aucun_motif(): void
    {
        /*
         * La règle se comparait à une colonne que la requête ne ramène jamais :
         * le motif de correction était réclamé dès la première saisie, alors
         * qu'il n'y avait rien à corriger.
         */
        $ligne = Regles::composer([
            'id' => 7,
            'date_reference' => '2026-08-17',
            'colis_erp' => 230,
            'poids_erp_kg' => 2244.0,
        ]);

        ['erreurs' => $erreurs] = Regles::lireSaisie(
            ['colis_lta' => '310', 'poids_lta_kg' => '2858'],
            $ligne
        );

        self::assertSame([], $erreurs);
    }

    public function test_revenir_sur_un_chiffre_deja_enregistre_exige_un_motif(): void
    {
        $ligne = Regles::composer([
            'id' => 7,
            'date_reference' => '2026-08-17',
            'colis_erp' => 230,
            'poids_erp_kg' => 2244.0,
            'r_colis_lta' => 310,
            'r_poids_lta_kg' => 2858.0,
        ]);

        ['erreurs' => $erreurs] = Regles::lireSaisie(
            ['colis_lta' => '290', 'poids_lta_kg' => '2858'],
            $ligne
        );

        self::assertNotSame([], $erreurs, 'Corriger en silence efface un écart que la direction doit voir.');
    }
}
