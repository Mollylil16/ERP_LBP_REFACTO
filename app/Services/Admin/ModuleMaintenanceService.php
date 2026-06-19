<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Repositories\Admin\ModuleMaintenanceRepository;
use RuntimeException;

final class ModuleMaintenanceService
{
    public function __construct(private ModuleMaintenanceRepository $repository)
    {
    }

    /** @return array<string,array<string,mixed>> */
    public function states(): array
    {
        return $this->repository->all();
    }

    /** @return array<string,mixed> */
    public function state(string $slug): array
    {
        return $this->repository->state($this->normalizeSlug($slug));
    }

    /** @return array<string,mixed> */
    public function update(string $slug, bool $maintenance, string $reason, int $userId): array
    {
        $slug = $this->normalizeSlug($slug);
        if ($slug === 'admin') {
            throw new RuntimeException('Le module Administration ne peut pas être placé en maintenance.');
        }
        $reason = trim($reason);
        if ($maintenance && mb_strlen($reason) < 5) {
            throw new RuntimeException('Indiquez un motif de maintenance suffisamment précis.');
        }
        $this->repository->set($slug, $maintenance, $reason, $userId);
        return $this->repository->state($slug);
    }

    private function normalizeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        if ($slug === '' || preg_match('/^[a-z0-9-]+$/', $slug) !== 1) {
            throw new RuntimeException('Module invalide.');
        }
        return $slug;
    }
}
