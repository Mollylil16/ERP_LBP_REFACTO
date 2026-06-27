<?php

declare(strict_types=1);

namespace App\Repositories\Rh;

use PDO;

final class RhMissionRepository
{
    public function __construct(private PDO $pdo) {}

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        return $this->pdo->query("
            SELECT m.*, e.full_name AS employee_name, e.employee_number,
                   a.full_name AS approved_by_name
            FROM rh_missions m
            INNER JOIN rh_employees e ON e.id = m.employee_id
            LEFT JOIN rh_employees a ON a.id = m.approved_by
            ORDER BY m.created_at DESC
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

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT m.*, e.full_name AS employee_name, e.employee_number,
                   a.full_name AS approved_by_name
            FROM rh_missions m
            INNER JOIN rh_employees e ON e.id = m.employee_id
            LEFT JOIN rh_employees a ON a.id = m.approved_by
            WHERE m.id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function save(array $data): void
    {
        $id = (int) ($data['id'] ?? 0);
        $employeeId = (int) ($data['employee_id'] ?? 0);
        $destination = trim((string) ($data['destination'] ?? ''));
        $startDate = trim((string) ($data['start_date'] ?? ''));
        $endDate = trim((string) ($data['end_date'] ?? ''));
        $purpose = trim((string) ($data['purpose'] ?? ''));
        $butContexte = trim((string) ($data['but_contexte'] ?? ''));
        $liaisonType = trim((string) ($data['liaison_type'] ?? ''));
        $notes = trim((string) ($data['notes'] ?? ''));
        $transport = trim((string) ($data['transport_mode'] ?? ''));
        $status = trim((string) ($data['status'] ?? 'draft'));

        if (!in_array($status, ['draft', 'submitted', 'approved', 'rejected', 'cancelled'], true)) {
            $status = 'draft';
        }

        // Parse expenses and calculate budget
        $expenses = [];
        $calculatedBudget = 0.0;
        if (isset($data['expenses']) && is_array($data['expenses'])) {
            foreach ($data['expenses'] as $item) {
                $designation = trim((string)($item['designation'] ?? ''));
                if ($designation !== '') {
                    $qte = (float)($item['qte'] ?? 0);
                    $pu = (float)($item['pu'] ?? 0);
                    $total = $qte * $pu;
                    $expenses[] = [
                        'designation' => $designation,
                        'unite' => trim((string)($item['unite'] ?? '')),
                        'qte' => $qte,
                        'pu' => $pu,
                        'total' => $total
                    ];
                    $calculatedBudget += $total;
                }
            }
        }
        $expensesJson = json_encode($expenses);
        $budget = $calculatedBudget;

        if ($employeeId <= 0 || $destination === '' || $startDate === '' || $endDate === '' || $purpose === '') {
            throw new \RuntimeException('Tous les champs obligatoires (Collaborateur, Destination, Date debut, Date fin, Objet) doivent etre renseignes.');
        }

        if ($id > 0) {
            // Keep status if not explicitly overridden
            $stmt = $this->pdo->prepare("
                UPDATE rh_missions
                SET employee_id = :employee_id, destination = :destination, start_date = :start_date,
                    end_date = :end_date, purpose = :purpose, but_contexte = :but_contexte,
                    liaison_type = :liaison_type, expenses_json = :expenses_json, notes = :notes,
                    transport_mode = :transport, budget = :budget, status = :status, updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                'employee_id' => $employeeId,
                'destination' => $destination,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'purpose' => $purpose,
                'but_contexte' => $butContexte !== '' ? $butContexte : null,
                'liaison_type' => $liaisonType !== '' ? $liaisonType : null,
                'expenses_json' => $expensesJson,
                'notes' => $notes !== '' ? $notes : null,
                'transport' => $transport !== '' ? $transport : null,
                'budget' => $budget,
                'status' => $status,
                'id' => $id,
            ]);
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO rh_missions
                    (employee_id, destination, start_date, end_date, purpose, but_contexte, liaison_type, expenses_json, notes, transport_mode, budget, status, created_at)
                VALUES
                    (:employee_id, :destination, :start_date, :end_date, :purpose, :but_contexte, :liaison_type, :expenses_json, :notes, :transport, :budget, :status, NOW())
            ");
            $stmt->execute([
                'employee_id' => $employeeId,
                'destination' => $destination,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'purpose' => $purpose,
                'but_contexte' => $butContexte !== '' ? $butContexte : null,
                'liaison_type' => $liaisonType !== '' ? $liaisonType : null,
                'expenses_json' => $expensesJson,
                'notes' => $notes !== '' ? $notes : null,
                'transport' => $transport !== '' ? $transport : null,
                'budget' => $budget,
                'status' => $status,
            ]);
        }
    }

    public function decide(int $id, string $status, int $approverId): void
    {
        if (!in_array($status, ['approved', 'rejected', 'cancelled', 'submitted'], true)) {
            throw new \RuntimeException('Statut de mission invalide.');
        }

        // Set approved_by/approved_at only if approved
        $approver = ($status === 'approved' || $status === 'rejected') ? $approverId : null;
        $approvedAtSql = ($status === 'approved' || $status === 'rejected') ? ", approved_at = NOW()" : "";

        $stmt = $this->pdo->prepare("
            UPDATE rh_missions
            SET status = :status, approved_by = :approver {$approvedAtSql}, updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'status' => $status,
            'approver' => $approver,
            'id' => $id,
        ]);
    }
}
