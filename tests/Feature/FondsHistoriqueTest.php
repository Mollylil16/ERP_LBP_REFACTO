<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\View\Components\FinanceFondsHistorique as Historique;
use Tests\TestCase;

/**
 * Le journal des décisions prises sur les demandes de fonds.
 *
 * Chaque validation, chaque rejet, chaque décaissement laissait déjà sa trace
 * en base. Mais elle ne se lisait que demande par demande : répondre à « qui a
 * rejeté cette demande, et pourquoi ? » obligeait à rouvrir les fiches une à
 * une. Cet écran la donne d'un bloc, et la sort en PDF et en tableur — ce sont
 * ces deux documents que la direction oppose à une contestation.
 */
final class FondsHistoriqueTest extends TestCase
{
    /** @return array<int, array<string, mixed>> */
    private function lignes(): array
    {
        return [
            [
                'id' => 1,
                'demande_fonds_id' => 31,
                'action' => 'REJET',
                'statut_avant' => 'en_attente',
                'statut_apres' => 'rejetee',
                'commentaire' => 'Montant non justifié : la facture du fournisseur manque au dossier.',
                'created_at' => '2026-10-04 16:42:11',
                'auteur_nom' => 'Mme KOFFI',
                'auteur_email' => 'compta@labelleporte.ci',
                'numero_demande' => 'DF-2026-0031',
                'motif' => 'ACHAT DE SCOTCH DU 02/10/2026',
                'montant' => 30000,
                'devise' => 'XOF',
                'cadre' => 'fonctionnement',
                'statut_actuel' => 'rejetee',
                'agence_nom' => 'Agence Abobo Dokui',
                'demandeur_nom' => 'KOUAKOU SALES',
            ],
            [
                'id' => 2,
                'demande_fonds_id' => 30,
                'action' => 'VALIDATION',
                'statut_avant' => 'en_attente',
                'statut_apres' => 'validee',
                'commentaire' => null,
                'created_at' => '2026-10-04 09:17:48',
                'auteur_nom' => 'ADJEMORI Roxane',
                'auteur_email' => 'roxane.a@labelleporte.ci',
                'numero_demande' => 'DF-2026-0030',
                'motif' => "RECHARGEMENT INTERNET DE L'AGENCE AEROPORT",
                'montant' => 25100,
                'devise' => 'XOF',
                'cadre' => 'fonctionnement',
                'statut_actuel' => 'decaissee',
                'agence_nom' => 'Aéroport Port Bouët Fret',
                'demandeur_nom' => 'KOUAKOU SALES',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function filtres(): array
    {
        return ['du' => '2026-10-01', 'au' => '2026-10-05', 'action' => '', 'agence_id' => 0, 'user_id' => 0, 'q' => ''];
    }

    public function test_l_ecran_dit_qui_quand_et_pourquoi(): void
    {
        $html = Historique::page($this->lignes(), $this->filtres(), [], []);

        // Qui
        self::assertStringContainsString('Mme KOFFI', $html);
        self::assertStringContainsString('ADJEMORI Roxane', $html);
        // Quand, à l'heure près : une décision sans heure ne tranche pas entre
        // deux décisions du même jour.
        self::assertStringContainsString('04/10/2026 à 16:42', $html);
        // Pourquoi
        self::assertStringContainsString('la facture du fournisseur manque', $html);
        // Quoi
        self::assertStringContainsString('Rejetée', $html);
        self::assertStringContainsString('Validée', $html);
        self::assertStringContainsString('DF-2026-0031', $html);
    }

    public function test_les_deux_exports_sont_proposes(): void
    {
        $html = Historique::page($this->lignes(), $this->filtres(), [], []);

        self::assertStringContainsString('finance/fonds/historique/pdf', $html);
        self::assertStringContainsString('finance/fonds/historique/excel', $html);
    }

    public function test_les_exports_gardent_le_filtre_de_l_ecran(): void
    {
        // Un PDF qui ne dirait pas la même chose que l'écran d'où on l'a tiré
        // ne vaudrait rien devant une contestation.
        $html = Historique::page($this->lignes(), $this->filtres(), [], []);

        self::assertStringContainsString('du=2026-10-01', $html);
        self::assertStringContainsString('au=2026-10-05', $html);
    }

    public function test_le_pdf_reprend_les_memes_lignes(): void
    {
        $pdf = Historique::exportPdf($this->lignes(), $this->filtres(), 'Direction Générale');

        self::assertStringContainsString('DF-2026-0031', $pdf);
        self::assertStringContainsString('Mme KOFFI', $pdf);
        self::assertStringContainsString('la facture du fournisseur manque', $pdf);
        self::assertStringContainsString('Direction Générale', $pdf);
        self::assertStringContainsString('window.print()', $pdf);
    }

    public function test_l_excel_porte_le_courriel_et_les_statuts(): void
    {
        /*
         * Le tableur sert aux recoupements : il garde ce que l'écran allège,
         * notamment le courriel de l'auteur et le passage d'un statut à
         * l'autre.
         */
        $xls = Historique::exportExcel($this->lignes(), $this->filtres());

        self::assertStringContainsString('compta@labelleporte.ci', $xls);
        self::assertStringContainsString('en_attente', $xls);
        self::assertStringContainsString('rejetee', $xls);
        self::assertStringContainsString('Statut actuel de la demande', $xls);
    }

    public function test_un_rejet_sans_motif_est_signale(): void
    {
        // Le formulaire en exige un : s'il manque, c'est que la ligne vient
        // d'avant la règle, et la direction doit le voir plutôt que de lire un
        // tiret anodin.
        $lignes = $this->lignes();
        $lignes[0]['commentaire'] = '';

        $html = Historique::page($lignes, $this->filtres(), [], []);

        self::assertStringContainsString('Motif non enregistré', $html);
    }

    public function test_un_journal_vide_explique_pourquoi(): void
    {
        $html = Historique::page([], $this->filtres(), [], []);

        self::assertStringContainsString('Aucune décision sur cette période', $html);
    }

    public function test_la_tuile_apparait_dans_la_gestion_des_fonds(): void
    {
        $navigation = (string) file_get_contents(BASE_PATH . '/app/Services/Shared/ModuleDashboardService.php');

        self::assertStringContainsString("'key' => 'fonds_historique'", $navigation);
        self::assertStringContainsString("'url' => '/finance/fonds/historique'", $navigation);
    }

    public function test_la_route_historique_passe_avant_la_fiche(): void
    {
        /*
         * /fonds/{id} attraperait « historique » comme un identifiant, et
         * l'écran répondrait « demande introuvable ».
         */
        $routes = (string) file_get_contents(BASE_PATH . '/routes/finance.php');

        $posHistorique = strpos($routes, "'/fonds/historique'");
        $posFiche = strpos($routes, "'/fonds/{id}'");

        self::assertIsInt($posHistorique);
        self::assertIsInt($posFiche);
        self::assertLessThan($posFiche, $posHistorique);
    }
}
