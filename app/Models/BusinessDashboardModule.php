<?php

namespace App\Models;

final class BusinessDashboardModule
{
    /**
     * @param array<int, array<string, string>> $kpis
     * @param array<int, array<string, string>> $actions
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $label,
        public readonly string $code,
        public readonly string $iconKey,
        public readonly string $accent,
        public readonly string $accent2,
        public readonly string $gradient,
        public readonly string $description,
        public readonly array $kpis,
        public readonly array $actions,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['slug'],
            (string) $data['label'],
            (string) $data['code'],
            (string) $data['iconKey'],
            (string) $data['accent'],
            (string) $data['accent2'],
            (string) $data['gradient'],
            (string) $data['description'],
            $data['kpis'] ?? [],
            $data['actions'] ?? [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'label' => $this->label,
            'code' => $this->code,
            'iconKey' => $this->iconKey,
            'accent' => $this->accent,
            'accent2' => $this->accent2,
            'gradient' => $this->gradient,
            'description' => $this->description,
            'kpis' => $this->kpis,
            'actions' => $this->actions,
        ];
    }
}
