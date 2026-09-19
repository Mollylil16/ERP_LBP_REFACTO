<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\RapprochementEnvoisAcces;
use Tests\TestCase;

/**
 * Qui ouvre le rapprochement des envois.
 *
 * L'écran montre les chiffres de saisie des agences. La règle du 15/09/2026 les
 * réservait au Directeur général, pour que l'agent export ne puisse pas les
 * recopier sur la LTA. Le 18/09 la direction l'a ouvert au comptable et à son
 * assistante — aucun des deux ne prépare de départ. Il reste fermé à ceux qui
 * saisissent.
 */
final class RapprochementEnvoisAccesTest extends TestCase
{
    /** @param array<int, string> $roles */
    private function acces(array $roles, bool $admin = false): RapprochementEnvoisAcces
    {
        return new RapprochementEnvoisAcces($roles, $admin, 42);
    }

    public function test_le_comptable_ouvre_et_saisit(): void
    {
        $acces = $this->acces(['comptable']);

        self::assertTrue($acces->peutOuvrir());
        self::assertTrue($acces->peutSaisir());
    }

    public function test_le_directeur_general_ouvre_et_saisit(): void
    {
        $acces = $this->acces(['dg']);

        self::assertTrue($acces->peutOuvrir());
        self::assertTrue($acces->peutSaisir());
    }

    public function test_l_administrateur_passe_partout(): void
    {
        $acces = $this->acces([], true);

        self::assertTrue($acces->peutOuvrir());
        self::assertTrue($acces->peutSaisir());
    }

    public function test_l_assistante_dg_lit_sans_corriger(): void
    {
        foreach (['assistant_dg', 'assistante_dg'] as $role) {
            $acces = $this->acces([$role]);

            self::assertTrue($acces->peutOuvrir(), "{$role} doit pouvoir consulter.");
            self::assertFalse($acces->peutSaisir(), "{$role} ne modifie jamais rien.");
        }
    }

    /**
     * Le cœur de la règle : celui qui prépare le départ ou saisit les colis ne
     * doit pas voir les chiffres auxquels son document sera comparé.
     */
    public function test_ceux_qui_saisissent_n_ouvrent_pas_l_ecran(): void
    {
        foreach (['agent_export', 'agent_saisie', 'agent_enregistrement', 'chef_agence', 'caissiere', 'gestionnaire_caisse'] as $role) {
            self::assertFalse(
                $this->acces([$role])->peutOuvrir(),
                "{$role} ne doit pas voir les chiffres de saisie des agences."
            );
        }
    }

    public function test_un_compte_sans_role_n_ouvre_rien(): void
    {
        self::assertFalse($this->acces([])->peutOuvrir());
        self::assertFalse($this->acces([])->peutSaisir());
    }
}
