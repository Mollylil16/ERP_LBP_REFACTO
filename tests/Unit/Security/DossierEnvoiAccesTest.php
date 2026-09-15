<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\DossierEnvoiAcces;
use Tests\TestCase;

/**
 * Droits sur les départs, décidés le 15/09/2026 : l'agent export seul prépare
 * et soumet, le Directeur général seul voit la saisie des colis et valide.
 */
final class DossierEnvoiAccesTest extends TestCase
{
    private const AGENT = 17;
    private const DG = 28;

    public function test_l_agent_export_prepare_et_complete_ses_departs(): void
    {
        $agent = new DossierEnvoiAcces(['agent_export'], false, self::AGENT);

        self::assertTrue($agent->peutOuvrir());
        self::assertTrue($agent->peutCreer());
        self::assertSame(self::AGENT, $agent->responsableImpose());
        self::assertTrue($agent->peutModifier($this->depart('EN_COURS')));
        self::assertTrue($agent->peutModifier($this->depart('A_CORRIGER')));
        self::assertTrue($agent->peutSoumettre($this->depart('EN_COURS')));
    }

    public function test_l_agent_export_ne_voit_jamais_la_saisie_des_colis(): void
    {
        self::assertFalse((new DossierEnvoiAcces(['agent_export'], false, self::AGENT))->voitSaisie());
    }

    public function test_un_depart_soumis_ou_valide_est_verrouille_pour_l_agent(): void
    {
        $agent = new DossierEnvoiAcces(['agent_export'], false, self::AGENT);

        self::assertFalse($agent->peutModifier($this->depart('SOUMIS')));
        self::assertFalse($agent->peutModifier($this->depart('VALIDE')));
        self::assertFalse($agent->peutValider($this->depart('SOUMIS')));
        self::assertFalse($agent->peutVoir($this->depart('EN_COURS', 99)), "Il ne voit pas les départs d'un autre agent.");
    }

    public function test_le_directeur_general_controle_et_valide_sans_saisir(): void
    {
        $dg = new DossierEnvoiAcces(['dg', 'dg_surveillance'], false, self::DG);

        self::assertTrue($dg->voitSaisie());
        self::assertNull($dg->responsableImpose());
        self::assertTrue($dg->peutVoir($this->depart('SOUMIS')));
        self::assertTrue($dg->peutValider($this->depart('SOUMIS')));
        self::assertTrue($dg->peutRenvoyer($this->depart('SOUMIS')));
        self::assertTrue($dg->peutRouvrir($this->depart('VALIDE')));
        self::assertFalse($dg->peutValider($this->depart('EN_COURS')));
        self::assertFalse($dg->peutCreer());
        self::assertFalse($dg->peutModifier($this->depart('EN_COURS', self::DG)));
    }

    public function test_l_assistant_dg_et_les_autres_roles_n_ont_aucun_acces(): void
    {
        foreach ([['assistant_dg'], ['agent_saisie'], ['chef_agence'], ['comptable'], []] as $roles) {
            $acces = new DossierEnvoiAcces($roles, false, 5);
            self::assertFalse($acces->peutOuvrir(), implode(',', $roles) ?: 'aucun rôle');
            self::assertFalse($acces->voitSaisie());
            self::assertFalse($acces->peutVoir($this->depart('EN_COURS', 5)));
        }
    }

    public function test_l_administrateur_garde_un_acces_technique_complet(): void
    {
        $admin = new DossierEnvoiAcces([], true, 1);

        self::assertTrue($admin->peutCreer());
        self::assertTrue($admin->voitSaisie());
        self::assertTrue($admin->peutModifier($this->depart('EN_COURS')));
        self::assertTrue($admin->peutValider($this->depart('SOUMIS')));
    }

    /** @return array<string, mixed> */
    private function depart(string $statut, int $responsable = self::AGENT): array
    {
        return ['id' => 1, 'statut' => $statut, 'responsable_id' => $responsable];
    }
}
