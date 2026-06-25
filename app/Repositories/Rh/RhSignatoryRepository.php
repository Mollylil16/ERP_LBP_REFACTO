<?php

declare(strict_types=1);

namespace App\Repositories\Rh;

use PDO;

final class RhSignatoryRepository
{
    public function __construct(private PDO $pdo) {}

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        return $this->pdo->query("
            SELECT s.id, s.employee_id, s.role, s.title, s.is_active, s.document_types, e.full_name AS employee_name
            FROM rh_signatories s
            INNER JOIN rh_employees e ON e.id = s.employee_id
            ORDER BY s.is_active DESC, e.full_name ASC
        ")->fetchAll() ?: [];
    }

    /** @return array<int,array<string,mixed>> */
    public function getActiveEmployees(): array
    {
        return $this->pdo->query("
            SELECT id, full_name, employee_number
            FROM rh_employees
            WHERE is_active = 1
            ORDER BY full_name ASC
        ")->fetchAll() ?: [];
    }

    public function save(array $data): void
    {
        $id = (int) ($data['id'] ?? 0);
        $employeeId = (int) ($data['employee_id'] ?? 0);
        $role = trim((string) ($data['role'] ?? ''));
        $title = trim((string) ($data['title'] ?? ''));
        $docs = isset($data['document_types']) && is_array($data['document_types'])
            ? implode(',', array_map('strval', $data['document_types']))
            : '';

        if ($employeeId <= 0 || $role === '' || $title === '') {
            throw new \RuntimeException('L\'employe, le role et le titre sont obligatoires.');
        }

        if ($id > 0) {
            $stmt = $this->pdo->prepare("
                UPDATE rh_signatories
                SET employee_id = :employee_id, role = :role, title = :title, document_types = :docs, updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                'employee_id' => $employeeId,
                'role' => $role,
                'title' => $title,
                'docs' => $docs,
                'id' => $id,
            ]);
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO rh_signatories (employee_id, role, title, is_active, document_types, created_at)
                VALUES (:employee_id, :role, :title, 1, :docs, NOW())
            ");
            $stmt->execute([
                'employee_id' => $employeeId,
                'role' => $role,
                'title' => $title,
                'docs' => $docs,
            ]);
        }
    }

    public function toggle(int $id): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE rh_signatories
            SET is_active = IF(is_active = 1, 0, 1), updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);
    }
}
