<?php

namespace App\Services;

use App\Repositories\RhPersonnelRepository;
use RuntimeException;

class RhPersonnelService
{
    public function __construct(private RhPersonnelRepository $repository) {}

    public function list(array $query): array
    {
        $scope = (string) ($query['scope'] ?? 'active');
        if (!in_array($scope, ['active', 'inactive', 'all'], true)) {
            $scope = 'active';
        }

        $filters = [
            'q' => trim((string) ($query['q'] ?? '')),
            'service_id' => (int) ($query['service_id'] ?? 0),
            'function_id' => (int) ($query['function_id'] ?? 0),
            'status_id' => (int) ($query['status_id'] ?? 0),
            'scope' => $scope,
        ];

        return [
            'filters' => $filters,
            'pagination' => $this->repository->paginate(
                $filters,
                (int) ($query['page'] ?? 1)
            ),
            'options' => $this->repository->options(),
        ];
    }

    public function dossier(int $id): array
    {
        $employee = $this->repository->find($id);
        if (!$employee) {
            throw new RuntimeException('Collaborateur introuvable.');
        }

        return [
            'employee' => $employee,
            'history' => $this->repository->history($id),
            'mutations' => $this->repository->mutations($id),
            'options' => $this->repository->options(),
        ];
    }

    public function create(array $input, int $actorId): int
    {
        return $this->repository->create($this->validateEmployee($input), $actorId);
    }

    public function update(int $id, array $input): void
    {
        $this->requireEmployee($id);
        $this->repository->update($id, $this->validateEmployee($input, $id));
    }

    public function mutate(int $id, array $input, int $actorId): void
    {
        $employee = $this->requireEmployee($id);
        $effectiveDate = $this->requiredDate($input['effective_date'] ?? null, 'La date de mutation est obligatoire.');

        $this->repository->applyMutation($id, [
            'effective_date' => $effectiveDate,
            'title' => $this->nullableString($input['title'] ?? null) ?? 'Mutation / affectation RH',
            'service_id' => $this->nullableInt($input['service_id'] ?? null) ?? $this->nullableInt($employee['service_id']),
            'function_id' => $this->nullableInt($input['function_id'] ?? null) ?? $this->nullableInt($employee['function_id']),
            'status_id' => $this->nullableInt($input['status_id'] ?? null) ?? $this->nullableInt($employee['status_id']),
            'site' => $this->nullableString($input['site'] ?? null) ?? $employee['site'],
            'start_date' => $this->nullableDate($input['start_date'] ?? null),
            'reason' => $this->nullableString($input['reason'] ?? null),
        ], $actorId);
    }

    public function exit(int $id, array $input, int $actorId): void
    {
        $this->requireEmployee($id);
        $this->repository->exitEmployee($id, [
            'exit_date' => $this->requiredDate($input['exit_date'] ?? null, 'La date de sortie est obligatoire.'),
            'exit_reason_id' => $this->nullableInt($input['exit_reason_id'] ?? null),
            'exit_notes' => $this->nullableString($input['exit_notes'] ?? null),
        ], $actorId);
    }

    public function reintegrate(int $id, array $input, int $actorId): void
    {
        $this->requireEmployee($id);
        $date = $this->requiredDate($input['start_date'] ?? null, 'La date de reintegration est obligatoire.');
        $this->repository->reintegrate($id, $date, $actorId);
    }

    public function addHistory(int $id, array $input, int $actorId): void
    {
        $this->requireEmployee($id);
        $title = $this->nullableString($input['title'] ?? null);
        if ($title === null) {
            throw new RuntimeException('Le titre de l’evenement est obligatoire.');
        }

        $allowedTypes = ['note', 'promotion', 'sanction', 'formation', 'renouvellement', 'affectation'];
        $type = (string) ($input['event_type'] ?? 'note');
        if (!in_array($type, $allowedTypes, true)) {
            $type = 'note';
        }

        $this->repository->addHistory($id, [
            'event_type' => $type,
            'event_date' => $this->requiredDate($input['event_date'] ?? null, 'La date de l’evenement est obligatoire.'),
            'title' => $title,
            'description' => $this->nullableString($input['description'] ?? null),
        ], $actorId);
    }

    public function options(): array
    {
        return $this->repository->options();
    }

    public function mutationRegister(): array
    {
        return ['mutations' => $this->repository->allMutations()];
    }

    public function movementRegister(): array
    {
        return ['movements' => $this->repository->movements()];
    }

    private function validateEmployee(array $input, ?int $id = null): array
    {
        $fullName = trim((string) ($input['full_name'] ?? ''));
        if ($fullName === '') {
            throw new RuntimeException('Le nom complet est obligatoire.');
        }

        $email = $this->nullableString($input['email'] ?? null);
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('L’adresse email est invalide.');
        }

        $gender = $this->nullableString($input['gender'] ?? null);
        if ($gender !== null && !in_array($gender, ['male', 'female', 'other'], true)) {
            $gender = null;
        }

        return [
            'employee_number' => $this->nullableString($input['employee_number'] ?? null) ?? $this->generateNumber($id),
            'full_name' => $fullName,
            'email' => $email,
            'phone' => $this->nullableString($input['phone'] ?? null),
            'gender' => $gender,
            'birth_date' => $this->nullableDate($input['birth_date'] ?? null),
            'birth_place' => $this->nullableString($input['birth_place'] ?? null),
            'marital_status' => $this->nullableString($input['marital_status'] ?? null),
            'address' => $this->nullableString($input['address'] ?? null),
            'site' => $this->nullableString($input['site'] ?? null),
            'service_id' => $this->nullableInt($input['service_id'] ?? null),
            'function_id' => $this->nullableInt($input['function_id'] ?? null),
            'status_id' => $this->nullableInt($input['status_id'] ?? null),
            'cni_number' => $this->nullableString($input['cni_number'] ?? null),
            'cnps_number' => $this->nullableString($input['cnps_number'] ?? null),
            'contract_duration_months' => $this->nullableInt($input['contract_duration_months'] ?? null),
            'hire_date' => $this->nullableDate($input['hire_date'] ?? null),
            'start_date' => $this->nullableDate($input['start_date'] ?? null),
            'father_name' => $this->nullableString($input['father_name'] ?? null),
            'father_phone' => $this->nullableString($input['father_phone'] ?? null),
            'mother_name' => $this->nullableString($input['mother_name'] ?? null),
            'mother_phone' => $this->nullableString($input['mother_phone'] ?? null),
            'emergency_contact_name' => $this->nullableString($input['emergency_contact_name'] ?? null),
            'emergency_contact_phone' => $this->nullableString($input['emergency_contact_phone'] ?? null),
            'children_count' => max(0, (int) ($input['children_count'] ?? 0)),
        ];
    }

    private function requireEmployee(int $id): array
    {
        $employee = $this->repository->find($id);
        if (!$employee) {
            throw new RuntimeException('Collaborateur introuvable.');
        }
        return $employee;
    }

    private function generateNumber(?int $id): string
    {
        return 'RH-' . date('Y') . '-' . str_pad((string) ($id ?? random_int(1, 99999)), 5, '0', STR_PAD_LEFT);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value !== null && $value !== '' && (int) $value > 0 ? (int) $value : null;
    }

    private function nullableDate(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    private function requiredDate(mixed $value, string $message): string
    {
        $date = $this->nullableDate($value);
        if ($date === null) {
            throw new RuntimeException($message);
        }
        return $date;
    }
}
