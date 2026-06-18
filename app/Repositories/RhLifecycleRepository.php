<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class RhLifecycleRepository
{
    public function __construct(private PDO $pdo) {}

    public function dashboard(): array
    {
        return [
            'contracts' => $this->rows("
                SELECT c.*, e.full_name, e.employee_number,
                    DATEDIFF(COALESCE(c.trial_end_date, c.end_date), CURDATE()) AS days_remaining
                FROM rh_contracts c
                INNER JOIN rh_employees e ON e.id = c.employee_id
                ORDER BY COALESCE(c.trial_end_date, c.end_date) IS NULL,
                         COALESCE(c.trial_end_date, c.end_date), c.id DESC LIMIT 100
            "),
            'assignments' => $this->rows("
                SELECT a.*, e.full_name, m.full_name AS manager_name, s.name AS site_name
                FROM rh_assignments a
                INNER JOIN rh_employees e ON e.id = a.employee_id
                LEFT JOIN rh_employees m ON m.id = a.manager_employee_id
                LEFT JOIN company_sites s ON s.id = a.site_id
                ORDER BY a.start_date DESC, a.id DESC LIMIT 100
            "),
            'evaluations' => $this->rows("
                SELECT v.*, e.full_name
                FROM rh_evaluations v
                INNER JOIN rh_employees e ON e.id = v.employee_id
                ORDER BY v.due_date IS NULL, v.due_date, v.id DESC LIMIT 100
            "),
            'trainings' => $this->rows("
                SELECT t.*, COUNT(en.id) AS enrolled_count
                FROM rh_training_sessions t
                LEFT JOIN rh_training_enrollments en ON en.session_id = t.id
                GROUP BY t.id ORDER BY t.start_date DESC, t.id DESC LIMIT 100
            "),
            'workflows' => $this->rows("
                SELECT w.*, e.full_name
                FROM rh_workflow_requests w
                LEFT JOIN rh_employees e ON e.id = w.employee_id
                ORDER BY FIELD(w.status, 'pending', 'draft', 'approved', 'completed', 'rejected', 'cancelled'), w.id DESC
                LIMIT 100
            "),
            'employees' => $this->rows("SELECT id, employee_number, full_name FROM rh_employees WHERE is_active = 1 ORDER BY full_name"),
            'sites' => $this->rows("SELECT id, name FROM company_sites WHERE is_active = 1 ORDER BY name"),
            'disciplinaryActions' => $this->rows("
                SELECT d.*, e.full_name FROM rh_disciplinary_actions d
                INNER JOIN rh_employees e ON e.id = d.employee_id
                ORDER BY d.action_date DESC, d.id DESC LIMIT 100
            "),
            'employeeRequests' => $this->rows("
                SELECT r.*, e.full_name
                FROM employee_legal_requests r
                INNER JOIN rh_employees e ON e.id = r.employee_id
                WHERE r.status NOT IN ('approved','rejected','cancelled')
                ORDER BY r.id DESC LIMIT 100
            "),
        ];
    }

    public function createContract(array $data, int $actorId): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO rh_contracts
                (employee_id, contract_type, reference, start_date, end_date, trial_start_date,
                 trial_end_date, trial_status, status, alert_days, created_by)
            VALUES
                (:employee_id, :contract_type, :reference, :start_date, :end_date, :trial_start_date,
                 :trial_end_date, :trial_status, 'approval', :alert_days, :created_by)
        ");
        $stmt->execute($data + ['created_by' => $actorId ?: null]);
        $id = (int) $this->pdo->lastInsertId();
        $this->createWorkflow('contract_request', 'contract', $id, (int) $data['employee_id'], $actorId);
        return $id;
    }

    public function createEvaluation(array $data, int $actorId): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO rh_evaluations
                (employee_id, evaluator_employee_id, evaluation_type, period_label, due_date, status)
            VALUES (:employee_id, :evaluator_employee_id, :evaluation_type, :period_label, :due_date, 'self_review')
        ");
        $stmt->execute($data);
        $id = (int) $this->pdo->lastInsertId();
        $this->createWorkflow('evaluation', 'evaluation', $id, (int) $data['employee_id'], $actorId);
        return $id;
    }

    public function createAssignment(array $data, int $actorId): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO rh_assignments
                (employee_id, title, project_code, manager_employee_id, site_id, start_date, end_date, status, notes, created_by)
            VALUES (:employee_id, :title, :project_code, :manager_employee_id, :site_id, :start_date, :end_date, 'approval', :notes, :created_by)
        ");
        $stmt->execute($data + ['created_by' => $actorId ?: null]);
        $id = (int) $this->pdo->lastInsertId();
        $this->createWorkflow('assignment_change', 'assignment', $id, (int) $data['employee_id'], $actorId);
        return $id;
    }

    public function createDisciplinaryAction(array $data, int $actorId): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO rh_disciplinary_actions
                (employee_id, action_type, action_date, reason, decision, status, created_by)
            VALUES (:employee_id, :action_type, :action_date, :reason, :decision, 'draft', :created_by)
        ");
        $stmt->execute($data + ['created_by' => $actorId ?: null]);
        return (int) $this->pdo->lastInsertId();
    }

    public function createTraining(array $data, int $actorId): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO rh_training_sessions
                (title, training_type, provider, start_date, end_date, budget, capacity, status)
            VALUES (:title, :training_type, :provider, :start_date, :end_date, :budget, :capacity, 'approval')
        ");
        $stmt->execute($data);
        $id = (int) $this->pdo->lastInsertId();
        $this->createWorkflow('training_session', 'training', $id, 0, $actorId);
        return $id;
    }

    public function advanceWorkflow(int $id, string $decision, int $actorId): void
    {
        $workflow = $this->pdo->prepare("SELECT * FROM rh_workflow_requests WHERE id = :id LIMIT 1");
        $workflow->execute(['id' => $id]);
        $row = $workflow->fetch();
        if (!$row) {
            throw new \RuntimeException('Workflow RH introuvable.');
        }
        $steps = ['manager' => 'rh', 'rh' => 'direction', 'direction' => 'completed'];
        $next = $decision === 'approve' ? ($steps[$row['current_step']] ?? 'completed') : 'completed';
        $status = $decision === 'reject' ? 'rejected' : ($next === 'completed' ? 'approved' : 'pending');
        $stmt = $this->pdo->prepare("
            UPDATE rh_workflow_requests
            SET current_step = :step, status = :status, decided_by = :actor,
                decided_at = IF(:final_status IN ('approved','rejected'), NOW(), decided_at), updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute(['step' => $next, 'status' => $status, 'final_status' => $status, 'actor' => $actorId ?: null, 'id' => $id]);
    }

    public function advanceEmployeeRequest(int $id, string $decision, int $actorId, ?string $comment): void
    {
        $stmt = $this->pdo->prepare("SELECT * FROM employee_legal_requests WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $request = $stmt->fetch();
        if (!$request) throw new \RuntimeException('Demande employé introuvable.');
        $steps = ['manager' => ['rh', 'manager_approved'], 'rh' => ['direction', 'hr_approved'], 'direction' => ['completed', 'approved']];
        if ($decision === 'reject') {
            $nextStep = 'completed';
            $status = 'rejected';
        } else {
            [$nextStep, $status] = $steps[$request['current_step']] ?? ['completed', 'approved'];
        }
        $this->pdo->beginTransaction();
        try {
            $update = $this->pdo->prepare("
                UPDATE employee_legal_requests SET current_step = :step, status = :status,
                    decision_comment = :comment, decided_at = IF(:final IN ('approved','rejected'), NOW(), decided_at), updated_at = NOW()
                WHERE id = :id
            ");
            $update->execute(['step' => $nextStep, 'status' => $status, 'comment' => $comment, 'final' => $status, 'id' => $id]);
            $event = $this->pdo->prepare("
                INSERT INTO employee_request_events (request_id, event_type, step, status, comment, actor_user_id)
                VALUES (:request_id, :event_type, :step, :status, :comment, :actor)
            ");
            $event->execute(['request_id' => $id, 'event_type' => $decision, 'step' => $nextStep, 'status' => $status, 'comment' => $comment, 'actor' => $actorId ?: null]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function createWorkflow(string $process, string $subject, int $subjectId, int $employeeId, int $actorId): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO rh_workflow_requests
                (process_type, subject_type, subject_id, employee_id, current_step, status, requested_by)
            VALUES (:process, :subject, :subject_id, :employee_id, 'manager', 'pending', :requested_by)
        ");
        $stmt->execute([
            'process' => $process,
            'subject' => $subject,
            'subject_id' => $subjectId,
            'employee_id' => $employeeId ?: null,
            'requested_by' => $actorId ?: null,
        ]);
    }

    private function rows(string $sql): array
    {
        return $this->pdo->query($sql)->fetchAll() ?: [];
    }
}
