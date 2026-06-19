<?php

declare(strict_types=1);

namespace App\View\Pages\Admin;

use App\Models\User;

final class UserFormPage
{
    /** @var array<string,mixed>|null */
    public readonly ?array $employee;
    /** @var array<int,array<string,mixed>> */
    public readonly array $employeeOptions;
    /** @var array<int,array<string,mixed>> */
    public readonly array $permissions;
    public readonly bool $isEdit;
    public readonly bool $canSubmit;

    /**
     * @param array<string,mixed>|null $employee
     * @param array<int,array<string,mixed>> $employees
     * @param array<int,array<string,mixed>> $permissions
     */
    public function __construct(
        public readonly string $title,
        public readonly ?User $user,
        ?array $employee,
        array $employees,
        array $permissions,
        public readonly string $action,
        public readonly string $submitLabel,
    ) {
        $this->employee = $employee;
        $this->permissions = $permissions;
        $this->isEdit = $user !== null;
        $this->employeeOptions = array_merge(
            [['value' => '', 'label' => 'Sélectionner un collaborateur']],
            array_map(static fn(array $row): array => [
                'value' => (string) ($row['id'] ?? ''),
                'label' => trim((string) ($row['full_name'] ?? '')) . ' · '
                    . ((string) ($row['employee_number'] ?? '') ?: 'Sans matricule'),
                'attrs' => [
                    'data-name' => (string) ($row['full_name'] ?? ''),
                    'data-number' => (string) (($row['employee_number'] ?? '') ?: 'Non renseigné'),
                    'data-email' => (string) (($row['email'] ?? '') ?: 'Non renseigné'),
                    'data-phone' => (string) (($row['phone'] ?? '') ?: 'Non renseigné'),
                    'data-service' => (string) (($row['service_name'] ?? '') ?: 'Non renseigné'),
                    'data-function' => (string) (($row['function_name'] ?? '') ?: 'Non renseigné'),
                ],
            ], $employees)
        );
        $this->canSubmit = $this->isEdit || count($this->employeeOptions) > 1;
    }
}
