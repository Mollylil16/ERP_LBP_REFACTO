<?php

declare(strict_types=1);

namespace App\View\Pages\Rh;

final class PersonnelExitPage
{
    /** @var array<string,mixed> */
    public readonly array $employee;
    /** @var array<string,mixed> */
    public readonly array $restrictedTables;
    /** @var array<int,array{value:string,label:string}> */
    public readonly array $exitReasons;
    public readonly int $employeeId;
    public readonly bool $exited;

    /** @param array<string,mixed> $dossier */
    public function __construct(array $dossier)
    {
        $this->employee = is_array($dossier['employee'] ?? null) ? $dossier['employee'] : [];
        $options = is_array($dossier['options'] ?? null) ? $dossier['options'] : [];
        $this->restrictedTables = is_array($dossier['restrictedTables'] ?? null)
            ? $dossier['restrictedTables']
            : [];
        $this->employeeId = (int) ($this->employee['id'] ?? 0);
        $this->exited = !empty($this->employee['exit_date']);
        $this->exitReasons = array_merge(
            [['value' => '', 'label' => 'Non renseigne']],
            array_map(static fn(array $row): array => [
                'value' => (string) ($row['id'] ?? ''),
                'label' => (string) ($row['name'] ?? ''),
            ], is_array($options['exitReasons'] ?? null) ? $options['exitReasons'] : [])
        );
    }
}
