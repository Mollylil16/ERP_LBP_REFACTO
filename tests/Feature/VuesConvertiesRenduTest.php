<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Finance\DemandeFonds;
use App\View\Components\ColisageGuide;
use App\View\Components\FinanceFonds;
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
