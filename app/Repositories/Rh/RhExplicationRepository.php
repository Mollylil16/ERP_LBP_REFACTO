<?php

declare(strict_types=1);

namespace App\Repositories\Rh;

use PDO;

final class RhExplicationRepository
{
    public function __construct(private PDO $pdo) {}

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        return $this->pdo->query("
            SELECT x.*, e.full_name AS employee_name, e.employee_number
            FROM rh_explanation_requests x
            INNER JOIN rh_employees e ON e.id = x.employee_id
            ORDER BY x.created_at DESC
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

    public function create(array $data, int $actorId): void
    {
        $employeeId = (int) ($data['employee_id'] ?? 0);
        $subject = trim((string) ($data['subject'] ?? ''));
        $facts = trim((string) ($data['facts'] ?? ''));
        $responseDueDays = (int) ($data['response_due_days'] ?? 3);
        $incidentPeriod = trim((string) ($data['incident_period'] ?? ''));
        $incidentLocation = trim((string) ($data['incident_location'] ?? ''));
        $isDgCopy = isset($data['is_dg_copy']) && $data['is_dg_copy'] ? 1 : 0;
        $generalContext = trim((string) ($data['general_context'] ?? ''));
        $expectedExplanations = trim((string) ($data['expected_explanations'] ?? ''));
        $additionalElements = trim((string) ($data['additional_elements'] ?? ''));

        // Calculate due date based on due days
        $dueDate = date('Y-m-d', strtotime('+' . $responseDueDays . ' days'));

        // Optional: try to parse incident_date if incident_period is a simple date, else null
        $incidentDate = null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $incidentPeriod)) {
            $incidentDate = $incidentPeriod;
        }

        if ($employeeId <= 0 || $subject === '' || $facts === '') {
            throw new \RuntimeException('L\'employe, l\'objet et les faits sont obligatoires.');
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO rh_explanation_requests
                (employee_id, subject, facts, incident_date, response_due_date, response_due_days, incident_period, incident_location, is_dg_copy, general_context, expected_explanations, additional_elements, status, requested_by, created_at)
            VALUES
                (:employee_id, :subject, :facts, :incident_date, :due_date, :due_days, :incident_period, :incident_location, :is_dg_copy, :general_context, :expected_explanations, :additional_elements, 'pending_response', :actor, NOW())
        ");
        $stmt->execute([
            'employee_id' => $employeeId,
            'subject' => $subject,
            'facts' => $facts,
            'incident_date' => $incidentDate,
            'due_date' => $dueDate,
            'due_days' => $responseDueDays,
            'incident_period' => $incidentPeriod !== '' ? $incidentPeriod : null,
            'incident_location' => $incidentLocation !== '' ? $incidentLocation : null,
            'is_dg_copy' => $isDgCopy,
            'general_context' => $generalContext !== '' ? $generalContext : null,
            'expected_explanations' => $expectedExplanations !== '' ? $expectedExplanations : null,
            'additional_elements' => $additionalElements !== '' ? $additionalElements : null,
            'actor' => $actorId ?: null,
        ]);
    }

    public function respond(int $id, string $response): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE rh_explanation_requests
            SET employee_response = :response, status = 'responded', responded_at = NOW(), updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'response' => $response,
            'id' => $id,
        ]);
    }

    public function close(int $id): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE rh_explanation_requests
            SET status = 'closed', closed_at = NOW(), updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);
    }

    public function relancer(int $id): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE rh_explanation_requests
            SET status = 'complement_requested', updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);
    }
}
