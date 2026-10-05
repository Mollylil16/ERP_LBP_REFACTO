<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\View\Components\FournituresHistorique as Journal;
use Tests\TestCase;

/**
 * Le journal des décisions prises sur les demandes de fournitures.
 *
 * Les fournitures n'ont pas de table d'historique : chaque décision s'inscrit
 * en colonne sur la demande elle-même. Le journal se reconstitue donc à partir
 * de ces quatre moments — demandée, approuvée (ou refusée), confirmée, livrée —
 * plutôt que d'ajouter une table et de perdre tout le passé.
 */
final class FournituresHistoriqueTest extends TestCase
{
    /** @return array<int, array<string, mixed>> */
    private function lignes(): array
    {
        $commun = [
            'demande_id' => 12,
            'objet' => 'RAMETTES A4 ET CARTOUCHES',
            'quantite' => 5,
            'montant' => 47500,
            'agence_nom' => 'Agence Adjamé Pharmacie Latin',
            'demandeur_nom' => 'KOUAKOU SALES',
            'statut_actuel' => 'CONFIRMEE',
        ];

        return [
            $commun + ['action' => 'CONFIRMATION', 'quand' => '2026-10-04 15:30:00', 'par' => 'Mme KOFFI', 'motif' => ''],
            $commun + ['action' => 'APPROBATION', 'quand' => '2026-10-04 10:12:00', 'par' => 'Linda DJAMBITCHÉ', 'motif' => ''],
            $commun + ['action' => 'DEMANDE', 'quand' => '2026-10-03 08:00:00', 'par' => 'KOUAKOU SALES', 'motif' => ''],
            [
                'demande_id' => 11,
                'objet' => 'DEUX FAUTEUILS DE BUREAU',
                'quantite' => 2,
                'montant' => 180000,
                'agence_nom' => 'Agence Abobo Dokui',
                'demandeur_nom' => 'KOUAKOU SALES',
                'statut_actuel' => 'REJETEE',
                'action' => 'REJET',
                'quand' => '2026-10-02 09:45:00',
                'par' => 'Linda DJAMBITCHÉ',
                'motif' => 'Hors budget du trimestre : à représenter en janvier.',
            ],
            [
                'demande_id' => 10,
                'objet' => 'SCOTCH ET ÉTIQUETTES',
                'quantite' => 20,
                'montant' => 30000,
                'agence_nom' => 'Aéroport Port Bouët Fret',
                'demandeur_nom' => 'KOUAKOU SALES',
                'statut_actuel' => 'LIVREE',
                'action' => 'LIVRAISON',
                'quand' => '2026-10-01 16:00:00',
                'par' => '',
                'motif' => '',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function filtres(): array
    {
        return ['du' => '2026-10-01', 'au' => '2026-10-05', 'action' => '', 'agence' => '', 'par' => '', 'q' => ''];
    }

    public function test_les_deux_signatures_sont_nommees(): void
    {
        $html = Journal::page($this->lignes(), $this->filtres(), [], []);

        self::assertStringContainsString('Linda DJAMBITCH', $html);
        self::assertStringContainsString('Mme KOFFI', $html);
        self::assertStringContainsString('Approuvée', $html);
        self::assertStringContainsString('Confirmée', $html);
        self::assertStringContainsString('04/10/2026 à 15:30', $html);
    }

    public function test_le_motif_du_refus_est_lisible(): void
    {
        $html = Journal::page($this->lignes(), $this->filtres(), [], []);

        self::assertStringContainsString('Refusée', $html);
        self::assertStringContainsString('Hors budget du trimestre', $html);
    }

    public function test_la_livraison_dit_qu_elle_vient_de_l_agence(): void
    {
        // La colonne n'enregistre pas qui a cliqué : l'écrire vaut mieux qu'un
        // tiret qui laisserait croire à une donnée perdue.
        $html = Journal::page($this->lignes(), $this->filtres(), [], []);

        self::assertStringContainsString('agence, à réception', $html);
    }

    public function test_les_deux_exports_sont_proposes_et_filtres(): void
    {
        $html = Journal::page($this->lignes(), $this->filtres(), [], []);

        self::assertStringContainsString('fournitures/historique/pdf', $html);
        self::assertStringContainsString('fournitures/historique/excel', $html);
        self::assertStringContainsString('du=2026-10-01', $html);
    }

    public function test_le_pdf_reprend_les_memes_lignes(): void
    {
        $pdf = Journal::exportPdf($this->lignes(), $this->filtres(), 'Direction Générale');

        self::assertStringContainsString('Hors budget du trimestre', $pdf);
        self::assertStringContainsString('Linda DJAMBITCH', $pdf);
        self::assertStringContainsString('Direction Générale', $pdf);
        self::assertStringContainsString('window.print()', $pdf);
    }

    public function test_l_excel_garde_le_numero_et_le_statut(): void
    {
        $xls = Journal::exportExcel($this->lignes(), $this->filtres());

        self::assertStringContainsString('N° de demande', $xls);
        self::assertStringContainsString('Statut actuel', $xls);
        self::assertStringContainsString('REJETEE', $xls);
    }

    public function test_un_refus_sans_motif_est_signale(): void
    {
        $lignes = $this->lignes();
        $lignes[3]['motif'] = '';

        $html = Journal::page($lignes, $this->filtres(), [], []);

        self::assertStringContainsString('Motif non enregistré', $html);
    }

    public function test_un_journal_vide_explique_pourquoi(): void
    {
        $html = Journal::page([], $this->filtres(), [], []);

        self::assertStringContainsString('Aucune décision sur cette période', $html);
    }

    public function test_la_tuile_apparait_dans_les_ressources_internes(): void
    {
        $navigation = (string) file_get_contents(BASE_PATH . '/app/Services/Shared/ModuleDashboardService.php');

        self::assertStringContainsString("'key' => 'exploitation_fournitures_historique'", $navigation);
    }

    public function test_le_journal_est_reserve_a_ceux_qui_tranchent(): void
    {
        // Une agence voit déjà ses propres demandes sur l'écran des
        // fournitures ; ce journal recoupe tout le réseau.
        $source = (string) file_get_contents(BASE_PATH . '/app/Controllers/Colisage/ExploitationController.php');

        $extrait = substr($source, (int) strpos($source, 'public function fournituresHistorique'), 900);

        self::assertStringContainsString('$this->voitToutesLesDemandes()', $extrait);
    }
}
