<?php

namespace App\Services\Rh;

use App\Repositories\Rh\RhPersonnelRepository;
use App\Security\PermissionEntityRegistry;
use App\Services\Support\DataVisibilityService;
use RuntimeException;

class RhPersonnelService
{
    public function __construct(
        private RhPersonnelRepository $repository,
        private ?DataVisibilityService $visibility = null,
    ) {
        $this->visibility ??= new DataVisibilityService();
    }

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
            'gender' => trim((string) ($query['gender'] ?? '')),
            'site' => trim((string) ($query['site'] ?? '')),
        ];

        $pagination = $this->repository->paginate(
            $filters,
            (int) ($query['page'] ?? 1)
        );
        $pagination['items'] = $this->visibility->employeeRows($pagination['items']);
        if (!$this->visibility->canView(PermissionEntityRegistry::RH_EMPLOYEES)) {
            $pagination['total'] = 0;
            $pagination['totalPages'] = 1;
        }

        return [
            'filters' => $filters,
            'pagination' => $pagination,
            'options' => $this->visibility->options($this->repository->options()),
            'restrictedTables' => $this->visibility->restrictedTables(),
            'stats' => $this->repository->getStats(),
        ];
    }

    public function dossier(int $id): array
    {
        $employee = $this->repository->find($id);
        if (!$employee) {
            throw new RuntimeException('Collaborateur introuvable.');
        }

        $documents = $this->repository->documents($id);
        $birthCertificatePath = '';
        $employmentContractPath = '';
        foreach ($documents as $doc) {
            if ($doc['document_type'] === 'birth_certificate') {
                $birthCertificatePath = $doc['stored_path'];
            } elseif ($doc['document_type'] === 'employment_contract') {
                $employmentContractPath = $doc['stored_path'];
            }
        }
        $employee['birth_certificate_path'] = $birthCertificatePath;
        $employee['employment_contract_path'] = $employmentContractPath;

        return [
            'employee' => $this->visibility->employee($employee),
            'history' => $this->visibility->history($this->repository->history($id)),
            'mutations' => $this->visibility->mutations($this->repository->mutations($id)),
            'documents' => $documents,
            'options' => $this->visibility->options($this->repository->options()),
            'restrictedTables' => $this->visibility->restrictedTables(),
        ];
    }

    public function create(array $input, array $files, int $actorId): int
    {
        $data = $this->protectRestrictedReferences($this->validateEmployee($input));
        $uploads = $this->collectUploads($files, (int) $data['children_count']);
        return $this->repository->create($data, $actorId, $uploads['columns'], $uploads['documents']);
    }

    public function update(int $id, array $input, array $files): void
    {
        $employee = $this->requireEmployee($id);
        $data = $this->validateEmployee($input, $id);
        $uploads = $this->collectUploads($files, (int) $data['children_count']);

        $this->repository->update($id, $this->protectRestrictedReferences($data, $employee), $uploads['columns'], $uploads['documents']);
    }

    public function mutate(int $id, array $input, int $actorId): void
    {
        $employee = $this->requireEmployee($id);
        $effectiveDate = $this->requiredDate($input['effective_date'] ?? null, 'La date de mutation est obligatoire.');

        $data = [
            'effective_date' => $effectiveDate,
            'title' => $this->nullableString($input['title'] ?? null) ?? 'Mutation / affectation RH',
            'service_id' => $this->nullableInt($input['service_id'] ?? null) ?? $this->nullableInt($employee['service_id']),
            'function_id' => $this->nullableInt($input['function_id'] ?? null) ?? $this->nullableInt($employee['function_id']),
            'status_id' => $this->nullableInt($input['status_id'] ?? null) ?? $this->nullableInt($employee['status_id']),
            'site' => $this->nullableString($input['site'] ?? null) ?? $employee['site'],
            'start_date' => $this->nullableDate($input['start_date'] ?? null),
            'reason' => $this->nullableString($input['reason'] ?? null),
        ];

        $this->repository->applyMutation(
            $id,
            $this->protectRestrictedReferences($data, $employee),
            $actorId
        );
    }

    public function exit(int $id, array $input, int $actorId): void
    {
        $this->requireEmployee($id);
        $data = [
            'exit_date' => $this->requiredDate($input['exit_date'] ?? null, 'La date de sortie est obligatoire.'),
            'exit_reason_id' => $this->nullableInt($input['exit_reason_id'] ?? null),
            'exit_notes' => $this->nullableString($input['exit_notes'] ?? null),
        ];
        if (!$this->visibility->canView(PermissionEntityRegistry::RH_EXIT_REASONS)) {
            $data['exit_reason_id'] = null;
        }
        $this->repository->exitEmployee($id, $data, $actorId);
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
        return $this->visibility->options($this->repository->options());
    }

    public function restrictedTables(): array
    {
        return $this->visibility->restrictedTables();
    }

    public function mutationRegister(): array
    {
        return [
            'mutations' => $this->visibility->mutations($this->repository->allMutations()),
            'restrictedTables' => $this->visibility->restrictedTables(),
        ];
    }

    public function movementRegister(): array
    {
        return [
            'movements' => $this->visibility->movements($this->repository->movements()),
            'restrictedTables' => $this->visibility->restrictedTables(),
        ];
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

    private function protectRestrictedReferences(array $data, ?array $existing = null): array
    {
        $protectedReferences = [
            PermissionEntityRegistry::RH_SERVICES => 'service_id',
            PermissionEntityRegistry::RH_FUNCTIONS => 'function_id',
            PermissionEntityRegistry::RH_STATUSES => 'status_id',
        ];

        foreach ($protectedReferences as $table => $field) {
            if (!$this->visibility->canView($table)) {
                $value = $existing[$field] ?? null;
                $data[$field] = $value !== null ? (int) $value : null;
            }
        }

        return $data;
    }


    private function collectUploads(array $files, int $childrenCount): array
    {
        $columns = [];
        $documents = [];
        $map = [
            'photo' => ['column' => 'photo_path', 'type' => 'photo'],
            'birth_certificate' => ['type' => 'birth_certificate'],
            'identity_document' => ['column' => 'identity_document_path', 'type' => 'identity'],
            'diploma' => ['column' => 'diploma_path', 'type' => 'diploma'],
            'employment_contract' => ['type' => 'employment_contract'],
        ];

        foreach ($map as $field => $meta) {
            $file = $files[$field] ?? null;
            if ($this->hasUploadedFile($file)) {
                $stored = $this->storeUploadedFile($file, $meta['type']);
                if (isset($meta['column'])) {
                    $columns[$meta['column']] = $stored['path'];
                }
                $documents[] = $stored + ['document_type' => $meta['type'], 'child_index' => null];
            }
        }

        $childFiles = $files['child_birth_certificates'] ?? null;
        if (is_array($childFiles['name'] ?? null)) {
            for ($i = 0; $i < $childrenCount; $i++) {
                $file = [
                    'name' => $childFiles['name'][$i] ?? '',
                    'type' => $childFiles['type'][$i] ?? '',
                    'tmp_name' => $childFiles['tmp_name'][$i] ?? '',
                    'error' => $childFiles['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $childFiles['size'][$i] ?? 0,
                ];
                if ($this->hasUploadedFile($file)) {
                    $documents[] = $this->storeUploadedFile($file, 'child_birth_certificate') + [
                        'document_type' => 'child_birth_certificate',
                        'child_index' => $i + 1,
                    ];
                }
            }
        }

        return ['columns' => $columns, 'documents' => $documents];
    }

    private function hasUploadedFile(mixed $file): bool
    {
        return is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_uploaded_file((string) ($file['tmp_name'] ?? ''));
    }

    private function storeUploadedFile(array $file, string $type): array
    {
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        $mime = mime_content_type($file['tmp_name']) ?: ($file['type'] ?? 'application/octet-stream');
        if (!in_array($mime, $allowed, true)) {
            throw new RuntimeException('Format de fichier non autorise. Utilisez PDF, JPG, PNG ou WEBP.');
        }
        if ((int) $file['size'] > 5 * 1024 * 1024) {
            throw new RuntimeException('Chaque fichier RH doit faire 5 Mo maximum.');
        }

        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'pdf',
        };
        $dir = BASE_PATH . '/public/uploads/rh/personnel/' . date('Y/m');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $filename = $type . '_' . bin2hex(random_bytes(10)) . '.' . $extension;
        $absolute = $dir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $absolute)) {
            throw new RuntimeException('Impossible d’enregistrer le fichier transmis.');
        }

        return [
            'path' => 'uploads/rh/personnel/' . date('Y/m') . '/' . $filename,
            'original_name' => (string) ($file['name'] ?? $filename),
            'mime_type' => $mime,
            'size_bytes' => (int) ($file['size'] ?? 0),
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
