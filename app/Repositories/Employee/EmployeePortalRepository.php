<?php

declare(strict_types=1);

namespace App\Repositories\Employee;

use PDO;

class EmployeePortalRepository
{
    public function __construct(private PDO $pdo) {}

    public function employee(int $employeeId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT e.*, s.name AS service_name, f.name AS function_name, st.name AS status_name
            FROM rh_employees e
            LEFT JOIN rh_services s ON s.id = e.service_id
            LEFT JOIN rh_functions f ON f.id = e.function_id
            LEFT JOIN rh_statuses st ON st.id = e.status_id
            WHERE e.id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $employeeId]);
        return $stmt->fetch() ?: null;
    }

    public function requests(int $employeeId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM employee_legal_requests WHERE employee_id = :employee_id ORDER BY id DESC");
        $stmt->execute(['employee_id' => $employeeId]);
        return $stmt->fetchAll() ?: [];
    }

    public function request(int $employeeId, int $requestId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM employee_legal_requests WHERE id = :id AND employee_id = :employee_id LIMIT 1");
        $stmt->execute(['id' => $requestId, 'employee_id' => $employeeId]);
        $request = $stmt->fetch();
        if (!$request) return null;
        $events = $this->pdo->prepare("SELECT * FROM employee_request_events WHERE request_id = :id ORDER BY id");
        $events->execute(['id' => $requestId]);
        $request['events'] = $events->fetchAll() ?: [];
        return $request;
    }

    public function createRequest(int $employeeId, array $data, int $userId): int
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO employee_legal_requests
                    (employee_id, request_type, reference, start_date, end_date, amount, reason,
                     metadata_json, attachment_path, attachment_original_name, attachment_mime_type,
                     attachment_size_bytes, current_step, status)
                VALUES (:employee_id, :request_type, :reference, :start_date, :end_date, :amount, :reason,
                    :metadata_json, :attachment_path, :attachment_original_name, :attachment_mime_type,
                    :attachment_size_bytes, 'manager', 'submitted')
            ");
            $stmt->execute($data + ['employee_id' => $employeeId]);
            $id = (int) $this->pdo->lastInsertId();
            $this->addEvent($id, 'submitted', 'manager', 'submitted', 'Demande soumise par le collaborateur.', $userId);
            $this->pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function cancelRequest(int $employeeId, int $requestId, int $userId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE employee_legal_requests SET status = 'cancelled', current_step = 'completed', updated_at = NOW()
            WHERE id = :id AND employee_id = :employee_id AND status IN ('draft','submitted')
        ");
        $stmt->execute(['id' => $requestId, 'employee_id' => $employeeId]);
        if ($stmt->rowCount() !== 1) throw new \RuntimeException('Cette demande ne peut plus être annulée.');
        $this->addEvent($requestId, 'cancelled', 'completed', 'cancelled', 'Demande annulée par le collaborateur.', $userId);
    }

    public function explanations(int $employeeId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM rh_explanation_requests WHERE employee_id = :employee_id ORDER BY id DESC");
        $stmt->execute(['employee_id' => $employeeId]);
        return $stmt->fetchAll() ?: [];
    }

    public function respondExplanation(int $employeeId, int $id, string $response): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE rh_explanation_requests
            SET employee_response = :response, status = 'responded', responded_at = NOW(), updated_at = NOW()
            WHERE id = :id AND employee_id = :employee_id AND status IN ('pending_response','complement_requested')
        ");
        $stmt->execute(['response' => $response, 'id' => $id, 'employee_id' => $employeeId]);
        if ($stmt->rowCount() !== 1) throw new \RuntimeException('Cette demande d’explication ne peut pas être traitée.');
    }

    public function attendance(int $employeeId, string $from, string $to): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM rh_attendance_daily
            WHERE employee_id = :employee_id AND attendance_date BETWEEN :date_from AND :date_to
            ORDER BY attendance_date DESC
        ");
        $stmt->execute(['employee_id' => $employeeId, 'date_from' => $from, 'date_to' => $to]);
        return $stmt->fetchAll() ?: [];
    }

    public function leaveBalance(int $employeeId, int $year): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM rh_leave_opening_balance WHERE employee_id = :employee_id AND leave_year = :year LIMIT 1");
        $stmt->execute(['employee_id' => $employeeId, 'year' => $year]);
        return $stmt->fetch() ?: ['opening_days' => 0, 'acquired_days' => 0, 'taken_days' => 0];
    }

    public function documents(int $employeeId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM rh_employee_documents WHERE employee_id = :employee_id ORDER BY id DESC");
        $stmt->execute(['employee_id' => $employeeId]);
        return $stmt->fetchAll() ?: [];
    }

    private function addEvent(int $requestId, string $type, string $step, string $status, string $comment, int $userId): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO employee_request_events (request_id, event_type, step, status, comment, actor_user_id)
            VALUES (:request_id, :event_type, :step, :status, :comment, :actor)
        ");
        $stmt->execute(['request_id' => $requestId, 'event_type' => $type, 'step' => $step, 'status' => $status, 'comment' => $comment, 'actor' => $userId ?: null]);
    }
}
