<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Security\DemandesFondsAcces;
use Tests\TestCase;

/**
 * Le partage des taches sur les demandes de fonds.
 *
 * Demande du comptable SIBRI PAUL le 14/10/2026, accordee par l'administrateur :
 * « faites que madame Yedess fasse les validations des demandes et moi je
 * decaisse, comme ca on pourra partager les taches ».
 *
 * Avant, l'assistante DG pouvait autoriser une depense ET sortir l'argent sur
 * la meme demande, pendant que le comptable validait sans jamais payer.
 */
final class DemandesFondsPartageTest extends TestCase
{
    private function controleur(): string
    {
        return (string) file_get_contents(
            BASE_PATH . '/app/Controllers/Finance/DemandesFondsController.php'
        );
    }

    public function test_l_assistante_dg_valide_et_le_comptable_non(): void
    {
        self::assertContains('assistant_dg', DemandesFondsAcces::ROLES_VALIDATION);
        self::assertContains('assistante_dg', DemandesFondsAcces::ROLES_VALIDATION);
        self::assertContains('dg', DemandesFondsAcces::ROLES_VALIDATION);

        self::assertNotContains('comptable', DemandesFondsAcces::ROLES_VALIDATION);
    }

    public function test_le_comptable_decaisse_et_l_assistante_dg_non(): void
    {
        self::assertContains('comptable', DemandesFondsAcces::ROLES_DECAISSEMENT);

        // Elle tient physiquement l'espece en agence : la direction l'avait
        // tranche le 05/10/2026, et le partage ne la remet pas en cause.
        self::assertContains('caissiere_principale', DemandesFondsAcces::ROLES_DECAISSEMENT);

        self::assertNotContains('assistant_dg', DemandesFondsAcces::ROLES_DECAISSEMENT);
        self::assertNotContains('assistante_dg', DemandesFondsAcces::ROLES_DECAISSEMENT);
    }

    public function test_les_deux_gestes_ne_se_recouvrent_que_pour_le_dg(): void
    {
        /*
         * Le DG garde les deux pour qu'un jour de conge ne bloque pas une
         * sortie de caisse. C'est le seul cumul tolere, et le garde-fou des
         * deux mains l'empeche de se refermer sur une demande precise.
         */
        $cumul = array_intersect(
            DemandesFondsAcces::ROLES_VALIDATION,
            DemandesFondsAcces::ROLES_DECAISSEMENT
        );

        self::assertSame(['dg'], array_values($cumul));
    }

    public function test_on_ne_decaisse_pas_ce_qu_on_a_soi_meme_valide(): void
    {
        $source = $this->controleur();

        $debut = (int) strpos($source, 'public function decaisser');
        $action = substr($source, $debut, (int) strpos($source, 'public function historique') - $debut);

        // Le refus doit tomber AVANT l'ecriture en base, sinon il ne protege rien.
        $refus = (int) strpos($action, 'aDejaValide');
        $ecriture = (int) strpos($action, 'decaisserDemande');

        self::assertGreaterThan(0, $refus, 'Le garde-fou des deux mains est absent du décaissement.');
        self::assertGreaterThan(0, $ecriture);
        self::assertLessThan($ecriture, $refus, 'Le refus arrive après le décaissement.');
    }

    public function test_le_panneau_disparait_pour_qui_a_valide(): void
    {
        /*
         * Montrer le panneau puis refuser au clic serait pire que de ne rien
         * montrer : le comptable a cherche deux jours une fenetre absente.
         */
        $source = $this->controleur();

        self::assertStringContainsString(
            '$canDecaisser = DemandesFondsAcces::peutDecaisser()',
            $source
        );
        self::assertStringContainsString(
            '&& !DemandesFondsAcces::aDejaValide($demande->validateurId)',
            $source
        );
    }

    public function test_la_regle_ne_se_recopie_plus_dans_le_controleur(): void
    {
        /*
         * La liste etait repetee cinq fois et avait deja derive : la liste des
         * demandes reconnaissait le comptable, la fiche l'avait oublie.
         */
        $source = $this->controleur();

        self::assertStringNotContainsString(
            "Auth::hasAnyRole(['dg', 'assistant_dg', 'comptable'])",
            $source
        );
        self::assertStringNotContainsString(
            "['admin', 'dg', 'assistant_dg', 'caissiere_principale']",
            $source
        );
    }

    public function test_le_refus_de_validation_dit_au_comptable_ou_est_sa_place(): void
    {
        // Un « accès refusé » sec l'aurait renvoye vers l'administrateur ; il
        // doit lire que le decaissement, lui, est desormais le sien.
        self::assertStringContainsString(
            'Le comptable, lui, procède au décaissement.',
            $this->controleur()
        );
    }
}
