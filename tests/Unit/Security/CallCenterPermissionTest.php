<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\PermissionEntityRegistry;
use Tests\TestCase;

final class CallCenterPermissionTest extends TestCase
{
    public function test_call_center_permissions_are_registered(): void
    {
        $codes = PermissionEntityRegistry::codes();

        self::assertContains(PermissionEntityRegistry::CALL_CENTER_VIEW, $codes);
        self::assertContains(PermissionEntityRegistry::CALL_CENTER_MANAGE, $codes);

        self::assertEquals('Call Center', PermissionEntityRegistry::all()[PermissionEntityRegistry::CALL_CENTER_VIEW]['module']);
        self::assertEquals('Call Center', PermissionEntityRegistry::all()[PermissionEntityRegistry::CALL_CENTER_MANAGE]['module']);
    }
}
