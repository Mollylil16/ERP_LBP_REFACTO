<?php

declare(strict_types=1);

namespace App\View\Pages\Rh;

use App\Helpers\View;

final class PersonnelShowPage
{
    /** @var array<string,mixed> */
    public readonly array $employee;
    /** @var array<int,array<string,mixed>> */
    public readonly array $history;
    /** @var array<int,array<string,mixed>> */
    public readonly array $mutations;
    /** @var array<int,array<string,mixed>> */
    public readonly array $documents;
    /** @var array<string,mixed> */
    public readonly array $restrictedTables;
    /** @var array<string,mixed> */
    public readonly array $details;
    /** @var array<int,array{label:string,href:string,variant:string}> */
    public readonly array $headerActions;
    /** @var array<int,array{label:string,key?:string,render?:callable}> */
    public readonly array $mutationColumns;
    public readonly int $employeeId;
    public readonly bool $canViewHistory;
    public readonly bool $canAddHistory;

    /**
     * @param array<string,mixed> $dossier
     * @param array{update?:bool,mutate?:bool,exit?:bool,viewHistory?:bool,addHistory?:bool} $permissions
     */
    public function __construct(array $dossier, array $permissions = [])
    {
        $this->employee = is_array($dossier['employee'] ?? null) ? $dossier['employee'] : [];
        $this->history = is_array($dossier['history'] ?? null) ? $dossier['history'] : [];
        $this->mutations = is_array($dossier['mutations'] ?? null) ? $dossier['mutations'] : [];
        $this->documents = is_array($dossier['documents'] ?? null) ? $dossier['documents'] : [];
        $this->restrictedTables = is_array($dossier['restrictedTables'] ?? null)
            ? $dossier['restrictedTables']
            : [];
        $this->employeeId = (int) ($this->employee['id'] ?? 0);
        $this->canViewHistory = (bool) ($permissions['viewHistory'] ?? false);
        $this->canAddHistory = (bool) ($permissions['addHistory'] ?? false);
        $this->details = $this->buildDetails();
        $this->headerActions = $this->buildActions($permissions);
        $this->mutationColumns = $this->buildMutationColumns();
    }

    public function date(?string $value): string
    {
        return $value ? date('d/m/Y', strtotime($value)) : 'Non renseignee';
    }

    /** @return array<string,mixed> */
    private function buildDetails(): array
    {
        return [
            'Matricule' => $this->employee['employee_number'] ?: 'Non renseigne',
            'E-mail' => $this->employee['email'] ?: 'Non renseigne',
            'Telephone' => $this->employee['phone'] ?: 'Non renseigne',
            'Service' => $this->employee['service_name'],
            'Fonction' => $this->employee['function_name'],
            'Statut' => $this->employee['status_name'],
            'Site' => $this->employee['site'] ?: 'Non renseigne',
            'Recrutement' => $this->date($this->employee['hire_date']),
            'Prise de service' => $this->date($this->employee['start_date']),
            'CNI' => $this->employee['cni_number'] ?: 'Non renseigne',
            'CNPS' => $this->employee['cnps_number'] ?: 'Non renseigne',
            'Contact urgence' => trim(
                ($this->employee['emergency_contact_name'] ?: '') . ' '
                . ($this->employee['emergency_contact_phone'] ?: '')
            ) ?: 'Non renseigne',
        ];
    }

    /** @return array<int,array{label:string,href:string,variant:string}> */
    private function buildActions(array $permissions): array
    {
        $actions = [];
        if (!empty($permissions['update'])) {
            $actions[] = [
                'label' => 'Modifier',
                'href' => 'rh/personnel/' . $this->employeeId . '/modifier',
                'variant' => 'accent',
            ];
        }
        if (!empty($permissions['mutate'])) {
            $actions[] = [
                'label' => 'Mutation',
                'href' => 'rh/personnel/' . $this->employeeId . '/mutation',
                'variant' => 'secondary',
            ];
        }
        if (!empty($permissions['exit'])) {
            $actions[] = [
                'label' => 'Sortie / reintegration',
                'href' => 'rh/personnel/' . $this->employeeId . '/sortie',
                'variant' => 'secondary',
            ];
        }

        return $actions;
    }

    /** @return array<int,array{label:string,key?:string,render?:callable}> */
    private function buildMutationColumns(): array
    {
        return [
            ['label' => 'Date', 'render' => fn(array $row): string =>
                View::e($this->date($row['effective_date']))],
            ['label' => 'Service', 'render' => static fn(array $row): string => View::e(
                ($row['previous_service_name'] ?: '-') . ' -> ' . ($row['new_service_name'] ?: '-')
            )],
            ['label' => 'Fonction', 'render' => static fn(array $row): string => View::e(
                ($row['previous_function_name'] ?: '-') . ' -> ' . ($row['new_function_name'] ?: '-')
            )],
            ['label' => 'Statut', 'render' => static fn(array $row): string => View::e(
                ($row['previous_status_name'] ?: '-') . ' -> ' . ($row['new_status_name'] ?: '-')
            )],
            ['label' => 'Site', 'render' => static fn(array $row): string => View::e(
                ($row['previous_site'] ?: '-') . ' -> ' . ($row['new_site'] ?: '-')
            )],
            ['label' => 'Motif', 'key' => 'reason'],
        ];
    }
}
