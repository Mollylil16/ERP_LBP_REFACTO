<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\ModuleAccess;
use App\Services\Admin\AdminService;
use ReflectionClass;
use Tests\TestCase;

/**
 * Le catalogue des rôles d'Administration contre les rôles réellement portés.
 *
 * Deux agents de saisie d'Adjamé se sont retrouvés sans aucun rôle, sans
 * pouvoir facturer ni encaisser. La cause n'était pas leur compte : leur rôle
 * n'était pas dans le catalogue, donc le formulaire n'avait pas de case pour
 * lui, donc l'enregistrement — un numéro de téléphone corrigé suffisait — ne le
 * renvoyait pas, et setRoles() le remplaçait par ce qui avait été coché.
 *
 * Ces deux tests ferment les deux moitiés du piège.
 */
final class CatalogueRolesTest extends TestCase
{
    /**
     * Un rôle porté par des comptes réels mais absent du catalogue ne peut pas
     * être attribué depuis Administration : on ne peut nommer personne agent de
     * saisie, alors que c'est le rôle le plus répandu de l'entreprise.
     */
    public function test_les_roles_reellement_portes_sont_attribuables(): void
    {
        // Relevés sur la base de production le 18/09/2026, agence par agence.
        $portesEnProduction = [
            'agent_saisie', 'agent_enregistrement', 'gestionnaire_caisse',
            'agent_call_center', 'agent_exploitation',
            'passeur_douane', 'declarant_douane',
            'dg_surveillance', 'responsable_rh',
            'responsable_marketing', 'responsable_logistique',
        ];

        $catalogue = array_keys(AdminService::AVAILABLE_ROLES);

        foreach ($portesEnProduction as $role) {
            self::assertContains(
                $role,
                $catalogue,
                "Le rôle {$role} est porté par des comptes réels : Administration doit pouvoir l'attribuer."
            );
        }
    }

    /**
     * Enregistrer le formulaire ne doit jamais retirer un rôle que le
     * formulaire n'affichait pas.
     */
    public function test_enregistrer_le_formulaire_ne_supprime_pas_un_role_absent_du_formulaire(): void
    {
        $service = (new ReflectionClass(AdminService::class))->newInstanceWithoutConstructor();
        $methode = new \ReflectionMethod(AdminService::class, 'rolesApresFormulaire');
        $methode->setAccessible(true);

        // Le compte porte un rôle inconnu du catalogue ; le formulaire n'a coché
        // que « caissiere ». Le rôle inconnu doit survivre.
        $obtenus = $methode->invoke(
            $service,
            ['roles' => ['caissiere', 'role_invente_qui_n_existe_pas']],
            ['assistante_dg', 'caissiere']
        );

        self::assertContains('caissiere', $obtenus);
        self::assertContains('assistante_dg', $obtenus, "Un rôle absent du formulaire ne doit pas être effacé par l'enregistrement.");
        self::assertNotContains('role_invente_qui_n_existe_pas', $obtenus, "Le formulaire ne doit pas pouvoir inventer un rôle.");
    }

    public function test_un_role_du_catalogue_decoche_est_bien_retire(): void
    {
        $service = (new ReflectionClass(AdminService::class))->newInstanceWithoutConstructor();
        $methode = new \ReflectionMethod(AdminService::class, 'rolesApresFormulaire');
        $methode->setAccessible(true);

        $obtenus = $methode->invoke($service, ['roles' => ['caissiere']], ['caissiere', 'chef_agence']);

        self::assertSame(['caissiere'], $obtenus, 'Décocher un rôle du catalogue doit le retirer.');
    }

    public function test_le_role_vide_herite_d_anciennes_saisies_disparait(): void
    {
        $service = (new ReflectionClass(AdminService::class))->newInstanceWithoutConstructor();
        $methode = new \ReflectionMethod(AdminService::class, 'rolesApresFormulaire');
        $methode->setAccessible(true);

        $obtenus = $methode->invoke($service, ['roles' => ['dg', 'dg_surveillance']], ['', 'dg', 'dg_surveillance']);

        self::assertNotContains('', $obtenus);
        self::assertSame(['dg', 'dg_surveillance'], $obtenus);
    }
}
