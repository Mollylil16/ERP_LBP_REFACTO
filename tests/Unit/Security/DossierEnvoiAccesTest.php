<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\DossierEnvoiAcces;
use Tests\TestCase;

/**
 * Droits sur les dossiers d'envoi, décidés le 15/09/2026 : l'agent export tient
 * ses dossiers, le Directeur général seul valide, personne d'autre n'y accède.
 */
final class DossierEnvoiAccesTest extends TestCase
{
    private const AGENT = 17;
    private const AUTRE_AGENT = 99;
    private const DG = 28;

    public function test_l_agent_export_tient_ses_propres_dossiers(): void
    {
        $agent = new DossierEnvoiAcces(['agent_export'], false, self::AGENT);

        self::assertTrue($agent->peutOuvrir());
        self::assertTrue($agent->peutCreer());
        self::assertSame(self::AGENT, $agent->responsableImpose(), "Ses listes sont limitées à ses dossiers.");
        self::assertTrue($agent->peutVoir($this->dossier('BROUILLON')));
        self::assertTrue($agent->peutModifier($this->dossier('LIVRE')));
        self::assertTrue($agent->peutSoumettre($this->dossier('LIVRE')));
        self::assertFalse($agent->peutSoumettre($this->dossier('PARTI')), "Un dossier non livré ne se soumet pas.");
    }

    public function test_l_agent_export_ne_voit_pas_les_dossiers_d_un_autre(): void
    {
        $agent = new DossierEnvoiAcces(['agent_export'], false, self::AGENT);
        $autre = $this->dossier('BROUILLON', self::AUTRE_AGENT);

        self::assertFalse($agent->peutVoir($autre));
        self::assertFalse($agent->peutModifier($autre));
    }

    public function test_un_dossier_soumis_ou_valide_est_verrouille_pour_l_agent(): void
    {
        $agent = new DossierEnvoiAcces(['agent_export'], false, self::AGENT);

        foreach (['SOUMIS', 'VALIDE', 'ANNULE', 'REPRIS'] as $statut) {
            self::assertFalse($agent->peutModifier($this->dossier($statut)), $statut);
        }
        self::assertTrue($agent->peutModifier($this->dossier('A_CORRIGER')), 'Un dossier renvoyé redevient modifiable.');
        self::assertFalse($agent->peutValider($this->dossier('SOUMIS')), "L'agent ne valide jamais.");
    }

    public function test_l_agent_n_annule_qu_avant_le_depart(): void
    {
        $agent = new DossierEnvoiAcces(['agent_export'], false, self::AGENT);

        self::assertTrue($agent->peutAnnuler($this->dossier('BROUILLON')));
        self::assertTrue($agent->peutAnnuler($this->dossier('RESERVE')));
        self::assertFalse($agent->peutAnnuler($this->dossier('PARTI')));
    }

    public function test_le_directeur_general_voit_tout_et_valide_seul(): void
    {
        $dg = new DossierEnvoiAcces(['dg', 'dg_surveillance'], false, self::DG);
        $dossier = $this->dossier('SOUMIS');

        self::assertTrue($dg->peutOuvrir());
        self::assertNull($dg->responsableImpose());
        self::assertTrue($dg->peutVoir($dossier));
        self::assertTrue($dg->peutValider($dossier));
        self::assertTrue($dg->peutRenvoyer($dossier));
        self::assertTrue($dg->peutRouvrir($this->dossier('VALIDE')));
        self::assertTrue($dg->peutReaffecter($this->dossier('PARTI')));
        self::assertFalse($dg->peutReaffecter($this->dossier('VALIDE')));
        self::assertFalse($dg->peutValider($this->dossier('LIVRE')), 'Seul un dossier soumis se valide.');
    }

    public function test_le_directeur_general_ne_saisit_pas(): void
    {
        $dg = new DossierEnvoiAcces(['dg'], false, self::DG);

        self::assertFalse($dg->peutCreer());
        self::assertFalse($dg->peutModifier($this->dossier('BROUILLON', self::DG)));
    }

    public function test_l_assistant_dg_et_les_autres_roles_n_ont_aucun_acces(): void
    {
        foreach ([['assistant_dg'], ['assistante_dg'], ['agent_saisie'], ['chef_agence'], ['comptable'], ['responsable_logistique'], []] as $roles) {
            $acces = new DossierEnvoiAcces($roles, false, 5);
            self::assertFalse($acces->peutOuvrir(), implode(',', $roles) ?: 'aucun rôle');
            self::assertFalse($acces->peutVoir($this->dossier('SOUMIS', 5)));
        }
    }

    public function test_l_administrateur_garde_un_acces_technique_complet(): void
    {
        $admin = new DossierEnvoiAcces([], true, 1);

        self::assertTrue($admin->peutOuvrir());
        self::assertTrue($admin->peutModifier($this->dossier('BROUILLON', self::AGENT)));
        self::assertTrue($admin->peutValider($this->dossier('SOUMIS')));
    }

    /** @return array<string, mixed> */
    private function dossier(string $statut, int $responsable = self::AGENT): array
    {
        return ['id' => 1, 'statut' => $statut, 'responsable_id' => $responsable];
    }
}
