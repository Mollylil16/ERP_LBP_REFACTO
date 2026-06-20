<?php

declare(strict_types=1);

namespace App\View\Pages\Portal;

final class SelectionPage
{
    /** @param array<int,array<string,mixed>> $modules */
    public function __construct(
        public readonly string $userName,
        public readonly array $modules,
        public readonly string $title = 'Sélection portail',
    ) {
    }

    /** @return array<int,array{value:string,label:string}> */
    public function moduleOptions(): array
    {
        return array_map(
            static fn(array $module): array => [
                'value' => (string) ($module['key'] ?? ''),
                'label' => trim((string) ($module['label'] ?? 'Module')
                    . ' · ' . (string) ($module['code'] ?? '')),
            ],
            $this->modules
        );
    }
}
