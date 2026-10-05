<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Gestion des fonds : qui tranche, et à quelle étape.
 *
 * Une demande passe par trois mains différentes — celle qui valide, celle qui
 * sort l'argent, celle qui reçoit les justificatifs. Le danger n'est pas qu'un
 * profil en fasse trop, c'est que l'écran et l'action ne s'accordent pas : on
 * lit alors une demande sans voir le bouton qu'on a pourtant le droit de
 * cliquer, et la demande dort.
 */
final class GestionFondsCircuitTest extends TestCase
{
    private function source(): string
    {
        return (string) file_get_contents(BASE_PATH . '/app/Controllers/Finance/DemandesFondsController.php');
    }

    public function test_la_fiche_montre_le_bouton_a_qui_a_le_droit_de_valider(): void
    {
        /*
         * show() omettait le comptable, que valider() accepte. Il voyait donc
         * la fiche sans pouvoir la trancher.
         */
        $source = $this->source();

        $roles = "Auth::hasAnyRole(['dg', 'assistant_dg', 'comptable'])";

        self::assertStringContainsString(
            '$canValidate = Auth::isAdmin() || ' . $roles,
            $source,
            "La fiche détaillée doit proposer le bouton à tous ceux qui peuvent valider."
        );

        // valider() et rejeter() portent la même condition : l'écran doit
        // s'accorder avec les deux, pas avec une seule.
        self::assertSame(
            2,
            substr_count($source, 'if (!(Auth::isAdmin() || ' . $roles . '))'),
            "Valider et rejeter doivent reconnaître les mêmes personnes."
        );
    }

    public function test_le_decaissement_reste_a_la_caisse(): void
    {
        // Valider n'est pas payer : celui qui autorise la dépense ne doit pas
        // être le seul maillon jusqu'à la sortie des espèces.
        $source = $this->source();

        self::assertStringContainsString(
            "RoleMiddleware::check(['admin', 'dg', 'assistant_dg', 'caissiere_principale', 'caissiere', 'chef_agence'])",
            $source
        );
        self::assertStringNotContainsString("statut = 'decaissee'\n", $source);
    }

    public function test_le_rejet_revient_aux_memes_personnes_que_la_validation(): void
    {
        $source = $this->source();

        $extrait = substr($source, (int) strpos($source, 'public function rejeter'));

        self::assertStringContainsString("Auth::hasAnyRole(['dg', 'assistant_dg', 'comptable'])", $extrait);
    }

    public function test_le_demandeur_peut_justifier_sa_propre_depense(): void
    {
        // L'imputation n'est pas une faveur : c'est à celui qui a dépensé de
        // rapporter les pièces, sans quoi le reliquat ne revient jamais.
        $source = $this->source();

        self::assertStringContainsString('(int) Auth::id() === $demande->demandeurId', $source);
    }
}
