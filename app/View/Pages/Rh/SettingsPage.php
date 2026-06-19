<?php

declare(strict_types=1);

namespace App\View\Pages\Rh;

final class SettingsPage
{
    /** @var array<string,mixed> */
    public readonly array $catalog;
    /** @var array<int,array{key:string,label:string,description:string,count:int,href:string}> */
    public readonly array $tabs;

    /** @param array<string,mixed> $catalogs */
    public function __construct(array $catalogs, public readonly string $activeCatalog)
    {
        $this->catalog = is_array($catalogs[$activeCatalog] ?? null)
            ? $catalogs[$activeCatalog]
            : [];
        $descriptions = [
            'services' => 'Organisation',
            'functions' => 'Postes',
            'statuses' => 'Contrats',
            'exit_reasons' => 'Departs',
            'document_types' => 'Dossiers',
            'sites' => 'Implantations',
        ];
        $tabs = [];
        foreach ($catalogs as $key => $item) {
            $tabs[] = [
                'key' => (string) $key,
                'label' => (string) ($item['title'] ?? ''),
                'description' => $descriptions[$key] ?? '',
                'count' => count($item['rows'] ?? []),
                'href' => 'rh/parametrage?catalog=' . rawurlencode((string) $key),
            ];
        }
        $this->tabs = $tabs;
    }
}
