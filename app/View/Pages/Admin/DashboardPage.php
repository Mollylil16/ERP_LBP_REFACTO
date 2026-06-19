<?php

declare(strict_types=1);

namespace App\View\Pages\Admin;

final class DashboardPage
{
    /** @var array<string,int> */
    public readonly array $statistics;
    /** @var array<int,mixed> */
    public readonly array $entities;
    public readonly int $grantedPermissions;

    /** @param array<string,mixed> $data */
    public function __construct(array $data)
    {
        $this->statistics = is_array($data['statistics'] ?? null) ? $data['statistics'] : [];
        $this->entities = is_array($data['entities'] ?? null) ? $data['entities'] : [];
        $this->grantedPermissions = (int) ($data['grantedPermissions'] ?? 0);
    }
}
