<?php

declare(strict_types=1);

namespace App\Services\Rh;

use App\Repositories\Rh\RhLifecycleRepository;
use DateTimeImmutable;
use RuntimeException;

class RhLifecycleService
{
    public function __construct(private RhLifecycleRepository $repository) {}

    public function dashboard(): array
    {
        $data = $this->repository->dashboard();
        $data['alerts'] = array_values(array_filter($data['contracts'], static fn(array $contract): bool =>
            $contract['days_remaining'] !== null && (int) $contract['days_remaining'] >= 0 && (int) $contract['days_remaining'] <= 30
        ));
        return $data;
    }

    public function createContract(array $input, int $actorId): int
    {
        $start = $this->date($input['start_date'] ?? null, 'La date de début du contrat est obligatoire.');
        $end = $this->optionalDate($input['end_date'] ?? null);
        $trialStart = $this->optionalDate($input['trial_start_date'] ?? null);
        $trialEnd = $this->optionalDate($input['trial_end_date'] ?? null);
        $this->assertOrder($start, $end, 'La fin du contrat doit être postérieure à son début.');
        if ($trialStart !== null && $trialEnd !== null) {
            $this->assertOrder($trialStart, $trialEnd, 'La fin de période d’essai doit être postérieure à son début.');
        }
        return $this->repository->createContract([
            'employee_id' => $this->positiveInt($input['employee_id'] ?? null, 'Le collaborateur est obligatoire.'),
            'contract_type' => $this->required($input['contract_type'] ?? null, 'Le type de contrat est obligatoire.'),
            'reference' => $this->nullable($input['reference'] ?? null),
            'start_date' => $start,
            'end_date' => $end,
            'trial_start_date' => $trialStart,
            'trial_end_date' => $trialEnd,
            'trial_status' => $trialStart ? 'pending' : 'not_applicable',
            'alert_days' => '30,15,7',
        ], $actorId);
    }

    public function createEvaluation(array $input, int $actorId): int
    {
        $types = ['annual', 'semiannual', 'trial_end', 'assignment_end', 'professional'];
        $type = (string) ($input['evaluation_type'] ?? '');
        if (!in_array($type, $types, true)) {
            throw new RuntimeException('Le type d’évaluation est invalide.');
        }
        return $this->repository->createEvaluation([
            'employee_id' => $this->positiveInt($input['employee_id'] ?? null, 'Le collaborateur est obligatoire.'),
            'evaluator_employee_id' => $this->optionalPositiveInt($input['evaluator_employee_id'] ?? null),
            'evaluation_type' => $type,
            'period_label' => $this->required($input['period_label'] ?? null, 'La période évaluée est obligatoire.'),
            'due_date' => $this->optionalDate($input['due_date'] ?? null),
        ], $actorId);
    }

    public function createAssignment(array $input, int $actorId): int
    {
        $start = $this->date($input['start_date'] ?? null, 'La date de début de mission est obligatoire.');
        $end = $this->optionalDate($input['end_date'] ?? null);
        $this->assertOrder($start, $end, 'La fin de mission doit être postérieure à son début.');
        return $this->repository->createAssignment([
            'employee_id' => $this->positiveInt($input['employee_id'] ?? null, 'Le collaborateur est obligatoire.'),
            'title' => $this->required($input['title'] ?? null, 'L’intitulé de mission est obligatoire.'),
            'project_code' => $this->nullable($input['project_code'] ?? null),
            'manager_employee_id' => $this->optionalPositiveInt($input['manager_employee_id'] ?? null),
            'site_id' => $this->optionalPositiveInt($input['site_id'] ?? null),
            'start_date' => $start,
            'end_date' => $end,
            'notes' => $this->nullable($input['notes'] ?? null),
        ], $actorId);
    }

    public function createDisciplinaryAction(array $input, int $actorId): int
    {
        $type = (string) ($input['action_type'] ?? '');
        if (!in_array($type, ['warning', 'reprimand', 'suspension', 'other'], true)) {
            throw new RuntimeException('Le type de mesure disciplinaire est invalide.');
        }
        return $this->repository->createDisciplinaryAction([
            'employee_id' => $this->positiveInt($input['employee_id'] ?? null, 'Le collaborateur est obligatoire.'),
            'action_type' => $type,
            'action_date' => $this->date($input['action_date'] ?? null, 'La date de la mesure est obligatoire.'),
            'reason' => $this->required($input['reason'] ?? null, 'Le motif est obligatoire.'),
            'decision' => $this->nullable($input['decision'] ?? null),
        ], $actorId);
    }

    public function createTraining(array $input, int $actorId): int
    {
        $start = $this->date($input['start_date'] ?? null, 'La date de début de formation est obligatoire.');
        $end = $this->optionalDate($input['end_date'] ?? null);
        $this->assertOrder($start, $end, 'La fin de formation doit être postérieure à son début.');
        return $this->repository->createTraining([
            'title' => $this->required($input['title'] ?? null, 'Le titre de la formation est obligatoire.'),
            'training_type' => in_array(($input['training_type'] ?? ''), ['internal', 'external', 'mandatory', 'job'], true) ? $input['training_type'] : 'internal',
            'provider' => $this->nullable($input['provider'] ?? null),
            'start_date' => $start,
            'end_date' => $end,
            'budget' => max(0, (float) ($input['budget'] ?? 0)),
            'capacity' => $this->optionalPositiveInt($input['capacity'] ?? null),
        ], $actorId);
    }

    public function decideWorkflow(int $id, string $decision, int $actorId): void
    {
        if (!in_array($decision, ['approve', 'reject'], true)) {
            throw new RuntimeException('Décision de workflow invalide.');
        }
        $this->repository->advanceWorkflow($id, $decision, $actorId);
    }

    public function decideEmployeeRequest(int $id, string $decision, int $actorId, ?string $comment): void
    {
        if (!in_array($decision, ['approve', 'reject'], true)) throw new RuntimeException('Décision invalide.');
        $this->repository->advanceEmployeeRequest($id, $decision, $actorId, $this->nullable($comment));
    }

    private function required(mixed $value, string $message): string
    {
        $value = trim((string) $value);
        if ($value === '') throw new RuntimeException($message);
        return $value;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function positiveInt(mixed $value, string $message): int
    {
        $value = (int) $value;
        if ($value <= 0) throw new RuntimeException($message);
        return $value;
    }

    private function optionalPositiveInt(mixed $value): ?int
    {
        $value = (int) $value;
        return $value > 0 ? $value : null;
    }

    private function date(mixed $value, string $message): string
    {
        $date = $this->optionalDate($value);
        if ($date === null) throw new RuntimeException($message);
        return $date;
    }

    private function optionalDate(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    private function assertOrder(string $start, ?string $end, string $message): void
    {
        if ($end !== null && $end < $start) throw new RuntimeException($message);
    }
}
