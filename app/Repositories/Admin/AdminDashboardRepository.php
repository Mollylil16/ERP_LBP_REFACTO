<?php

declare(strict_types=1);

namespace App\Repositories\Admin;

final class AdminDashboardRepository
{
    public function __construct(
        private UserRepository $users,
        private PermissionRepository $permissions,
    ) {}

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        return [
            'statistics' => $this->users->statistics(),
            'entities' => $this->permissions->entities(),
            'grantedPermissions' => $this->permissions->grantedCount(),
        ];
    }
}
