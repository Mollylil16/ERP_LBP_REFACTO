<?php

declare(strict_types=1);

namespace App\View\Pages\Rh;

final class PersonnelMutationPage
{
    /** @var array<string,mixed> */
    public readonly array $employee;
    /** @var array<string,mixed> */
    public readonly array $restrictedTables;
    /** @var array<int,array{value:string,label:string}> */
    public readonly array $services;
    /** @var array<int,array{value:string,label:string}> */
    public readonly array $functions;
    /** @var array<int,array{value:string,label:string}> */
    public readonly array $statuses;
    /** @var array<int,array{value:string,label:string}> */
    public readonly array $sites;
    public readonly int $employeeId;

    /** @param array<string,mixed> $dossier */
    public function __construct(array $dossier)
    {
        $this->employee = is_array($dossier['employee'] ?? null) ? $dossier['employee'] : [];
        $options = is_array($dossier['options'] ?? null) ? $dossier['options'] : [];
        $this->restrictedTables = is_array($dossier['restrictedTables'] ?? null)
            ? $dossier['restrictedTables']
            : [];
        $this->employeeId = (int) ($this->employee['id'] ?? 0);
        $this->services = self::idOptions($options['services'] ?? []);
        $this->functions = self::idOptions($options['functions'] ?? []);
        $this->statuses = self::idOptions($options['statuses'] ?? []);
        $this->sites = array_merge(
            [['value' => '', 'label' => 'Conserver']],
            array_map(static fn(array $row): array => [
                'value' => (string) ($row['name'] ?? ''),
                'label' => (string) ($row['name'] ?? ''),
            ], is_array($options['sites'] ?? null) ? $options['sites'] : [])
        );
    }

    /** @return array<int,array{value:string,label:string}> */
    private static function idOptions(mixed $rows): array
    {
        $rows = is_array($rows) ? $rows : [];
        return array_merge(
            [['value' => '', 'label' => 'Conserver']],
            array_map(static fn(array $row): array => [
                'value' => (string) ($row['id'] ?? ''),
                'label' => (string) ($row['name'] ?? ''),
            ], $rows)
        );
    }
}
