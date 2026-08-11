<?php

namespace App\Models;

use ArrayAccess;

/**
 * Représente un utilisateur inscrit sur la plateforme.
 *
 * Ce model transporte uniquement les données utilisateur.
 * La logique d'inscription, de connexion et de session doit rester
 * dans AuthService.
 */
class User implements ArrayAccess
{
    public function __construct(
        public ?int $id,
        public string $fullName,
        public string $email,
        public ?string $phone,
        public string $passwordHash,
        public string $status = 'active',
        public bool $isAdmin = false,
        public ?int $rhEmployeeId = null,
        public ?int $agenceId = null,
        public ?int $zoneRegionaleId = null,
        public array $roles = [],
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {}

    public function offsetExists(mixed $offset): bool
    {
        $offsetStr = (string) $offset;
        if (property_exists($this, $offsetStr)) {
            return true;
        }
        $camel = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $offsetStr))));
        return property_exists($this, $camel);
    }

    public function offsetGet(mixed $offset): mixed
    {
        $offsetStr = (string) $offset;
        if (property_exists($this, $offsetStr)) {
            return $this->{$offsetStr};
        }
        $camel = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $offsetStr))));
        if (property_exists($this, $camel)) {
            return $this->{$camel};
        }
        return null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $offsetStr = (string) $offset;
        if (property_exists($this, $offsetStr)) {
            $this->{$offsetStr} = $value;
            return;
        }
        $camel = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $offsetStr))));
        if (property_exists($this, $camel)) {
            $this->{$camel} = $value;
        }
    }

    public function offsetUnset(mixed $offset): void {}
}
