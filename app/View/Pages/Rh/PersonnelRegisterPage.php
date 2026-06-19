<?php

declare(strict_types=1);

namespace App\View\Pages\Rh;

use App\Helpers\View;

final class PersonnelRegisterPage
{
    /** @var array<int,array<string,mixed>> */
    public readonly array $rows;
    /** @var array<string,mixed> */
    public readonly array $restrictedTables;
    /** @var array<int,array{label:string,key?:string,render?:callable}> */
    public readonly array $columns;

    /** @param array<string,mixed> $data */
    public function __construct(array $data, string $key)
    {
        $this->rows = is_array($data[$key] ?? null) ? $data[$key] : [];
        $this->restrictedTables = is_array($data['restrictedTables'] ?? null)
            ? $data['restrictedTables']
            : [];
        $this->columns = $key === 'movements'
            ? $this->movementColumns()
            : $this->mutationColumns();
    }

    public function date(?string $value): string
    {
        return $value ? date('d/m/Y', strtotime($value)) : 'Non renseignee';
    }

    /** @return array<int,array{label:string,key?:string,render?:callable}> */
    private function movementColumns(): array
    {
        return [
            ['label' => 'Date', 'render' => fn(array $row): string =>
                View::e($this->date($row['event_date']))],
            ['label' => 'Mouvement', 'render' => static function (array $row): string {
                $tone = $row['event_type'] === 'sortie' ? 'warning' : 'ok';
                $labels = ['integration' => 'Entree', 'sortie' => 'Sortie', 'reintegration' => 'Reintegration'];
                return '<span class="finea-status-badge finea-status-badge--' . $tone . '">'
                    . View::e($labels[$row['event_type']] ?? $row['event_type']) . '</span>';
            }],
            ['label' => 'Collaborateur', 'render' => static fn(array $row): string =>
                '<a href="' . View::url('rh/personnel/' . (int) $row['employee_id']) . '"><strong>'
                . View::e($row['full_name']) . '</strong></a><small class="rh-table-subtitle">'
                . View::e($row['employee_number']) . '</small>'],
            ['label' => 'Titre', 'key' => 'title'],
            ['label' => 'Details', 'key' => 'description'],
            ['label' => 'Situation actuelle', 'render' => static fn(array $row): string =>
                (int) $row['is_active'] === 1 ? 'En poste' : 'Sorti'],
        ];
    }

    /** @return array<int,array{label:string,key?:string,render?:callable}> */
    private function mutationColumns(): array
    {
        return [
            ['label' => 'Date', 'render' => fn(array $row): string =>
                View::e($this->date($row['effective_date']))],
            ['label' => 'Collaborateur', 'render' => static function (array $row): string {
                $name = '<strong>' . View::e($row['full_name']) . '</strong>';
                if ($row['employee_id']) {
                    $name = '<a href="' . View::url('rh/personnel/' . (int) $row['employee_id']) . '">' . $name . '</a>';
                }
                return $name . '<small class="rh-table-subtitle">'
                    . View::e($row['employee_number']) . '</small>';
            }],
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
