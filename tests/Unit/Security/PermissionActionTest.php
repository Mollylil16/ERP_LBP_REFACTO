<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\PermissionAction;
use Tests\TestCase;

final class PermissionActionTest extends TestCase
{
    public function test_create_update_delete_imply_view(): void
    {
        $rights = PermissionAction::normalize([
            PermissionAction::CREATE => true,
            PermissionAction::UPDATE => false,
            PermissionAction::DELETE => false,
        ]);

        self::assertTrue($rights[PermissionAction::VIEW]);
        self::assertTrue($rights[PermissionAction::CREATE]);
        self::assertFalse($rights[PermissionAction::UPDATE]);
        self::assertFalse($rights[PermissionAction::DELETE]);
    }

    public function test_unknown_action_has_no_database_column(): void
    {
        self::assertNull(PermissionAction::column('unknown'));
        self::assertFalse(PermissionAction::isValid('unknown'));
    }

    public function test_valid_actions_have_database_columns(): void
    {
        self::assertSame('can_view', PermissionAction::column(PermissionAction::VIEW));
        self::assertSame('can_create', PermissionAction::column(PermissionAction::CREATE));
        self::assertSame('can_update', PermissionAction::column(PermissionAction::UPDATE));
        self::assertSame('can_delete', PermissionAction::column(PermissionAction::DELETE));
    }
}
