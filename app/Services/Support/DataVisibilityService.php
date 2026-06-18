<?php

namespace App\Services\Support;

use App\Helpers\Auth;
use App\Security\PermissionAction;
use App\Security\PermissionEntityRegistry;

/**
 * Applique les droits de lecture avant la transmission des données aux vues.
 */
class DataVisibilityService
{
    public const HIDDEN = 'Donnée masquée';

    public function canView(string $table): bool
    {
        return Auth::can($table, PermissionAction::VIEW);
    }

    public function employeeRows(array $rows): array
    {
        if (!$this->canView(PermissionEntityRegistry::RH_EMPLOYEES)) {
            return [];
        }
        return array_map(fn(array $row): array => $this->employee($row), $rows);
    }

    public function employee(array $row): array
    {
        if (!$this->canView(PermissionEntityRegistry::RH_SERVICES)) {
            $row['service_id'] = null;
            $row['service_name'] = self::HIDDEN;
        }
        if (!$this->canView(PermissionEntityRegistry::RH_FUNCTIONS)) {
            $row['function_id'] = null;
            $row['function_name'] = self::HIDDEN;
        }
        if (!$this->canView(PermissionEntityRegistry::RH_STATUSES)) {
            $row['status_id'] = null;
            $row['status_name'] = self::HIDDEN;
        }
        if (!$this->canView(PermissionEntityRegistry::RH_EXIT_REASONS)) {
            $row['exit_reason_id'] = null;
            $row['exit_reason_name'] = self::HIDDEN;
            $row['exit_notes'] = null;
        }
        return $row;
    }

    public function options(array $options): array
    {
        if (!$this->canView(PermissionEntityRegistry::RH_SERVICES)) {
            $options['services'] = [];
        }
        if (!$this->canView(PermissionEntityRegistry::RH_FUNCTIONS)) {
            $options['functions'] = [];
        }
        if (!$this->canView(PermissionEntityRegistry::RH_STATUSES)) {
            $options['statuses'] = [];
        }
        if (!$this->canView(PermissionEntityRegistry::RH_EXIT_REASONS)) {
            $options['exitReasons'] = [];
        }
        return $options;
    }

    public function mutations(array $rows): array
    {
        if (!$this->canView(PermissionEntityRegistry::RH_EMPLOYEE_MUTATIONS)) {
            return [];
        }
        foreach ($rows as &$row) {
            if (!$this->canView(PermissionEntityRegistry::RH_EMPLOYEES)) {
                $row['full_name'] = self::HIDDEN;
                $row['employee_number'] = self::HIDDEN;
                $row['employee_id'] = null;
            }
            if (!$this->canView(PermissionEntityRegistry::RH_SERVICES)) {
                $row['previous_service_name'] = self::HIDDEN;
                $row['new_service_name'] = self::HIDDEN;
            }
            if (!$this->canView(PermissionEntityRegistry::RH_FUNCTIONS)) {
                $row['previous_function_name'] = self::HIDDEN;
                $row['new_function_name'] = self::HIDDEN;
            }
            if (!$this->canView(PermissionEntityRegistry::RH_STATUSES)) {
                $row['previous_status_name'] = self::HIDDEN;
                $row['new_status_name'] = self::HIDDEN;
            }
        }
        unset($row);
        return $rows;
    }

    public function history(array $rows): array
    {
        if (!$this->canView(PermissionEntityRegistry::RH_EMPLOYEE_HISTORY)) {
            return [];
        }
        if (!$this->canView(PermissionEntityRegistry::USERS)) {
            foreach ($rows as &$row) {
                $row['created_by_name'] = self::HIDDEN;
            }
            unset($row);
        }
        return $rows;
    }

    public function movements(array $rows): array
    {
        return $this->canView(PermissionEntityRegistry::RH_EMPLOYEE_HISTORY)
            && $this->canView(PermissionEntityRegistry::RH_EMPLOYEES)
            ? $rows
            : [];
    }

    public function restrictedTables(): array
    {
        $tables = [];
        foreach (PermissionEntityRegistry::codesForModule('Ressources humaines') as $table) {
            $tables[$table] = PermissionEntityRegistry::label($table);
        }

        return array_filter(
            $tables,
            fn(string $label, string $table): bool => !$this->canView($table),
            ARRAY_FILTER_USE_BOTH
        );
    }
}
