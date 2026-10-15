<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Finance\Facture;
use App\Models\Finance\Paiement;
use App\View\Components\Finance;
use Tests\TestCase;

/**
 * Annuler un règlement, et lui seul.
 *
 * Le 15/10/2026, deux caissières de la même agence ont encaissé le même
 * acompte de 100 000 F à 92 secondes d'intervalle sur la facture de la cliente
 * MARIE. Le logiciel ne savait alors qu'une chose : « Réinitialiser », qui
 * efface TOUS les règlements d'une facture. L'administrateur a donc corrigé la
 * facture à la main — et les deux lignes de paiement sont restées.
 *
 * Or le rapport du jour additionne les paiements, pas le montant de la
 * facture : il annonçait 200 000 F encaissés, et autant d'espèces attendues
 * dans le tiroir le soir. La caissière serait apparue en manque de 100 000 F.
 */
final class AnnulationReglementTest extends TestCase
{
    private function controleur(): string
    {
        return (string) file_get_contents(
            BASE_PATH . '/app/Controllers/Finance/FinanceController.php'
        );
    }

    private function action(): string
    {
        $source = $this->controleur();
        $debut = (int) strpos($source, 'public function paiementAnnuler');

        return substr($source, $debut, (int) strpos($source, 'public function factureRelancer') - $debut);
    }

    public function test_la_route_existe(): void
    {
        $routes = (string) file_get_contents(BASE_PATH . '/routes/finance.php');

        self::assertStringContainsString(
            "/factures/{id}/paiements/{paiementId}/annuler",
            $routes
        );
        self::assertStringContainsString("'paiementAnnuler'", $routes);
    }

    public function test_le_reglement_doit_appartenir_a_la_facture(): void
    {
        /*
         * Sans ce contrôle, un numéro deviné dans l'URL effacerait
         * l'encaissement d'une autre facture, voire d'une autre agence.
         */
        self::assertStringContainsString(
            '$paiement->factureId !== $facture->id',
            $this->action()
        );
        self::assertStringContainsString('Auth::checkAgencyScope', $this->action());
    }

    public function test_le_motif_est_obligatoire(): void
    {
        // C'est la seule chose que la direction lira pour distinguer une saisie
        // en double d'un encaissement escamoté.
        $action = $this->action();

        self::assertStringContainsString("\$motif === ''", $action);
        self::assertStringContainsString('motif_annulation', $action);
    }

    public function test_un_point_de_caisse_consolide_bloque_l_annulation(): void
    {
        /*
         * Consolidé veut dire signé par la caissière principale. Défaire un
         * total déjà validé dans son dos serait pire que le doublon.
         */
        $action = $this->action();

        $refus = (int) strpos($action, 'pointDuJourConsolide');
        $suppression = (int) strpos($action, '$this->paiementRepo->supprimer');

        self::assertGreaterThan(0, $refus, 'Le garde-fou du point consolidé est absent.');
        self::assertGreaterThan(0, $suppression);
        self::assertLessThan($suppression, $refus, 'Le refus arrive après la suppression.');
    }

    public function test_la_facture_se_recalcule_sur_ce_qui_reste(): void
    {
        /*
         * Et non par soustraction du montant annulé : c'est précisément une
         * facture retouchée à la main qui avait divergé de ses règlements.
         */
        $action = $this->action();

        self::assertStringContainsString('$restants = $this->paiementRepo->findByFactureId', $action);
        self::assertStringContainsString('$facture->montantEncaisse = $encaisse;', $action);
        self::assertStringNotContainsString('$facture->montantEncaisse -=', $action);
    }

    public function test_la_suppression_rouvre_le_point_du_jour(): void
    {
        // Les totaux du jour changent : le point doit être recompté avant la
        // fermeture, comme pour un encaissement tardif.
        $depot = (string) file_get_contents(
            BASE_PATH . '/app/Repositories/Finance/PaiementRepository.php'
        );

        $debut = (int) strpos($depot, 'public function supprimer');
        $methode = substr($depot, $debut, 400);

        self::assertStringContainsString('DELETE FROM lbp_paiements WHERE id = :id', $methode);
        self::assertStringContainsString('rouvrirLePointDuJour', $methode);
    }

    public function test_le_bouton_ne_s_affiche_qu_aux_habilites(): void
    {
        $facture = $this->facture();
        $paiement = new Paiement(
            id: 602,
            factureId: 4033,
            caissiereId: 47,
            montant: 100000.0,
            devise: 'XOF',
            mode: 'especes',
            type: 'acompte',
            datePaiement: '2026-10-15 08:59:44',
            agenceId: 3403
        );

        $sans = Finance::factureShowPage($facture, [$paiement], [], [], [], [], 0.0, false);
        $avec = Finance::factureShowPage($facture, [$paiement], [], [], [], [], 0.0, true);

        self::assertStringNotContainsString('Annuler ce règlement', $sans);
        self::assertStringContainsString('Annuler ce règlement', $avec);

        // Le motif est exigé par le formulaire lui-même, pas seulement côté serveur.
        self::assertStringContainsString('name="motif" required', $avec);
        self::assertStringContainsString('/paiements/602/annuler', $avec);
    }

    public function test_plusieurs_reglements_appellent_l_avertissement(): void
    {
        /*
         * C'est le cas du doublon. Corriger la facture suffit à l'écran, mais
         * pas au rapport du jour : il faut le dire là où la main hésite.
         */
        $facture = $this->facture();
        $un = new Paiement(601, 4033, 6, 100000.0, 'XOF', 'especes', 'acompte', '2026-10-15 08:58:12', 3403);
        $deux = new Paiement(602, 4033, 47, 100000.0, 'XOF', 'especes', 'acompte', '2026-10-15 08:59:44', 3403);

        $seul = Finance::factureShowPage($facture, [$deux], [], [], [], [], 0.0, true);
        $double = Finance::factureShowPage($facture, [$un, $deux], [], [], [], [], 0.0, true);

        // « saisi deux fois » sert aussi d'exemple dans le champ Motif : c'est
        // la phrase de l'avertissement qu'on cherche, pas ce mot-là.
        self::assertStringNotContainsString('porte plusieurs règlements', $seul);
        self::assertStringContainsString('porte plusieurs règlements', $double);
        self::assertStringContainsString('le tiroir serait attendu trop plein', $double);
    }

    private function facture(): Facture
    {
        return new Facture(
            id: 4033,
            numeroFacture: 'FA-3403-2026-000302',
            colisId: 1013,
            clientId: 1761,
            caissiereId: 47,
            agenceId: 3403,
            montantTotal: 259900.0,
            montantEncaisse: 100000.0,
            montantRestant: 159900.0,
            devise: 'XOF',
            tauxChange: null,
            statut: 'partiellement_payee',
            dateEmission: '2026-10-15 07:56:31'
        );
    }
}
