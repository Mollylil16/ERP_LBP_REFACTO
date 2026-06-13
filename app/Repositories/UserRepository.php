<?php

namespace App\Repositories;

use App\Models\User;
use PDO;

/**
 * Gère les accès base de données liés aux utilisateurs.
 *
 * Toutes les requêtes SQL concernant la table users doivent rester ici.
 */
class UserRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Recherche un utilisateur par son adresse email.
     */
    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM users
            WHERE email = :email
            LIMIT 1
        ");

        $stmt->execute([
            'email' => $email,
        ]);

        $row = $stmt->fetch();

        return $row ? $this->mapToUser($row) : null;
    }

    /**
     * Recherche un utilisateur par son identifiant.
     */
    public function findById(int $id): ?User
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM users
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id,
        ]);

        $row = $stmt->fetch();

        return $row ? $this->mapToUser($row) : null;
    }

    /**
     * Recherche un utilisateur par identifiant (email ou nom complet).
     */
    public function findByIdentifier(string $identifier): ?User
    {
        $stmt = $this->pdo->prepare(<<<SQL
            SELECT *
            FROM users
            WHERE LOWER(email) = LOWER(:identifier)
               OR LOWER(full_name) = LOWER(:name)
            LIMIT 1
            SQL);

        $stmt->execute([
            'identifier' => trim($identifier),
            'name' => trim($identifier),
        ]);

        $row = $stmt->fetch();

        return $row ? $this->mapToUser($row) : null;
    }

    /**
     * Crée un nouvel utilisateur.
     */
    public function create(User $user): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO users (
                full_name,
                email,
                phone,
                password_hash,
                status,
                created_at
            ) VALUES (
                :full_name,
                :email,
                :phone,
                :password_hash,
                :status,
                NOW()
            )
        ");

        $stmt->execute([
            'full_name' => $user->fullName,
            'email' => $user->email,
            'phone' => $user->phone,
            'password_hash' => $user->passwordHash,
            'status' => $user->status,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Transforme une ligne SQL en objet User.
     */
    private function mapToUser(array $row): User
    {
        return new User(
            id: (int) $row['id'],
            fullName: (string) $row['full_name'],
            email: (string) $row['email'],
            phone: $row['phone'] ?? null,
            passwordHash: (string) $row['password_hash'],
            status: (string) $row['status'],
            createdAt: $row['created_at'] ?? null,
            updatedAt: $row['updated_at'] ?? null,
        );
    }
}
