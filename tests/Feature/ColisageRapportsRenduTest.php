<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\View\Components\ColisageRapports;
use Tests\TestCase;

/**
 * Rendu des rapports d'activité par agence.
 */
final class ColisageRapportsRenduTest extends TestCase
{
    public function test_le_rapport_journalier_se_rend_sans_activite(): void
    {
        $html = ColisageRapports::journalierPage('2026-09-11', null, [], [], [], [], false);

        self::assertStringContainsString('finea-shell', $html);
        self::assertStringContainsString('Aucune activité', $html);
        self::assertStringContainsString('Journée du 11/09/2026', $html);
    }

    /**
     * date('F') renvoie toujours l'anglais. Le rapport mensuel affichait donc
     * « September 2026 » au milieu d'une interface française.
     */
    public function test_le_mois_s_affiche_en_francais(): void
    {
        $html = ColisageRapports::mensuelPage('2026-09', null, [], []);

        self::assertStringContainsString('Septembre 2026', $html);
        self::assertStringNotContainsString('September', $html);
    }

    public function test_les_douze_mois_sont_traduits(): void
    {
        $attendus = [
            '01' => 'Janvier', '02' => 'Février', '03' => 'Mars', '04' => 'Avril',
            '05' => 'Mai', '06' => 'Juin', '07' => 'Juillet', '08' => 'Août',
            '09' => 'Septembre', '10' => 'Octobre', '11' => 'Novembre', '12' => 'Décembre',
        ];

        foreach ($attendus as $numero => $nom) {
            $html = ColisageRapports::mensuelPage('2026-' . $numero, null, [], []);

            self::assertStringContainsString($nom . ' 2026', $html, "Mois {$numero}.");
        }
    }

    public function test_les_deux_devises_restent_separees(): void
    {
        // Additionner XOF et EUR dans un seul indicateur donnerait un chiffre
        // faux : les deux gardent leur propre carte.
        $html = ColisageRapports::journalierPage(
            '2026-09-11',
            null,
            [],
            [],
            [],
            [
                'nb_colis' => 42,
                'poids_total' => 315.5,
                'ca_xof' => 1250000.0,
                'ca_eur' => 480.0,
                'nb_hors_delai' => 3,
                'credits_non_regle_xof' => 75000.0,
                'credits_regle_xof_jour' => 25000.0,
            ],
            false
        );

        self::assertStringContainsString('1 250 000 XOF', $html);
        self::assertStringContainsString('480,00 EUR', $html);
        self::assertStringContainsString('315,5 kg', $html);
    }

    public function test_la_ligne_de_totaux_accompagne_le_detail(): void
    {
        $html = ColisageRapports::journalierPage(
            '2026-09-11',
            null,
            [],
            [[
                'agence_id' => 1,
                'agence_name' => 'Agence Abidjan',
                'nb_colis' => 12,
                'nb_retires' => 5,
                'poids_total' => 88.0,
                'ca_xof' => 300000.0,
                'ca_eur' => 0.0,
                'nb_hors_delai' => 0,
            ]],
            [],
            [
                'nb_colis' => 12,
                'poids_total' => 88.0,
                'ca_xof' => 300000.0,
                'ca_eur' => 0.0,
                'nb_hors_delai' => 0,
                'credits_non_regle_xof' => 0.0,
                'credits_regle_xof_jour' => 0.0,
            ],
            false
        );

        self::assertStringContainsString('Agence Abidjan', $html);
        self::assertStringContainsString('TOTAUX', $html);
    }

    public function test_l_export_excel_est_reserve(): void
    {
        $sans = ColisageRapports::journalierPage('2026-09-11', null, [], [], [], [], false);
        $avec = ColisageRapports::journalierPage('2026-09-11', null, [], [], [], [], true);

        self::assertStringNotContainsString('export-csv', $sans);
        self::assertStringContainsString('export-csv', $avec);
        self::assertStringContainsString('export-pdf', $sans, 'Le PDF reste ouvert à tous.');
    }

    public function test_la_navigation_ne_propose_pas_de_journee_a_venir(): void
    {
        $aujourdhui = date('Y-m-d');
        $demain = date('Y-m-d', strtotime('+1 day'));
        $hier = date('Y-m-d', strtotime('-1 day'));

        $html = ColisageRapports::journalierPage($aujourdhui, null, [], [], [], [], false);

        self::assertStringContainsString('date=' . $hier, $html);
        self::assertStringNotContainsString('date=' . $demain, $html, 'Le rapport d\'une journée à venir serait vide.');
    }

    public function test_la_navigation_propose_le_lendemain_sur_une_date_passee(): void
    {
        $html = ColisageRapports::journalierPage('2026-01-15', null, [], [], [], [], false);

        self::assertStringContainsString('date=2026-01-16', $html);
        self::assertStringContainsString('date=2026-01-14', $html);
    }

    public function test_le_jour_n_est_ecrit_qu_une_fois_par_groupe(): void
    {
        $html = ColisageRapports::mensuelPage(
            '2026-09',
            null,
            [],
            [
                ['jour' => '2026-09-10', 'agence_name' => 'Abidjan', 'nb_colis' => 4, 'poids' => 20.0, 'ca_xof' => 100.0, 'ca_eur' => 0.0],
                ['jour' => '2026-09-10', 'agence_name' => 'Adjamé', 'nb_colis' => 2, 'poids' => 10.0, 'ca_xof' => 50.0, 'ca_eur' => 0.0],
                ['jour' => '2026-09-09', 'agence_name' => 'Abidjan', 'nb_colis' => 1, 'poids' => 5.0, 'ca_xof' => 25.0, 'ca_eur' => 0.0],
            ]
        );

        self::assertSame(1, substr_count($html, '10/09/2026'));
        self::assertSame(1, substr_count($html, '09/09/2026'));
        // Le libellé est échappé à l'affichage : on cherche la forme rendue.
        self::assertStringContainsString('2 journée(s)', $html);
    }

    public function test_le_filtre_d_agence_conserve_la_selection(): void
    {
        $html = ColisageRapports::journalierPage(
            '2026-09-11',
            3,
            [
                ['id' => 1, 'name' => 'Abidjan'],
                ['id' => 3, 'name' => 'Adjamé'],
            ],
            [],
            [],
            [],
            false
        );

        self::assertMatchesRegularExpression('/<option value="3"[^>]*\bselected\b/', $html);
    }
}
