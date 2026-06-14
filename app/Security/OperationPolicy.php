<?php

namespace App\Security;

use InvalidArgumentException;

final class OperationPolicy
{
    public const RH_EMPLOYEE_VIEW = 'rh.employee.view';
    public const RH_EMPLOYEE_CREATE = 'rh.employee.create';
    public const RH_EMPLOYEE_UPDATE = 'rh.employee.update';
    public const RH_MUTATION_CREATE = 'rh.mutation.create';
    public const RH_EXIT_MANAGE = 'rh.exit.manage';
    public const RH_HISTORY_CREATE = 'rh.history.create';

    public static function requirements(string $operation): array
    {
        return match ($operation) {
            self::RH_EMPLOYEE_VIEW => [
                PermissionEntityRegistry::RH_EMPLOYEES => PermissionAction::VIEW,
            ],
            self::RH_EMPLOYEE_CREATE => [
                PermissionEntityRegistry::RH_EMPLOYEES => PermissionAction::CREATE,
                PermissionEntityRegistry::RH_EMPLOYEE_HISTORY => PermissionAction::CREATE,
            ],
            self::RH_EMPLOYEE_UPDATE => [
                PermissionEntityRegistry::RH_EMPLOYEES => PermissionAction::UPDATE,
            ],
            self::RH_MUTATION_CREATE => [
                PermissionEntityRegistry::RH_EMPLOYEES => PermissionAction::UPDATE,
                PermissionEntityRegistry::RH_EMPLOYEE_MUTATIONS => PermissionAction::CREATE,
                PermissionEntityRegistry::RH_EMPLOYEE_HISTORY => PermissionAction::CREATE,
            ],
            self::RH_EXIT_MANAGE => [
                PermissionEntityRegistry::RH_EMPLOYEES => PermissionAction::UPDATE,
                PermissionEntityRegistry::RH_EMPLOYEE_HISTORY => PermissionAction::CREATE,
            ],
            self::RH_HISTORY_CREATE => [
                PermissionEntityRegistry::RH_EMPLOYEES => PermissionAction::VIEW,
                PermissionEntityRegistry::RH_EMPLOYEE_HISTORY => PermissionAction::CREATE,
            ],
            default => throw new InvalidArgumentException('Politique de permission inconnue : ' . $operation),
        };
    }
}
