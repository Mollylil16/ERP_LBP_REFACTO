<?php

namespace App\Modules\Rh\Repositories;

use App\Models\Database;
use PDO;

class RhRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function fetchUsers(array $filters = []): array
    {
        $sql = "
            SELECT
                u.id,
                u.username,
                u.fullname,
                u.email,
                u.phone,
                u.code_acces,
                u.\"isActive\" AS is_active,
                u.must_change_password,
                u.agence_selected,
                u.id_role,
                u.id_agence,
                a.nom_agence,
                a.code_agence,
                r.nom_role
            FROM lbp_users u
            LEFT JOIN lbp_agences a ON u.id_agence = a.id
            LEFT JOIN lbp_roles r ON u.id_role = r.id
        ";

        $clauses = [];
        $params = [];

        if (isset($filters['search']) && $filters['search'] !== '') {
            $clauses[] = '(u.username ILIKE :search OR u.fullname ILIKE :search OR u.email ILIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }

        if (isset($filters['role_id'])) {
            $clauses[] = 'u.id_role = :role_id';
            $params['role_id'] = (int) $filters['role_id'];
        }

        if (isset($filters['agence_id'])) {
            $clauses[] = 'u.id_agence = :agence_id';
            $params['agence_id'] = (int) $filters['agence_id'];
        }

        if (isset($filters['active'])) {
            $active = filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($active !== null) {
                $clauses[] = 'u.\"isActive\" = :active';
                $params['active'] = $active ? 't' : 'f';
            }
        }

        if (!empty($clauses)) {
            $sql .= ' WHERE ' . implode(' AND ', $clauses);
        }

        $sql .= ' ORDER BY u.id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map([$this, 'normalizeUserRow'], $rows);
    }

    public function fetchUserById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                u.id,
                u.username,
                u.fullname,
                u.email,
                u.phone,
                u.code_acces,
                u.\"isActive\" AS is_active,
                u.must_change_password,
                u.agence_selected,
                u.id_role,
                u.id_agence,
                a.nom_agence,
                a.code_agence,
                r.nom_role
            FROM lbp_users u
            LEFT JOIN lbp_agences a ON u.id_agence = a.id
            LEFT JOIN lbp_roles r ON u.id_role = r.id
            WHERE u.id = :id
            LIMIT 1"
        );

        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user === false ? null : $this->normalizeUserRow($user);
    }

    public function fetchCredentialsById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, password FROM lbp_users WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user === false ? null : $user;
    }

    public function createUser(array $data): array
    {
        $allowedFields = [
            'username',
            'fullname',
            'email',
            'phone',
            'code_acces',
            'isActive',
            'must_change_password',
            'agence_selected',
            'id_role',
            'id_agence',
            'password',
            'password_plain',
        ];

        $columns = [];
        $placeholders = [];
        $params = [];

        foreach ($data as $field => $value) {
            if (!in_array($field, $allowedFields, true)) {
                continue;
            }

            $columns[] = $field;
            $placeholders[] = ':' . $field;
            $params[$field] = $value;
        }

        $sql = sprintf(
            'INSERT INTO lbp_users (%s) VALUES (%s) RETURNING id, username, fullname, email, phone, code_acces, "isActive" AS is_active, must_change_password, agence_selected, id_role, id_agence',
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user === false ? [] : $this->normalizeUserRow($user);
    }

    public function updateUser(int $id, array $data): ?array
    {
        $allowedFields = [
            'username',
            'fullname',
            'email',
            'phone',
            'code_acces',
            'isActive',
            'must_change_password',
            'agence_selected',
            'id_role',
            'id_agence',
            'password',
            'password_plain',
        ];

        $sets = [];
        $params = ['id' => $id];

        foreach ($data as $field => $value) {
            if (!in_array($field, $allowedFields, true)) {
                continue;
            }

            if ($field === 'nom_complet') {
                $field = 'fullname';
            }

            $sets[] = sprintf('%s = :%s', $field, $field);
            $params[$field] = $value;
        }

        if (empty($sets)) {
            return $this->fetchUserById($id);
        }

        $sql = sprintf(
            'UPDATE lbp_users SET %s, updated_at = NOW() WHERE id = :id RETURNING id, username, fullname, email, phone, code_acces, "isActive" AS is_active, must_change_password, agence_selected, id_role, id_agence',
            implode(', ', $sets)
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user === false ? null : $this->normalizeUserRow($user);
    }

    public function toggleUserActive(int $id): ?array
    {
        $user = $this->fetchUserById($id);
        if ($user === null) {
            return null;
        }

        $newStatus = !$user['is_active'];

        return $this->updateUser($id, ['isActive' => $newStatus]);
    }

    public function deactivateUser(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE lbp_users SET "isActive" = false, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    public function listRoles(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, nom_role, description FROM lbp_roles ORDER BY nom_role'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listAgences(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, nom_agence, code_agence, adresse, telephone, email FROM lbp_agences ORDER BY nom_agence'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listPermissions(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, code_acces, nom_permission, description FROM lbp_permissions ORDER BY nom_permission'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findRoleById(int $roleId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, nom_role, description FROM lbp_roles WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $roleId]);

        $role = $stmt->fetch(PDO::FETCH_ASSOC);

        return $role === false ? null : $role;
    }

    public function findAgenceById(int $agenceId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, nom_agence, code_agence, adresse, telephone, email FROM lbp_agences WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $agenceId]);

        $agence = $stmt->fetch(PDO::FETCH_ASSOC);

        return $agence === false ? null : $agence;
    }

    public function logUserLocation(int $userId, int $agenceId, float $lat, float $lng): void
    {
        // On récupère l'IP du client depuis les variables globales (HTTP)
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;

        $stmt = $this->pdo->prepare(
            'INSERT INTO lbp_user_locations_log (id_user, agence_id_session, latitude, longitude, ip_address) 
             VALUES (:id_user, :agence_id_session, :latitude, :longitude, :ip_address)'
        );
        
        $stmt->execute([
            'id_user' => $userId,
            'agence_id_session' => $agenceId,
            'latitude' => $lat,
            'longitude' => $lng,
            'ip_address' => $ip
        ]);
    }

    private function normalizeUserRow(array $user): array
    {
        $user['is_active'] = $this->normalizeBoolValue($user['is_active'] ?? false);
        $user['must_change_password'] = $this->normalizeBoolValue($user['must_change_password'] ?? false);
        $user['agence_selected'] = $this->normalizeBoolValue($user['agence_selected'] ?? false);
        $user['id_role'] = isset($user['id_role']) ? (int) $user['id_role'] : null;
        $user['id_agence'] = isset($user['id_agence']) ? (int) $user['id_agence'] : null;
        $user['code_acces'] = isset($user['code_acces']) ? (int) $user['code_acces'] : null;

        return $user;
    }

    private function normalizeBoolValue(mixed $value): bool
    {
        return $value === true || $value === 't' || $value === '1' || $value === 1 || $value === 'true';
    }
}
