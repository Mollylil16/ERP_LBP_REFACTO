<?php

namespace App\Repositories\Admin;

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
                is_admin,
                rh_employee_id,
                created_at
            ) VALUES (
                :full_name,
                :email,
                :phone,
                :password_hash,
                :status,
                :is_admin,
                :rh_employee_id,
                NOW()
            )
        ");

        $stmt->execute([
            'full_name' => $user->fullName,
            'email' => $user->email,
            'phone' => $user->phone,
            'password_hash' => $user->passwordHash,
            'status' => $user->status,
            'is_admin' => $user->isAdmin ? 1 : 0,
            'rh_employee_id' => $user->rhEmployeeId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function paginate(array $filters, int $page = 1, int $perPage = 15): array
    {
        $page = max(1, $page);
        $conditions = [];
        $params = [];

        if (($filters['q'] ?? '') !== '') {
            $conditions[] = '(full_name LIKE :q OR email LIKE :q OR phone LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }
        if (($filters['status'] ?? '') !== '') {
            $conditions[] = 'status = :status';
            $params['status'] = $filters['status'];
        }
        if (($filters['profile'] ?? '') === 'admin') {
            $conditions[] = 'is_admin = 1';
        } elseif (($filters['profile'] ?? '') === 'user') {
            $conditions[] = 'is_admin = 0';
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $count = $this->pdo->prepare("SELECT COUNT(*) FROM users {$where}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $stmt = $this->pdo->prepare("
            SELECT *
            FROM users
            {$where}
            ORDER BY is_admin DESC, full_name ASC
            LIMIT :limit OFFSET :offset
        ");
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => array_map(fn(array $row): User => $this->mapToUser($row), $stmt->fetchAll() ?: []),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function statistics(): array
    {
        $row = $this->pdo->query("
            SELECT
                COUNT(*) AS total,
                SUM(status = 'active') AS active,
                SUM(status <> 'active') AS restricted,
                SUM(is_admin = 1) AS administrators
            FROM users
        ")->fetch();

        return [
            'total' => (int) ($row['total'] ?? 0),
            'active' => (int) ($row['active'] ?? 0),
            'restricted' => (int) ($row['restricted'] ?? 0),
            'administrators' => (int) ($row['administrators'] ?? 0),
        ];
    }

    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE LOWER(email) = LOWER(:email)';
        $params = ['email' => $email];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function updateFromAdmin(int $id, array $data): void
    {
        $passwordSql = '';
        $params = [
            'id' => $id,
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'status' => $data['status'],
            'is_admin' => $data['is_admin'] ? 1 : 0,
        ];
        if ($data['password_hash'] !== null) {
            $passwordSql = ', password_hash = :password_hash';
            $params['password_hash'] = $data['password_hash'];
        }

        $stmt = $this->pdo->prepare("
            UPDATE users SET
                full_name = :full_name,
                email = :email,
                phone = :phone,
                status = :status,
                is_admin = :is_admin,
                updated_at = NOW()
                {$passwordSql}
            WHERE id = :id
        ");
        $stmt->execute($params);
    }

    public function updatePassword(int $userId, string $newPasswordHash): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['hash' => $newPasswordHash, 'id' => $userId]);
    }

    public function setStatus(int $id, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET status = :status, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    public function promoteToAdmin(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET is_admin = 1, status = :status WHERE id = :id');
        $stmt->execute(['status' => 'active', 'id' => $id]);
    }

    /**
     * Transforme une ligne SQL en objet User.
     */
    private function mapToUser(array $row): User
    {
        $userId = (int) $row['id'];
        $roles = [];
        try {
            $stmt = $this->pdo->prepare("SELECT role FROM lbp_user_roles WHERE user_id = :user_id");
            $stmt->execute(['user_id' => $userId]);
            $roles = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (\Exception $e) {}

        return new User(
            id: $userId,
            fullName: (string) $row['full_name'],
            email: (string) $row['email'],
            phone: $row['phone'] ?? null,
            passwordHash: (string) $row['password_hash'],
            status: (string) $row['status'],
            isAdmin: (bool) ($row['is_admin'] ?? false),
            rhEmployeeId: isset($row['rh_employee_id']) ? (int) $row['rh_employee_id'] : null,
            agenceId: isset($row['agence_id']) ? (int) $row['agence_id'] : null,
            zoneRegionaleId: isset($row['zone_regionale_id']) ? (int) $row['zone_regionale_id'] : null,
            roles: $roles,
            createdAt: $row['created_at'] ?? null,
            updatedAt: $row['updated_at'] ?? null,
        );
    }
}
