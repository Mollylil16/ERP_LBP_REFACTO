<?php

namespace App\Models;

/**
 * Représente un utilisateur inscrit sur la plateforme.
 *
 * Ce model transporte uniquement les données utilisateur.
 * La logique d'inscription, de connexion et de session doit rester
 * dans AuthService.
 */
class User
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
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {}
}
