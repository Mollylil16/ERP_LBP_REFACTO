<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Finance\RapprochementEnvoisRegles as Regles;
use App\View\Components\RapprochementEnvois;
use Tests\TestCase;

/**
 * Rendu de l'écran de rapprochement et de ses deux exports.
 *
 * Ce qui compte : les douze colonnes de la maquette validée, le panneau de
 * saisie réservé à qui peut corriger, et des exports qui reprennent exactement
 * les lignes affichées — c'est avec eux que la direction confronte les vrais
 * documents.
 */
final class RapprochementEnvoisRenduTest extends TestCase
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
                'agence_arrivee' => 'Paris',
                'colis_erp' => 230,
                'poids_erp_kg' => 2244.0,
                'nb_colis_declare' => null,
                'poids_brut_kg' => 2858.0,
                'taux_eur_xof' => 655.957,
            ]),
            Regles::composer([
                'id' => 8,
                'numero' => 'ENV-ABJ-2609-0001',
                'date_reference' => '2026-09-04',
                'transporteur' => 'AIR CI',
                'numero_document' => null,
                'agence_depart' => 'Abobo Dokui',
                'agence_arrivee' => 'Paris',
                'colis_erp' => 315,
                'poids_erp_kg' => 4454.0,
                'nb_colis_declare' => null,
                'poids_brut_kg' => null,
            ]),
        ];

        return [
            'lignes' => $lignes,
            'totaux' => Regles::totaux($lignes),
            'compagnies' => [['id' => 3, 'name' => 'SOTRACOM'], ['id' => 4, 'name' => 'AIR CI']],
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

    public function test_l_ecran_presente_les_douze_colonnes_de_la_maquette(): void
    {
        $html = RapprochementEnvois::page($this->donnees());

        foreach (['Date', 'Compagnie', 'N° envoi / LTA', 'Colis agence', 'Colis LTA', 'Écart',
            'Poids agence', 'Poids LTA', 'Écart poids', 'Montant compagnie', 'Règlement', 'État'] as $colonne) {
            self::assertStringContainsString('>' . $colonne . '</th>', $html, "Colonne « {$colonne} » absente.");
        }

        self::assertStringContainsString('finea-shell', $html, 'Même présentation que les autres écrans Finance.');
    }

    public function test_une_lta_manquante_s_affiche_a_saisir_et_non_comme_un_ecart(): void
    {
        $html = RapprochementEnvois::page($this->donnees());

        self::assertStringContainsString('à saisir', $html);
        self::assertStringContainsString('LTA à compléter', $html);
        self::assertStringContainsString('En attente de LTA', $html);
        self::assertStringNotContainsString('-230', $html, "Le tableur affichait ici un écart de −230 colis qui n'existe pas.");
    }

    public function test_l_ecart_au_dela_du_seuil_est_signale(): void
    {
        $html = RapprochementEnvois::page($this->donnees());

        self::assertStringContainsString('+614', $html);
        self::assertStringContainsString('finea-badge--danger', $html);
        self::assertStringContainsString('is-alerte', $html);
    }

    public function test_le_panneau_de_saisie_n_apparait_que_pour_qui_peut_corriger(): void
    {
        $avec = RapprochementEnvois::page($this->donnees(true));

        self::assertStringContainsString('rapprochement-envois/7/enregistrer', $avec);
        self::assertStringContainsString('name="colis_lta"', $avec);
        self::assertStringContainsString('name="montant_compagnie"', $avec);
        self::assertStringContainsString('name="numero_cheque"', $avec);
        self::assertStringContainsString('name="_csrf_token"', $avec);
        self::assertStringContainsString('name="motif_correction"', $avec);

        $sans = RapprochementEnvois::page($this->donnees(false));

        self::assertStringNotContainsString('rapprochement-envois/7/enregistrer', $sans, "L'assistante DG lit sans corriger.");
        self::assertStringNotContainsString('name="colis_lta"', $sans);
        self::assertStringContainsString('Saisie des agences', $sans, 'Elle voit tout de même les deux chiffres comparés.');
    }

    public function test_le_panneau_rappelle_la_saisie_d_origine_des_agences(): void
    {
        $html = RapprochementEnvois::page($this->donnees());

        self::assertStringContainsString('Saisie des agences', $html);
        self::assertStringContainsString("Lu sur le document par l'agent export", $html);
        self::assertStringContainsString("La saisie des agences n'est jamais écrasée", $html);
    }

    public function test_les_filtres_de_la_maquette_sont_presents(): void
    {
        $html = RapprochementEnvois::page($this->donnees());

        foreach (['name="du"', 'name="au"', 'name="transporteur_id"', 'name="agence_id"',
            'name="reglement"', 'name="q"', 'name="ecarts_seulement"'] as $champ) {
            self::assertStringContainsString($champ, $html, "Filtre {$champ} absent.");
        }
    }

    public function test_le_pdf_reprend_les_lignes_et_les_totaux(): void
    {
        $pdf = RapprochementEnvois::exportPdf($this->donnees());

        self::assertStringContainsString('Rapprochement des envois', $pdf);
        self::assertStringContainsString('483-20428520', $pdf);
        self::assertStringContainsString('Reste à régler', $pdf);
        self::assertStringContainsString('@page{size:A4 landscape', $pdf);
        self::assertStringContainsString('window.print()', $pdf);
    }

    public function test_l_excel_porte_les_nombres_en_valeur_pour_que_les_sommes_fonctionnent(): void
    {
        $excel = RapprochementEnvois::exportExcel($this->donnees());

        self::assertStringContainsString('x:num="614.0"', $excel);
        self::assertStringContainsString('urn:schemas-microsoft-com:office:excel', $excel);
        self::assertStringContainsString('Motif de la correction', $excel);
        self::assertStringContainsString('Rapproché par', $excel);
    }

    public function test_une_periode_sans_envoi_le_dit_sans_tableau_vide(): void
    {
        $donnees = $this->donnees();
        $donnees['lignes'] = [];
        $donnees['totaux'] = Regles::totaux([]);

        $html = RapprochementEnvois::page($donnees);

        self::assertStringContainsString('Aucun envoi sur cette période', $html);
        self::assertStringNotContainsString('<tbody></tbody>', $html);
    }
}
