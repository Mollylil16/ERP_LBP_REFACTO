<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Repositories\Admin\PermissionRepository;
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
        if ($this->user->isAdmin || in_array('dg', $this->user->roles, true)) {
            return true;
        }

        // L'Assistant DG a un accès total en consultation (VIEW) sur toutes les entités métier
        if (in_array('assistant_dg', $this->user->roles, true) && $action === PermissionAction::VIEW) {
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
