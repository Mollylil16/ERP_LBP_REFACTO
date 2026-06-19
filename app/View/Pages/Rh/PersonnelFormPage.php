<?php

declare(strict_types=1);

namespace App\View\Pages\Rh;

final class PersonnelFormPage
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

    public function __construct(
        array $employee,
        array $options,
        array $restrictedTables,
        public readonly string $title,
        public readonly string $action,
        public readonly string $submitLabel,
    ) {
        $this->employee = $employee;
        $this->restrictedTables = $restrictedTables;
        $this->services = self::idOptions($options['services'] ?? [], 'Non renseigne');
        $this->functions = self::idOptions($options['functions'] ?? [], 'Non renseignee');
        $this->statuses = self::idOptions($options['statuses'] ?? [], 'Non renseigne');
        $this->sites = array_merge(
            [['value' => '', 'label' => 'Non renseigne']],
            array_map(static fn(array $row): array => [
                'value' => (string) ($row['name'] ?? ''),
                'label' => (string) ($row['name'] ?? ''),
            ], is_array($options['sites'] ?? null) ? $options['sites'] : [])
        );
    }

    public function value(string $key, mixed $default = ''): mixed
    {
        return $this->employee[$key] ?? $default;
    }

    /** @return array<int,array{value:string,label:string}> */
    private static function idOptions(mixed $rows, string $emptyLabel): array
    {
        $rows = is_array($rows) ? $rows : [];
        return array_merge(
            [['value' => '', 'label' => $emptyLabel]],
            array_map(static fn(array $row): array => [
                'value' => (string) ($row['id'] ?? ''),
                'label' => (string) ($row['name'] ?? ''),
            ], $rows)
        );
    }
}
