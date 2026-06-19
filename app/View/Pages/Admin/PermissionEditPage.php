<?php

declare(strict_types=1);

namespace App\View\Pages\Admin;

use App\Models\User;

final class PermissionEditPage
{
    /** @param array<int,array<string,mixed>> $permissions */
    public function __construct(
        public readonly User $user,
        public readonly array $permissions,
    ) {
    }
}
