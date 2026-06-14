<?php

namespace App\Models;

/**
 * Entité métier sur laquelle des permissions CRUD peuvent être accordées.
 */
class PermissionEntity
{
    public function __construct(
        public ?int $id,
        public string $code,
        public string $module,
        public string $name,
        public ?string $description = null,
        public int $sortOrder = 0,
        public bool $isActive = true,
    ) {}
}
