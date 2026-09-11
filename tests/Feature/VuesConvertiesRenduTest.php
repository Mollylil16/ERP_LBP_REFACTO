<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Finance\DemandeFonds;
use App\View\Components\ColisageGuide;
use App\View\Components\FinanceFonds;
use App\View\Components\FinanceFondsFile;
use Tests\TestCase;

/**
 * Rendu des vues converties en composants.
 *
 * Déplacer du balisage d'une vue vers un composant est mécanique, donc facile à
 * faire à moitié : une clé oubliée ne se voit qu'à l'affichage. Chaque écran
 * converti entre ici, rendu à vide puis avec des données.
 *
 * Aucune session n'est ouverte : Auth::user() renvoie alors null sans toucher la
 * base, et les branches réservées aux rôles ne s'exécutent pas.
 */
final class VuesConvertiesRenduTest extends TestCase
{
    // ------------------------------------------------------------------
    // Guide de saisie
    // ------------------------------------------------------------------

    public function test_le_guide_de_saisie_se_rend_avec_ses_cinq_volets(): void
    {
        $html = ColisageGuide::page();

        self::assertStringContainsString('finea-shell', $html);
        self::assertSame(5, substr_count($html, 'data-finea-tab="'), 'Les cinq volets du guide doivent être présents.');
        self::assertSame(5, substr_count($html, 'finea-inline-pane'));
    }

    public function test_le_premier_volet_du_guide_est_visible_sans_javascript(): void
    {
        // Si le script ne s'exécute pas, la page doit rester lisible plutôt que
        // de s'afficher entièrement vide.
        $html = ColisageGuide::page();

        self::assertMatchesRegularExpression(
            '/<section role="tabpanel" id="guide-saisie"[^>]*class="finea-inline-pane is-active"[^>]*>/',
            $html
        );
    }

    public function test_le_guide_n_utilise_aucun_caractere_pictographique(): void
    {
        $html = ColisageGuide::page();

        self::assertSame(
            0,
            preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{231A}-\x{231B}\x{23E9}-\x{23FA}\x{FE0F}]/u', $html),
            'Le guide doit tracer ses icônes en SVG.'
        );
    }

    // ------------------------------------------------------------------
    // Demandes de fonds
    // ------------------------------------------------------------------

    public function test_la_liste_des_demandes_se_rend_sans_aucune_demande(): void
    {
        $html = FinanceFonds::listePage([], 0, 1, 0, $this->filtres(), [], []);

        self::assertStringContainsString('finea-shell', $html);
        self::assertStringContainsString('Aucune demande de fonds', $html);
        self::assertStringNotContainsString('finea-pagination', $html, 'Une seule page ne se pagine pas.');
    }

    public function test_la_liste_affiche_le_statut_et_le_montant_de_chaque_demande(): void
    {
        $html = FinanceFonds::listePage(
            [$this->demande('DF-2026-001', 'en_attente', 250000.0)],
            1,
            1,
            1,
            $this->filtres(),
            ['total_demandes' => 1, 'montant_total_demande' => 250000],
            [['id' => 1, 'name' => 'Agence Abidjan', 'code' => 'ABJ']]
        );

        self::assertStringContainsString('DF-2026-001', $html);
        self::assertStringContainsString('En attente de validation', $html);
        self::assertStringContainsString('250 000 XOF', $html);
        self::assertStringContainsString('Agence Abidjan (ABJ)', $html);
    }

    public function test_le_bon_de_caisse_n_est_propose_qu_apres_validation(): void
    {
        $enAttente = FinanceFonds::listePage(
            [$this->demande('DF-1', 'en_attente', 1000.0)],
            1, 1, 1, $this->filtres(), [], []
        );
        $validee = FinanceFonds::listePage(
            [$this->demande('DF-2', 'validee', 1000.0)],
            1, 1, 1, $this->filtres(), [], []
        );

        self::assertStringNotContainsString('bon-caisse-pdf', $enAttente);
        self::assertStringContainsString('bon-caisse-pdf', $validee);
    }

    public function test_la_pagination_apparait_des_la_deuxieme_page(): void
    {
        $html = FinanceFonds::listePage(
            [$this->demande('DF-1', 'imputee', 1000.0)],
            40, 2, 3, $this->filtres(), [], []
        );

        self::assertStringContainsString('finea-pagination', $html);
        self::assertStringContainsString('aria-current="page"', $html);
    }

    public function test_la_numerotation_des_lignes_suit_la_page_affichee(): void
    {
        // Page 2 avec 15 lignes par page : la première ligne porte le numéro 16.
        $html = FinanceFonds::listePage(
            [$this->demande('DF-16', 'imputee', 1000.0)],
            40, 2, 3, $this->filtres(), [], []
        );

        self::assertStringContainsString('<td style="text-align:center;">16</td>', $html);
    }

    public function test_le_motif_d_une_demande_est_echappe(): void
    {
        $html = FinanceFonds::listePage(
            [$this->demande('DF-1', 'en_attente', 1000.0, '<script>alert(1)</script>')],
            1, 1, 1, $this->filtres(), [], []
        );

        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    // ------------------------------------------------------------------
    // Files d'attente et creation
    // ------------------------------------------------------------------

    public function test_les_files_d_attente_se_rendent_a_vide(): void
    {
        $caisse = FinanceFondsFile::priseEnComptePage([], 0, 1, $this->filtres(), []);
        $imputation = FinanceFondsFile::imputationPage([], 0, 1, $this->filtres(), [], 'decaissee');

        self::assertStringContainsString('Rien à décaisser', $caisse);
        self::assertStringContainsString('Rien à justifier', $imputation);
    }

    public function test_la_file_de_caisse_propose_le_geste_de_decaissement(): void
    {
        $html = FinanceFondsFile::priseEnComptePage(
            [$this->demande('DF-7', 'validee', 120000.0)],
            1,
            1,
            $this->filtres(),
            [['id' => 1, 'name' => 'Agence Abidjan', 'code' => 'ABJ']]
        );

        self::assertStringContainsString('Décaisser', $html);
        self::assertStringContainsString('120 000 XOF', $html);
        self::assertStringContainsString('Demandée le', $html);
    }

    public function test_l_onglet_courant_de_l_imputation_survit_au_filtrage(): void
    {
        // Sans ce champ cache, filtrer depuis l'onglet « cloturees » renverrait
        // l'utilisateur sur la file « a justifier ».
        $html = FinanceFondsFile::imputationPage([], 0, 1, $this->filtres(), [], 'imputee');

        self::assertStringContainsString('name="statut"', $html);
        self::assertStringContainsString('value="imputee"', $html);
        self::assertStringContainsString('Dossiers clôturés', $html);
    }

    public function test_le_formulaire_de_creation_porte_un_jeton_et_ses_champs(): void
    {
        $html = FinanceFondsFile::creationPage(
            [['id' => 3, 'name' => 'Agence Abidjan', 'code' => 'ABJ']],
            ['S-IM00379/26', 'S-IM00380/26'],
            'DF-2026-014',
            3
        );

        self::assertStringContainsString('_csrf_token', $html);
        self::assertStringContainsString('DF-2026-014', $html);
        self::assertStringContainsString('name="montant"', $html);
        self::assertStringContainsString('name="motif"', $html);
        self::assertStringContainsString('S-IM00379/26', $html, 'Les dossiers récents alimentent la saisie assistée.');
    }

    public function test_l_agence_de_l_utilisateur_est_preselectionnee(): void
    {
        $html = FinanceFondsFile::creationPage(
            [
                ['id' => 1, 'name' => 'Agence Abidjan', 'code' => 'ABJ'],
                ['id' => 3, 'name' => 'Agence Adjamé', 'code' => 'ADJ'],
            ],
            [],
            'DF-1',
            3
        );

        self::assertMatchesRegularExpression(
            '/<option value="3"[^>]*\bselected\b/',
            $html,
            'L\'agent doit retrouver son agence sans la rechercher.'
        );
    }

    public function test_le_champ_dossier_se_desactive_hors_traitement_de_dossier(): void
    {
        // Un champ obligatoire mais masque bloque la soumission sans rien dire :
        // le script doit retirer « required » en meme temps qu'il masque.
        $html = FinanceFondsFile::creationPage([], [], 'DF-1', 1);

        self::assertStringContainsString('removeAttribute("required")', $html);
        self::assertStringContainsString('data-fonds-cadre', $html);
    }

    // ------------------------------------------------------------------
    // Fiche d'une demande de fonds
    // ------------------------------------------------------------------

    /**
     * Les cinq statuts possibles doivent tous produire une page lisible : c'est
     * là que se cachent les oublis, un bloc conditionnel n'étant visible que
     * dans un état sur cinq.
     */
    public function test_la_fiche_se_rend_dans_les_cinq_statuts(): void
    {
        foreach (['en_attente', 'validee', 'decaissee', 'imputee', 'rejetee'] as $statut) {
            $html = FinanceFonds::fichePage($this->demande('DF-1', $statut, 250000.0), [], true, true, true);

            self::assertStringContainsString('finea-shell', $html, "Statut {$statut}.");
            self::assertStringContainsString('DF-1', $html, "Statut {$statut}.");
        }
    }

    public function test_chaque_bloc_d_action_n_apparait_que_dans_son_statut(): void
    {
        $attendus = [
            'en_attente' => 'Décision de la direction',
            'validee' => 'Prise en compte caisse',
            'decaissee' => 'Imputation comptable',
        ];

        foreach ($attendus as $statut => $titre) {
            foreach (array_keys($attendus) as $autre) {
                $html = FinanceFonds::fichePage($this->demande('DF-1', $autre, 1000.0), [], true, true, true);

                if ($statut === $autre) {
                    self::assertStringContainsString($titre, $html, "« {$titre} » manque au statut {$statut}.");
                } else {
                    self::assertStringNotContainsString($titre, $html, "« {$titre} » ne doit pas s'afficher au statut {$autre}.");
                }
            }
        }
    }

    public function test_les_blocs_d_action_disparaissent_sans_habilitation(): void
    {
        // Sans droit, le formulaire ne doit pas être rendu du tout : le masquer
        // en CSS laisserait la route POST atteignable depuis la page.
        $html = FinanceFonds::fichePage($this->demande('DF-1', 'en_attente', 1000.0), [], false, false, false);

        self::assertStringNotContainsString('Décision de la direction', $html);
        self::assertStringNotContainsString('/valider', $html);
        self::assertStringNotContainsString('/rejeter', $html);
    }

    public function test_les_formulaires_de_la_fiche_portent_un_jeton_csrf(): void
    {
        foreach (['en_attente', 'validee', 'decaissee'] as $statut) {
            $html = FinanceFonds::fichePage($this->demande('DF-1', $statut, 1000.0), [], true, true, true);

            $formulaires = substr_count($html, '<form method="post"');
            $jetons = substr_count($html, '_csrf_token');

            self::assertGreaterThan(0, $formulaires, "Statut {$statut}.");
            self::assertGreaterThanOrEqual(
                $formulaires,
                $jetons,
                "Statut {$statut} : un formulaire POST sans jeton CSRF serait refusé par le contrôleur."
            );
        }
    }

    public function test_le_journal_de_tracabilite_affiche_les_evenements(): void
    {
        $html = FinanceFonds::fichePage(
            $this->demande('DF-1', 'validee', 1000.0),
            [
                ['action' => 'CREATION', 'user_nom' => 'M. Diarra', 'commentaire' => 'Demande déposée', 'created_at' => '2026-09-01 09:00:00'],
                ['action' => 'VALIDATION', 'user_nom' => 'DG', 'commentaire' => 'Accord', 'created_at' => '2026-09-02 11:30:00'],
            ],
            true,
            true,
            true
        );

        self::assertStringContainsString('CREATION', $html);
        self::assertStringContainsString('M. Diarra', $html);
        self::assertStringContainsString('02/09/2026 à 11:30', $html);
    }

    public function test_le_journal_vide_s_explique(): void
    {
        $html = FinanceFonds::fichePage($this->demande('DF-1', 'en_attente', 1000.0), [], true, true, true);

        self::assertStringContainsString('Aucun historique', $html);
    }

    public function test_le_reliquat_ne_transporte_pas_le_montant_dans_le_javascript(): void
    {
        // Le montant engagé passe par un attribut de données, pas par une
        // interpolation dans un gestionnaire inline.
        $html = FinanceFonds::fichePage($this->demande('DF-1', 'decaissee', 750000.0), [], true, true, true);

        self::assertStringContainsString('data-fonds-engage="750000"', $html);
        self::assertStringNotContainsString('calculerReliquat(', $html);
    }

    /**
     * @return array<string, mixed>
     */
    private function filtres(): array
    {
        return [
            'agence_id' => null,
            'statut' => '',
            'cadre' => '',
            'date_from' => '',
            'date_to' => '',
            'q' => '',
        ];
    }

    private function demande(string $numero, string $statut, float $montant, string $motif = 'Frais de dédouanement'): DemandeFonds
    {
        return new DemandeFonds(
            id: 1,
            numeroDemande: $numero,
            agenceId: 1,
            cadre: 'traitement_dossier',
            dossierNum: 'DOS-2026-01',
            motif: $motif,
            montant: $montant,
            devise: 'XOF',
            demandeurId: 7,
            statut: $statut,
            createdAt: '2026-09-01 10:00:00',
            agenceNom: 'Agence Abidjan',
            demandeurNom: 'M. Diarra',
        );
    }
}
