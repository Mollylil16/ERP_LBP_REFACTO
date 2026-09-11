<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\ModuleAccess;
use App\Services\Admin\AdminService;
use App\Services\Shared\ModuleDashboardService;
use Tests\TestCase;

/**
 * Les habilitations des six modules métier.
 *
 * Ce test ne juge pas le choix des rôles, qui relève du métier : il vérifie que
 * ce choix reste cohérent, c'est-à-dire qu'aucune liste ne nomme un rôle qui
 * n'existe pas, qu'aucun module ne devient ouvert à tous par inadvertance, et
 * que l'écriture reste plus étroite que la lecture.
 */
final class ModuleAccessTest extends TestCase
{
    private const MODULES = [
        'entrepots',
        'flotte-transport',
        'transit-douane',
        'portefeuille-clients',
        'agents-correspondants',
        'tracking-colis',
    ];

    public function test_chaque_module_declare_ses_roles_de_lecture(): void
    {
        foreach (self::MODULES as $slug) {
            self::assertTrue(
                ModuleAccess::estGere($slug),
                "Le module {$slug} n'a aucune liste de rôles : il serait ouvert par défaut."
            );
            self::assertNotEmpty(ModuleAccess::rolesLecture($slug));
        }
    }

    public function test_les_roles_cites_existent_reellement(): void
    {
        /*
         * Deux sources, pas une. AVAILABLE_ROLES est le catalogue proposé dans
         * l'administration, mais la base de production porte onze rôles qui n'y
         * figurent pas — « agent_saisie » en tête, qui est le plus répandu.
         * Valider contre le seul catalogue aurait donc interdit de nommer
         * précisément les rôles qui ont réellement besoin des modules.
         */
        $connus = array_merge(
            array_keys(AdminService::AVAILABLE_ROLES),
            ModuleAccess::ROLES_HORS_CATALOGUE
        );
        $inconnus = [];

        foreach (self::MODULES as $slug) {
            $roles = array_merge(
                ModuleAccess::rolesLecture($slug),
                ModuleAccess::rolesGestion($slug)
            );

            foreach ($roles as $role) {
                if (in_array($role, $connus, true)) {
                    continue;
                }
                $inconnus[] = $slug . ' → ' . $role;
            }
        }

        self::assertSame(
            [],
            $inconnus,
            "Rôle(s) inconnu(s) : un rôle mal orthographié n'ouvre rien et ne lève aucune erreur.\n  "
                . implode("\n  ", $inconnus)
        );
    }

    public function test_la_direction_accede_partout(): void
    {
        foreach (self::MODULES as $slug) {
            $roles = ModuleAccess::rolesLecture($slug);

            self::assertContains('dg', $roles, "Le DG doit pouvoir ouvrir {$slug}.");
            self::assertContains('admin', $roles, "L'administrateur doit pouvoir ouvrir {$slug}.");
        }
    }

    public function test_les_roles_hors_catalogue_sont_reellement_utilises(): void
    {
        // Cette liste n'existe que pour nommer des rôles qui vivent en base.
        // Elle ne doit pas devenir un fourre-tout : un rôle qui entre dans le
        // catalogue doit en sortir.
        $catalogue = array_keys(AdminService::AVAILABLE_ROLES);
        $doublons = array_intersect(ModuleAccess::ROLES_HORS_CATALOGUE, $catalogue);

        self::assertSame(
            [],
            array_values($doublons),
            "Rôle(s) désormais au catalogue : retirez-les de ROLES_HORS_CATALOGUE.\n  "
                . implode("\n  ", $doublons)
        );
    }

    public function test_aucun_module_n_est_ouvert_a_tous_les_roles(): void
    {
        $total = count(AdminService::AVAILABLE_ROLES) + count(ModuleAccess::ROLES_HORS_CATALOGUE);

        foreach (self::MODULES as $slug) {
            self::assertLessThan(
                $total,
                count(ModuleAccess::rolesLecture($slug)),
                "Le module {$slug} est ouvert à tous les rôles : la liste ne filtre plus rien."
            );
        }
    }

    public function test_l_ecriture_est_plus_etroite_que_la_lecture(): void
    {
        foreach (self::MODULES as $slug) {
            $lecture = ModuleAccess::rolesLecture($slug);
            $gestion = ModuleAccess::rolesGestion($slug);

            self::assertLessThanOrEqual(count($lecture), count($gestion), "Module {$slug}.");

            foreach ($gestion as $role) {
                self::assertContains(
                    $role,
                    $lecture,
                    "Le rôle {$role} peut écrire dans {$slug} sans pouvoir y entrer en lecture."
                );
            }
        }
    }

    public function test_chaque_module_garde_existe_dans_le_catalogue(): void
    {
        $catalogue = array_keys((new ModuleDashboardService())->modules());

        foreach (self::MODULES as $slug) {
            self::assertContains(
                $slug,
                $catalogue,
                "Le slug {$slug} ne correspond à aucun module du catalogue : la tuile du portail ne sera jamais filtrée."
            );
        }
    }
}
