<?php

declare(strict_types=1);

namespace App\Services;

interface ModuleDashboardContract
{
    /**
     * @return array<string,mixed>
     */
    public function dashboard(): array;
}
