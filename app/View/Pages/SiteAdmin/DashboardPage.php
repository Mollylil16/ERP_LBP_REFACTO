<?php

declare(strict_types=1);

namespace App\View\Pages\SiteAdmin;

final class DashboardPage
{
    /** @param array<string,mixed> $module */
    public function __construct(public readonly array $module)
    {
    }
}
