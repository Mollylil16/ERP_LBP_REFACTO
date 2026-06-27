<?php

declare(strict_types=1);

namespace App\Repositories\Rh;

use PDO;

final class RhHolidayRepository
{
    public function __construct(private PDO $pdo) {}

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        return $this->pdo->query("
            SELECT id, name, holiday_date, is_recurring, year, is_active
            FROM rh_holidays
            ORDER BY holiday_date DESC
        ")->fetchAll() ?: [];
    }

    public function save(array $data): void
    {
        $id = (int) ($data['id'] ?? 0);
        $name = trim((string) ($data['name'] ?? ''));
        $date = trim((string) ($data['holiday_date'] ?? ''));
        $isRecurring = isset($data['is_recurring']) ? 1 : 0;
        
        if ($date === '') {
            throw new \RuntimeException('La date est obligatoire.');
        }

        if ($name === '') {
            $name = 'Jour ferie';
        }

        $year = $isRecurring ? null : (int) date('Y', strtotime($date));

        // Check if a holiday with this date already exists to perform update instead of insert
        if ($id === 0) {
            $stmt = $this->pdo->prepare("SELECT id FROM rh_holidays WHERE holiday_date = :date LIMIT 1");
            $stmt->execute(['date' => $date]);
            $existingId = $stmt->fetchColumn();
            if ($existingId !== false) {
                $id = (int)$existingId;
            }
        }

        if ($id > 0) {
            $stmt = $this->pdo->prepare("
                UPDATE rh_holidays
                SET name = :name, holiday_date = :date, is_recurring = :is_recurring, year = :year, updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                'name' => $name,
                'date' => $date,
                'is_recurring' => $isRecurring,
                'year' => $year,
                'id' => $id,
            ]);
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO rh_holidays (name, holiday_date, is_recurring, year, is_active, created_at)
                VALUES (:name, :date, :is_recurring, :year, 1, NOW())
            ");
            $stmt->execute([
                'name' => $name,
                'date' => $date,
                'is_recurring' => $isRecurring,
                'year' => $year,
            ]);
        }
    }

    public function toggle(int $id): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE rh_holidays
            SET is_active = IF(is_active = 1, 0, 1), updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);
    }
}
