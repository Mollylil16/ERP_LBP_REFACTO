<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\PermissionRepository;
use App\Security\OperationPolicy;
use App\Security\PermissionAction;
use App\Security\PermissionEntityRegistry;

class AuthorizationService
{
    private ?array $permissionMap = null;

    public function __construct(
        private ?User $user,
        private PermissionRepository $permissions,
    ) {}

    public function can(string $entityCode, string $action = PermissionAction::VIEW): bool
    {
        if (
            !$this->user
            || $this->user->status !== 'active'
            || !PermissionEntityRegistry::exists($entityCode)
            || !PermissionAction::isValid($action)
        ) {
            return false;
        }
        if ($this->user->isAdmin) {
            return true;
        }

        $this->permissionMap ??= $this->permissions->permissionMapForUser((int) $this->user->id);

        return !empty($this->permissionMap[$entityCode][$action]);
    }

    public function canAll(array $requirements): bool
    {
        foreach ($requirements as $entityCode => $action) {
            if (!$this->can((string) $entityCode, (string) $action)) {
                return false;
            }
        }
        return true;
    }

    public function canAny(array $requirements): bool
    {
        foreach ($requirements as $entityCode => $action) {
            if ($this->can((string) $entityCode, (string) $action)) {
                return true;
            }
        }
        return false;
    }

    public function canOperation(string $operation): bool
    {
        return $this->canAll(OperationPolicy::requirements($operation));
    }
}
