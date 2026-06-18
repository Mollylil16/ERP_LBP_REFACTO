<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\OperationPolicy;
use App\Security\PermissionAction;
use App\Security\PermissionEntityRegistry;
use InvalidArgumentException;
use Tests\TestCase;

final class OperationPolicyTest extends TestCase
{
    public function test_mutation_creation_requires_all_expected_permissions(): void
    {
        $requirements = OperationPolicy::requirements(OperationPolicy::RH_MUTATION_CREATE);

        self::assertSame(PermissionAction::UPDATE, $requirements[PermissionEntityRegistry::RH_EMPLOYEES]);
        self::assertSame(PermissionAction::CREATE, $requirements[PermissionEntityRegistry::RH_EMPLOYEE_MUTATIONS]);
        self::assertSame(PermissionAction::CREATE, $requirements[PermissionEntityRegistry::RH_EMPLOYEE_HISTORY]);
    }

    public function test_unknown_operation_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        OperationPolicy::requirements('unknown.operation');
    }
}
