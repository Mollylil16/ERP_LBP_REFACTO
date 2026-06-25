<?php

declare(strict_types=1);

namespace App\Repositories\Rh;

use PDO;

final class RhValidationRepository
{
    public function __construct(private PDO $pdo) {}

    public function getPendingEmployeeRequests(): array
    {
        return $this->getEmployeeRequests('pending');
    }

    /** @return array<int,array<string,mixed>> */
    public function getEmployeeRequests(string $tab = 'pending'): array
    {
        $where = "r.status NOT IN ('approved', 'rejected', 'cancelled', 'draft')";
        if ($tab === 'approved') {
            $where = "r.status = 'approved'";
        } elseif ($tab === 'rejected') {
            $where = "r.status = 'rejected'";
        } elseif ($tab === 'cancelled') {
            $where = "r.status = 'cancelled'";
        } elseif ($tab === 'all') {
            $where = "r.status != 'draft'";
        }

        return $this->pdo->query("
            SELECT r.*, e.full_name AS employee_name, e.employee_number
            FROM employee_legal_requests r
            INNER JOIN rh_employees e ON e.id = r.employee_id
            WHERE {$where}
            ORDER BY r.submitted_at DESC
        ")->fetchAll() ?: [];
    }

    /** @return array<int,array<string,mixed>> */
    public function getPendingWorkflows(): array
    {
        return $this->pdo->query("
            SELECT w.*, e.full_name AS employee_name
            FROM rh_workflow_requests w
            LEFT JOIN rh_employees e ON e.id = w.employee_id
            WHERE w.status = 'pending'
            ORDER BY w.created_at DESC
        ")->fetchAll() ?: [];
    }

    public function decideEmployeeRequest(int $id, string $decision, int $actorId, ?string $comment): void
    {
        $stmt = $this->pdo->prepare("SELECT * FROM employee_legal_requests WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $request = $stmt->fetch();
        if (!$request) {
            throw new \RuntimeException('Demande employe introuvable.');
        }

        $steps = [
            'manager' => ['rh', 'manager_approved'],
            'rh' => ['direction', 'hr_approved'],
            'direction' => ['completed', 'approved']
        ];

        if ($decision === 'reject') {
            $nextStep = 'completed';
            $status = 'rejected';
        } else {
            [$nextStep, $status] = $steps[$request['current_step']] ?? ['completed', 'approved'];
        }

        $this->pdo->beginTransaction();
        try {
            $update = $this->pdo->prepare("
                UPDATE employee_legal_requests
                SET current_step = :step, status = :status, decision_comment = :comment,
                    decided_at = IF(:final IN ('approved', 'rejected'), NOW(), decided_at), updated_at = NOW()
                WHERE id = :id
            ");
            $update->execute([
                'step' => $nextStep,
                'status' => $status,
                'comment' => $comment,
                'final' => $status,
                'id' => $id
            ]);

            $event = $this->pdo->prepare("
                INSERT INTO employee_request_events (request_id, event_type, step, status, comment, actor_user_id)
                VALUES (:request_id, :event_type, :step, :status, :comment, :actor)
            ");
            $event->execute([
                'request_id' => $id,
                'event_type' => $decision,
                'step' => $nextStep,
                'status' => $status,
                'comment' => $comment,
                'actor' => $actorId ?: null
            ]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function decideWorkflow(int $id, string $decision, int $actorId): void
    {
        $stmt = $this->pdo->prepare("SELECT * FROM rh_workflow_requests WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new \RuntimeException('Workflow RH introuvable.');
        }

        $steps = ['manager' => 'rh', 'rh' => 'direction', 'direction' => 'completed'];
        $next = $decision === 'approve' ? ($steps[$row['current_step']] ?? 'completed') : 'completed';
        $status = $decision === 'reject' ? 'rejected' : ($next === 'completed' ? 'approved' : 'pending');

        $update = $this->pdo->prepare("
            UPDATE rh_workflow_requests
            SET current_step = :step, status = :status, decided_by = :actor,
                decided_at = IF(:final IN ('approved', 'rejected'), NOW(), decided_at), updated_at = NOW()
            WHERE id = :id
        ");
        $update->execute([
            'step' => $next,
            'status' => $status,
            'final' => $status,
            'actor' => $actorId ?: null,
            'id' => $id
        ]);
    }
}
