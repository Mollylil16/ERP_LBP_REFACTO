<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\PermissionEntityRegistry;
use Tests\TestCase;

final class PermissionEntityRegistryTest extends TestCase
{
    public function test_registry_contains_expected_core_entities(): void
    {
        $codes = PermissionEntityRegistry::codes();

        self::assertContains(PermissionEntityRegistry::USERS, $codes);
        self::assertContains(PermissionEntityRegistry::RH_EMPLOYEES, $codes);
        self::assertContains(PermissionEntityRegistry::RH_EMPLOYEE_MUTATIONS, $codes);
        self::assertCount(16, $codes);
    }

    public function test_unknown_entity_does_not_exist(): void
    {
        self::assertFalse(PermissionEntityRegistry::exists('unknown_entity'));
    }

    public function test_codes_for_rh_module_are_only_rh_entities(): void
    {
        $codes = PermissionEntityRegistry::codesForModule('Ressources humaines');

        self::assertContains(PermissionEntityRegistry::RH_EMPLOYEES, $codes);
        self::assertNotContains(PermissionEntityRegistry::USERS, $codes);
    }
}
