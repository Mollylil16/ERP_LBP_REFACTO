<?php

declare(strict_types=1);

namespace App\Services\Employee;

use App\Models\User;
use App\Repositories\Employee\EmployeePortalRepository;
use DateTimeImmutable;
use RuntimeException;

class EmployeePortalService
{
    public function __construct(
        private EmployeePortalRepository $repository,
        private ?EmployeeRequestUploadService $uploads = null,
    ) {
        $this->uploads ??= new EmployeeRequestUploadService();
    }

    public function dashboard(User $user): array
    {
        $employeeId = $this->employeeId($user);
        $employee = $this->repository->employee($employeeId);
        if (!$employee) throw new RuntimeException('Le dossier collaborateur lié à ce compte est introuvable.');
        $requests = $this->repository->requests($employeeId);
        $attendance = $this->repository->attendance($employeeId, date('Y-m-01'), date('Y-m-t'));
        $explanations = $this->repository->explanations($employeeId);
        $balance = $this->repository->leaveBalance($employeeId, (int) date('Y'));
        $present = count(array_filter($attendance, static fn(array $row): bool => in_array($row['attendance_status'], ['present', 'mission', 'conge'], true)));
        return compact('employee', 'requests', 'attendance', 'explanations', 'balance') + [
            'documents' => $this->repository->documents($employeeId),
            'stats' => [
                'openRequests' => count(array_filter($requests, static fn(array $row): bool => !in_array($row['status'], ['approved', 'rejected', 'cancelled'], true))),
                'pendingExplanations' => count(array_filter($explanations, static fn(array $row): bool => in_array($row['status'], ['pending_response', 'complement_requested'], true))),
                'presenceRate' => $attendance === [] ? 0 : round(($present / count($attendance)) * 100, 1),
                'leaveRemaining' => round((float)$balance['opening_days'] + (float)$balance['acquired_days'] - (float)$balance['taken_days'], 2),
            ],
        ];
    }

    public function request(User $user, int $requestId): array
    {
        $request = $this->repository->request($this->employeeId($user), $requestId);
        if (!$request) throw new RuntimeException('Demande introuvable.');
        $request['metadata'] = json_decode((string) ($request['metadata_json'] ?? ''), true) ?: [];
        return $request;
    }

    public function createRequest(User $user, array $input, array $files = []): int
    {
        $type = (string) ($input['request_type'] ?? '');
        if (EmployeeRequestCatalog::get($type) === null) throw new RuntimeException('Le type de demande est invalide.');
        $reason = $this->requiredText($input['reason'] ?? null, 'Le motif de la demande est obligatoire.');
        $start = null;
        $end = null;
        $amount = null;
        $metadata = [];

        switch ($type) {
            case 'leave':
                $start = $this->requiredDate($input['start_date'] ?? null, 'Le premier jour de congé est obligatoire.');
                $end = $this->requiredDate($input['end_date'] ?? null, 'Le dernier jour de congé est obligatoire.');
                $metadata['leave_kind'] = $this->choice($input['leave_kind'] ?? null, ['annual', 'family', 'maternity_paternity', 'unpaid'], 'La nature du congé est obligatoire.');
                $metadata['handover'] = $this->nullable($input['handover'] ?? null);
                break;
            case 'absence':
                $start = $this->requiredDate($input['start_date'] ?? null, 'La date de début de l’absence est obligatoire.');
                $end = $this->optionalDate($input['end_date'] ?? null);
                $metadata['absence_kind'] = $this->choice($input['absence_kind'] ?? null, ['planned', 'medical', 'emergency', 'justification'], 'Le type d’absence est obligatoire.');
                break;
            case 'lateness':
                $start = $this->requiredDate($input['incident_date'] ?? null, 'La date du retard est obligatoire.');
                $metadata['arrival_time'] = $this->requiredTime($input['arrival_time'] ?? null, 'L’heure d’arrivée est obligatoire.');
                break;
            case 'attendance_correction':
                $start = $this->requiredDate($input['incident_date'] ?? null, 'La journée à corriger est obligatoire.');
                $metadata['correction_kind'] = $this->choice($input['correction_kind'] ?? null, ['missing_entry', 'missing_exit', 'wrong_time', 'wrong_status'], 'Le type de correction est obligatoire.');
                $metadata['check_in_time'] = $this->optionalTime($input['check_in_time'] ?? null);
                $metadata['check_out_time'] = $this->optionalTime($input['check_out_time'] ?? null);
                if ($metadata['correction_kind'] === 'wrong_time' && !$metadata['check_in_time'] && !$metadata['check_out_time']) {
                    throw new RuntimeException('Renseignez au moins une heure correcte.');
                }
                break;
            case 'salary_advance':
                $amount = (float) ($input['amount'] ?? 0);
                if ($amount <= 0) throw new RuntimeException('Le montant de l’avance doit être supérieur à zéro.');
                $metadata['repayment_months'] = (int) $this->choice((string) ($input['repayment_months'] ?? ''), ['1', '2', '3', '4', '6'], 'La durée de remboursement est obligatoire.');
                $metadata['desired_payment_date'] = $this->optionalDate($input['desired_payment_date'] ?? null);
                break;
            case 'document':
                $metadata['document_kind'] = $this->choice($input['document_kind'] ?? null, ['work_certificate', 'salary_certificate', 'employment_certificate', 'contract_copy', 'other'], 'Le document souhaité est obligatoire.');
                $metadata['delivery_format'] = $this->choice($input['delivery_format'] ?? null, ['digital', 'paper', 'both'], 'Le format de livraison est obligatoire.');
                break;
            case 'other':
                $metadata['subject'] = $this->requiredText($input['subject'] ?? null, 'L’objet de la demande est obligatoire.');
                break;
        }

        if ($end !== null && $start !== null && $end < $start) throw new RuntimeException('La date de fin doit être postérieure à la date de début.');
        $attachment = $this->uploads->store($files['attachment'] ?? null);
        return $this->repository->createRequest($this->employeeId($user), [
            'request_type' => $type,
            'reference' => 'REQ-' . date('Ym') . '-' . strtoupper(bin2hex(random_bytes(3))),
            'start_date' => $start,
            'end_date' => $end,
            'amount' => $amount,
            'reason' => $reason,
            'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'attachment_path' => $attachment['path'] ?? null,
            'attachment_original_name' => $attachment['original_name'] ?? null,
            'attachment_mime_type' => $attachment['mime_type'] ?? null,
            'attachment_size_bytes' => $attachment['size_bytes'] ?? null,
        ], (int) $user->id);
    }

    public function cancelRequest(User $user, int $requestId): void
    {
        $this->repository->cancelRequest($this->employeeId($user), $requestId, (int) $user->id);
    }

    public function respondExplanation(User $user, int $id, array $input): void
    {
        $response = trim((string) ($input['response'] ?? ''));
        if (mb_strlen($response) < 20) throw new RuntimeException('La réponse doit contenir au moins 20 caractères.');
        $this->repository->respondExplanation($this->employeeId($user), $id, $response);
    }

    private function employeeId(User $user): int
    {
        if (!$user->rhEmployeeId) throw new RuntimeException('Votre compte n’est pas rattaché à un dossier collaborateur. Contactez les RH.');
        return $user->rhEmployeeId;
    }

    private function requiredText(mixed $value, string $message): string
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

    private function choice(mixed $value, array $allowed, string $message): string
    {
        $value = trim((string) $value);
        if (!in_array($value, $allowed, true)) throw new RuntimeException($message);
        return $value;
    }

    private function optionalDate(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) throw new RuntimeException('Une date fournie est invalide.');
        return $value;
    }

    private function requiredDate(mixed $value, string $message): string
    {
        $date = $this->optionalDate($value);
        if ($date === null) throw new RuntimeException($message);
        return $date;
    }

    private function optionalTime(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) throw new RuntimeException('Une heure fournie est invalide.');
        return $value;
    }

    private function requiredTime(mixed $value, string $message): string
    {
        $time = $this->optionalTime($value);
        if ($time === null) throw new RuntimeException($message);
        return $time;
    }
}
