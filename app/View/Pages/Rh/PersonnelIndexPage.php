<?php

declare(strict_types=1);

namespace App\View\Pages\Rh;

use App\Helpers\View;

final class PersonnelIndexPage
{
    /** @var array<string,mixed> */
    public readonly array $filters;

    /** @var array<string,array<int,array{value:string,label:string}>> */
    public readonly array $filterOptions;

    /** @var array<int,array{employee:array<string,mixed>,actions:array<int,array{label:string,href:string,variant?:string}>}> */
    public readonly array $employees;

    /** @var array<int,array{number:int,href:string,active:bool}> */
    public readonly array $pagination;

    /** @var array<string,mixed> */
    public readonly array $restrictedTables;

    public readonly int $total;
    public readonly int $currentPage;
    public readonly int $totalPages;
    /** @var array{total:int,active:int,inactive:int} */
    public readonly array $stats;
    public readonly bool $canCreate;

    /**
     * @param array<string,mixed> $data
     * @param array{view?:bool,create?:bool,update?:bool,mutate?:bool} $permissions
     */
    public function __construct(array $data, array $permissions = [])
    {
        $this->filters = is_array($data['filters'] ?? null) ? $data['filters'] : [];
        $pagination = is_array($data['pagination'] ?? null) ? $data['pagination'] : [];
        $options = is_array($data['options'] ?? null) ? $data['options'] : [];
        $this->restrictedTables = is_array($data['restrictedTables'] ?? null)
            ? $data['restrictedTables']
            : [];

        $items = is_array($pagination['items'] ?? null) ? $pagination['items'] : [];
        $this->total = (int) ($pagination['total'] ?? 0);
        $currentPage = max(1, (int) ($pagination['page'] ?? 1));
        $totalPages = max(1, (int) ($pagination['totalPages'] ?? 1));
        $this->currentPage = $currentPage;
        $this->totalPages = $totalPages;
        $this->stats = is_array($data['stats'] ?? null) ? $data['stats'] : ['total' => 0, 'active' => 0, 'inactive' => 0];

        $this->filterOptions = [
            'services' => self::options($options['services'] ?? []),
            'functions' => self::options($options['functions'] ?? []),
            'statuses' => self::options($options['statuses'] ?? []),
            'sites' => array_merge(
                [['value' => '', 'label' => 'Tous les sites']],
                array_map(static fn(array $row): array => [
                    'value' => (string) ($row['name'] ?? ''),
                    'label' => (string) ($row['name'] ?? ''),
                ], is_array($options['sites'] ?? null) ? $options['sites'] : [])
            ),
        ];

        $canView = (bool) ($permissions['view'] ?? false);
        $canUpdate = (bool) ($permissions['update'] ?? false);
        $canMutate = (bool) ($permissions['mutate'] ?? false);
        $this->canCreate = (bool) ($permissions['create'] ?? false);

        $this->employees = array_map(
            static function (array $employee) use ($canView, $canUpdate, $canMutate): array {
                $id = (int) ($employee['id'] ?? 0);
                $actions = [];
                if ($canView) {
                    $actions[] = [
                        'label' => 'Voir le dossier',
                        'href' => 'rh/personnel/' . $id,
                        'variant' => 'primary',
                    ];
                }
                if ($canUpdate) {
                    $actions[] = ['label' => 'Modifier', 'href' => 'rh/personnel/' . $id . '/modifier'];
                }
                if ($canMutate) {
                    $actions[] = [
                        'label' => 'Mutation',
                        'href' => 'rh/personnel/' . $id . '/mutation',
                        'variant' => 'plain',
                    ];
                }

                return ['employee' => $employee, 'actions' => $actions];
            },
            $items
        );

        $paginationLinks = [];
        for ($page = 1; $page <= $totalPages; $page++) {
            $query = http_build_query(array_filter(
                $this->filters + ['page' => $page],
                static fn(mixed $value): bool => $value !== '' && $value !== 0
            ));
            $paginationLinks[] = [
                'number' => $page,
                'href' => View::url('rh/personnel?' . $query),
                'active' => $page === $currentPage,
            ];
        }
        $this->pagination = $paginationLinks;
    }

    /** @return array<int,array{value:string,label:string}> */
    private static function options(mixed $rows): array
    {
        $rows = is_array($rows) ? $rows : [];
        return array_merge(
            [['value' => '', 'label' => 'Tous']],
            array_map(static fn(array $row): array => [
                'value' => (string) ($row['id'] ?? ''),
                'label' => (string) ($row['name'] ?? ''),
            ], $rows)
        );
    }
}
