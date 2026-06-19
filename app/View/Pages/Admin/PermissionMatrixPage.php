<?php

declare(strict_types=1);

namespace App\View\Pages\Admin;

final class PermissionMatrixPage
{
    /**
     * @param array<int,mixed> $entities
     * @param array<int,array<string,mixed>> $users
     */
    public function __construct(
        public readonly array $entities,
        public readonly array $users,
    ) {
    }
}
