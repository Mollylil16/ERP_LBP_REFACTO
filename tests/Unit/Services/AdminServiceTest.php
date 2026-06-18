<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\User;
use App\Repositories\PermissionRepository;
use App\Repositories\RhPersonnelRepository;
use App\Repositories\UserRepository;
use App\Services\AdminService;
use PDO;
use RuntimeException;
use Tests\TestCase;

final class AdminServiceTest extends TestCase
{
    public function test_list_users_normalizes_invalid_filters(): void
    {
        $users = $this->createMock(UserRepository::class);
        $users->expects(self::once())
            ->method('paginate')
            ->with(['q' => 'admin', 'status' => '', 'profile' => ''], 0, 15)
            ->willReturn(['items' => [], 'total' => 0, 'page' => 1, 'perPage' => 15, 'totalPages' => 1]);

        $service = $this->service($users);

        $result = $service->listUsers(['q' => ' admin ', 'status' => 'bad', 'profile' => 'root', 'page' => 0]);

        self::assertSame(['q' => 'admin', 'status' => '', 'profile' => ''], $result['filters']);
    }

    public function test_set_user_active_prevents_self_deactivation(): void
    {
        $user = new User(5, 'Admin', 'admin@erp-lbp.local', null, 'hash', 'active', true);

        $users = $this->createMock(UserRepository::class);
        $users->method('findById')->with(5)->willReturn($user);
        $users->expects(self::never())->method('setStatus');

        $service = $this->service($users);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Vous ne pouvez pas désactiver votre propre compte.');

        $service->setUserActive(5, false, 5);
    }

    public function test_set_user_active_updates_other_user_status(): void
    {
        $user = new User(6, 'User', 'user@erp-lbp.local', null, 'hash', 'inactive', false);

        $users = $this->createMock(UserRepository::class);
        $users->method('findById')->with(6)->willReturn($user);
        $users->expects(self::once())->method('setStatus')->with(6, 'active');

        $this->service($users)->setUserActive(6, true, 5);
    }

    public function test_save_permissions_rejects_admin_user(): void
    {
        $user = new User(7, 'Admin', 'admin@erp-lbp.local', null, 'hash', 'active', true);

        $users = $this->createMock(UserRepository::class);
        $users->method('findById')->with(7)->willReturn($user);

        $permissions = $this->createMock(PermissionRepository::class);
        $permissions->expects(self::never())->method('replaceForUser');

        $service = $this->service($users, $permissions);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Un administrateur dispose déjà de tous les droits.');

        $service->savePermissions(7, ['permissions' => []]);
    }

    private function service(?UserRepository $users = null, ?PermissionRepository $permissions = null): AdminService
    {
        return new AdminService(
            $users ?? $this->createStub(UserRepository::class),
            $permissions ?? $this->createStub(PermissionRepository::class),
            $this->createStub(RhPersonnelRepository::class),
            $this->createStub(PDO::class),
        );
    }
}
