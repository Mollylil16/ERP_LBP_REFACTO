<?php

namespace App\Repositories;

use App\Models\RhEmployee;
use PDO;
use RuntimeException;

class RhPersonnelRepository
{
    public function __construct(private PDO $pdo) {}

    public function paginate(array $filters, int $page = 1, int $perPage = 15): array
    {
        $page = max(1, $page);
        $perPage = max(5, min(50, $perPage));
        [$where, $params] = $this->buildFilters($filters);

        $count = $this->pdo->prepare("SELECT COUNT(*) FROM rh_employees e {$where}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $stmt = $this->pdo->prepare($this->baseSelect() . "
            {$where}
            ORDER BY e.is_active DESC, e.full_name ASC
            LIMIT :limit OFFSET :offset
        ");
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll() ?: [],
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare($this->baseSelect() . ' WHERE e.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $employee = $stmt->fetch();

        return $employee ?: null;
    }

    public function availableForUserAccount(): array
    {
        $stmt = $this->pdo->query($this->baseSelect() . "
            LEFT JOIN users u ON u.rh_employee_id = e.id
            WHERE e.is_active = 1
              AND e.exit_date IS NULL
              AND u.id IS NULL
            ORDER BY e.full_name
        ");

        return $stmt->fetchAll() ?: [];
    }

    public function findForUserAccount(int $id): ?array
    {
        $stmt = $this->pdo->prepare($this->baseSelect() . "
            WHERE e.id = :id
              AND e.is_active = 1
              AND e.exit_date IS NULL
              AND NOT EXISTS (
                  SELECT 1 FROM users u WHERE u.rh_employee_id = e.id
              )
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data, int $actorId, array $uploadColumns = [], array $documents = []): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO rh_employees (
                employee_number, full_name, email, phone, gender, birth_date, birth_place,
                marital_status, address, site, service_id, function_id, status_id,
                cni_number, cnps_number, contract_duration_months, hire_date, start_date,
                father_name, father_phone, mother_name, mother_phone,
                emergency_contact_name, emergency_contact_phone, children_count,
                is_active, created_at
            ) VALUES (
                :employee_number, :full_name, :email, :phone, :gender, :birth_date, :birth_place,
                :marital_status, :address, :site, :service_id, :function_id, :status_id,
                :cni_number, :cnps_number, :contract_duration_months, :hire_date, :start_date,
                :father_name, :father_phone, :mother_name, :mother_phone,
                :emergency_contact_name, :emergency_contact_phone, :children_count,
                1, NOW()
            )
        ");
        $stmt->execute($this->employeeParams($data));
        $id = (int) $this->pdo->lastInsertId();
        if ($uploadColumns !== []) {
            $this->updateDocumentColumns($id, $uploadColumns);
        }
        $this->storeDocuments($id, $documents);
        $this->addHistory($id, [
            'event_type' => 'integration',
            'event_date' => $data['hire_date'] ?: date('Y-m-d'),
            'title' => 'Integration du collaborateur',
            'description' => 'Creation du dossier personnel.',
        ], $actorId);

        return $id;
    }

    public function update(int $id, array $data, array $uploadColumns = [], array $documents = []): void
    {
        $params = $this->employeeParams($data);
        $params['id'] = $id;
        $stmt = $this->pdo->prepare("
            UPDATE rh_employees SET
                employee_number = :employee_number,
                full_name = :full_name,
                email = :email,
                phone = :phone,
                gender = :gender,
                birth_date = :birth_date,
                birth_place = :birth_place,
                marital_status = :marital_status,
                address = :address,
                site = :site,
                service_id = :service_id,
                function_id = :function_id,
                status_id = :status_id,
                cni_number = :cni_number,
                cnps_number = :cnps_number,
                contract_duration_months = :contract_duration_months,
                hire_date = :hire_date,
                start_date = :start_date,
                father_name = :father_name,
                father_phone = :father_phone,
                mother_name = :mother_name,
                mother_phone = :mother_phone,
                emergency_contact_name = :emergency_contact_name,
                emergency_contact_phone = :emergency_contact_phone,
                children_count = :children_count,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute($params);
        if ($uploadColumns !== []) {
            $this->updateDocumentColumns($id, $uploadColumns);
        }
        $this->storeDocuments($id, $documents);
    }

    public function applyMutation(int $id, array $data, int $actorId): void
    {
        $employee = $this->find($id);
        if (!$employee) {
            throw new RuntimeException('Collaborateur introuvable.');
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO rh_employee_mutations (
                    employee_id, effective_date,
                    previous_service_id, new_service_id,
                    previous_function_id, new_function_id,
                    previous_status_id, new_status_id,
                    previous_site, new_site, reason, created_by
                ) VALUES (
                    :employee_id, :effective_date,
                    :previous_service_id, :new_service_id,
                    :previous_function_id, :new_function_id,
                    :previous_status_id, :new_status_id,
                    :previous_site, :new_site, :reason, :created_by
                )
            ");
            $stmt->execute([
                'employee_id' => $id,
                'effective_date' => $data['effective_date'],
                'previous_service_id' => $employee['service_id'],
                'new_service_id' => $data['service_id'],
                'previous_function_id' => $employee['function_id'],
                'new_function_id' => $data['function_id'],
                'previous_status_id' => $employee['status_id'],
                'new_status_id' => $data['status_id'],
                'previous_site' => $employee['site'],
                'new_site' => $data['site'],
                'reason' => $data['reason'],
                'created_by' => $actorId ?: null,
            ]);

            $update = $this->pdo->prepare("
                UPDATE rh_employees
                SET service_id = :service_id, function_id = :function_id,
                    status_id = :status_id, site = :site,
                    start_date = COALESCE(:start_date, start_date), updated_at = NOW()
                WHERE id = :id
            ");
            $update->execute([
                'service_id' => $data['service_id'],
                'function_id' => $data['function_id'],
                'status_id' => $data['status_id'],
                'site' => $data['site'],
                'start_date' => $data['start_date'],
                'id' => $id,
            ]);

            $this->addHistory($id, [
                'event_type' => 'mutation',
                'event_date' => $data['effective_date'],
                'title' => $data['title'],
                'description' => $data['reason'],
                'metadata' => $data,
            ], $actorId);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function exitEmployee(int $id, array $data, int $actorId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE rh_employees
            SET exit_date = :exit_date, exit_reason_id = :exit_reason_id,
                exit_notes = :exit_notes, is_active = 0, updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'exit_date' => $data['exit_date'],
            'exit_reason_id' => $data['exit_reason_id'],
            'exit_notes' => $data['exit_notes'],
            'id' => $id,
        ]);
        $this->addHistory($id, [
            'event_type' => 'sortie',
            'event_date' => $data['exit_date'],
            'title' => 'Sortie du personnel',
            'description' => $data['exit_notes'],
        ], $actorId);
    }

    public function reintegrate(int $id, string $date, int $actorId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE rh_employees
            SET exit_date = NULL, exit_reason_id = NULL, exit_notes = NULL,
                is_active = 1, start_date = :start_date, updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute(['start_date' => $date, 'id' => $id]);
        $this->addHistory($id, [
            'event_type' => 'reintegration',
            'event_date' => $date,
            'title' => 'Reintegration du collaborateur',
            'description' => 'Retour du collaborateur dans les effectifs actifs.',
        ], $actorId);
    }

    public function addHistory(int $id, array $data, int $actorId): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO rh_employee_history (
                employee_id, event_type, event_date, title, description,
                metadata_json, created_by
            ) VALUES (
                :employee_id, :event_type, :event_date, :title, :description,
                :metadata_json, :created_by
            )
        ");
        $stmt->execute([
            'employee_id' => $id,
            'event_type' => $data['event_type'],
            'event_date' => $data['event_date'],
            'title' => $data['title'],
            'description' => $data['description'] ?: null,
            'metadata_json' => isset($data['metadata']) ? json_encode($data['metadata'], JSON_UNESCAPED_UNICODE) : null,
            'created_by' => $actorId ?: null,
        ]);
    }

    public function history(int $id): array
    {
        $stmt = $this->pdo->prepare("
            SELECT h.*, u.full_name AS created_by_name
            FROM rh_employee_history h
            LEFT JOIN users u ON u.id = h.created_by
            WHERE h.employee_id = :id
            ORDER BY h.event_date DESC, h.id DESC
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetchAll() ?: [];
    }

    public function mutations(int $id): array
    {
        $stmt = $this->pdo->prepare("
            SELECT m.*,
                ps.name AS previous_service_name, ns.name AS new_service_name,
                pf.name AS previous_function_name, nf.name AS new_function_name,
                pst.name AS previous_status_name, nst.name AS new_status_name
            FROM rh_employee_mutations m
            LEFT JOIN rh_services ps ON ps.id = m.previous_service_id
            LEFT JOIN rh_services ns ON ns.id = m.new_service_id
            LEFT JOIN rh_functions pf ON pf.id = m.previous_function_id
            LEFT JOIN rh_functions nf ON nf.id = m.new_function_id
            LEFT JOIN rh_statuses pst ON pst.id = m.previous_status_id
            LEFT JOIN rh_statuses nst ON nst.id = m.new_status_id
            WHERE m.employee_id = :id
            ORDER BY m.effective_date DESC, m.id DESC
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetchAll() ?: [];
    }

    public function allMutations(int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));
        $stmt = $this->pdo->prepare("
            SELECT m.*, e.full_name, e.employee_number,
                ps.name AS previous_service_name, ns.name AS new_service_name,
                pf.name AS previous_function_name, nf.name AS new_function_name,
                pst.name AS previous_status_name, nst.name AS new_status_name
            FROM rh_employee_mutations m
            INNER JOIN rh_employees e ON e.id = m.employee_id
            LEFT JOIN rh_services ps ON ps.id = m.previous_service_id
            LEFT JOIN rh_services ns ON ns.id = m.new_service_id
            LEFT JOIN rh_functions pf ON pf.id = m.previous_function_id
            LEFT JOIN rh_functions nf ON nf.id = m.new_function_id
            LEFT JOIN rh_statuses pst ON pst.id = m.previous_status_id
            LEFT JOIN rh_statuses nst ON nst.id = m.new_status_id
            ORDER BY m.effective_date DESC, m.id DESC
            LIMIT :limit
        ");
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function movements(int $limit = 250): array
    {
        $limit = max(1, min(500, $limit));
        $stmt = $this->pdo->prepare("
            SELECT h.*, e.full_name, e.employee_number, e.is_active
            FROM rh_employee_history h
            INNER JOIN rh_employees e ON e.id = h.employee_id
            WHERE h.event_type IN ('integration', 'sortie', 'reintegration')
            ORDER BY h.event_date DESC, h.id DESC
            LIMIT :limit
        ");
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function options(): array
    {
        return [
            'services' => $this->pairs('rh_services'),
            'functions' => $this->pairs('rh_functions'),
            'statuses' => $this->pairs('rh_statuses', 'sort_order, name'),
            'exitReasons' => $this->pairs('rh_exit_reasons', 'sort_order, name'),
            'documentTypes' => $this->pairs('rh_document_types', 'sort_order, name'),
        ];
    }

    public function documents(int $employeeId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM rh_employee_documents
            WHERE employee_id = :employee_id
            ORDER BY created_at DESC, id DESC
        ");
        $stmt->execute(['employee_id' => $employeeId]);
        return $stmt->fetchAll() ?: [];
    }

    public function toModel(array $row): RhEmployee
    {
        return new RhEmployee(
            id: (int) $row['id'],
            employeeNumber: $row['employee_number'],
            fullName: (string) $row['full_name'],
            email: $row['email'],
            phone: $row['phone'],
            serviceId: $row['service_id'] !== null ? (int) $row['service_id'] : null,
            functionId: $row['function_id'] !== null ? (int) $row['function_id'] : null,
            statusId: $row['status_id'] !== null ? (int) $row['status_id'] : null,
            hireDate: $row['hire_date'],
            startDate: $row['start_date'],
            exitDate: $row['exit_date'],
            isActive: (bool) $row['is_active'],
        );
    }


    private function updateDocumentColumns(int $id, array $columns): void
    {
        $allowed = ['photo_path', 'identity_document_path', 'diploma_path'];
        $sets = [];
        $params = ['id' => $id];
        foreach ($columns as $column => $value) {
            if (!in_array($column, $allowed, true)) {
                continue;
            }
            $sets[] = "{$column} = :{$column}";
            $params[$column] = $value;
        }
        if ($sets === []) {
            return;
        }
        $sql = 'UPDATE rh_employees SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    private function storeDocuments(int $employeeId, array $documents): void
    {
        if ($documents === []) {
            return;
        }
        $stmt = $this->pdo->prepare("
            INSERT INTO rh_employee_documents (
                employee_id, document_type, child_index, original_name,
                stored_path, mime_type, size_bytes, created_at
            ) VALUES (
                :employee_id, :document_type, :child_index, :original_name,
                :stored_path, :mime_type, :size_bytes, NOW()
            )
        ");
        foreach ($documents as $document) {
            $stmt->execute([
                'employee_id' => $employeeId,
                'document_type' => $document['document_type'],
                'child_index' => $document['child_index'],
                'original_name' => $document['original_name'],
                'stored_path' => $document['path'],
                'mime_type' => $document['mime_type'],
                'size_bytes' => $document['size_bytes'],
            ]);
        }
    }

    private function baseSelect(): string
    {
        return "
            SELECT e.*,
                COALESCE(s.name, 'Service non renseigne') AS service_name,
                COALESCE(f.name, 'Fonction non renseignee') AS function_name,
                COALESCE(st.name, 'Statut non renseigne') AS status_name,
                er.name AS exit_reason_name
            FROM rh_employees e
            LEFT JOIN rh_services s ON s.id = e.service_id
            LEFT JOIN rh_functions f ON f.id = e.function_id
            LEFT JOIN rh_statuses st ON st.id = e.status_id
            LEFT JOIN rh_exit_reasons er ON er.id = e.exit_reason_id
        ";
    }

    private function buildFilters(array $filters): array
    {
        $conditions = [];
        $params = [];
        if (($filters['q'] ?? '') !== '') {
            $conditions[] = '(e.full_name LIKE :q_name OR e.employee_number LIKE :q_number OR e.email LIKE :q_email)';
            $search = '%' . $filters['q'] . '%';
            $params['q_name'] = $search;
            $params['q_number'] = $search;
            $params['q_email'] = $search;
        }
        foreach (['service_id', 'function_id', 'status_id'] as $key) {
            if (!empty($filters[$key])) {
                $conditions[] = "e.{$key} = :{$key}";
                $params[$key] = (int) $filters[$key];
            }
        }
        if (($filters['scope'] ?? '') === 'active') {
            $conditions[] = 'e.is_active = 1 AND e.exit_date IS NULL';
        } elseif (($filters['scope'] ?? '') === 'inactive') {
            $conditions[] = '(e.is_active = 0 OR e.exit_date IS NOT NULL)';
        }

        return [$conditions ? 'WHERE ' . implode(' AND ', $conditions) : '', $params];
    }

    private function employeeParams(array $data): array
    {
        $keys = [
            'employee_number', 'full_name', 'email', 'phone', 'gender', 'birth_date',
            'birth_place', 'marital_status', 'address', 'site', 'service_id', 'function_id',
            'status_id', 'cni_number', 'cnps_number', 'contract_duration_months',
            'hire_date', 'start_date', 'father_name', 'father_phone', 'mother_name',
            'mother_phone', 'emergency_contact_name', 'emergency_contact_phone', 'children_count',
        ];
        $params = [];
        foreach ($keys as $key) {
            $params[$key] = $data[$key] ?? null;
        }
        return $params;
    }

    private function pairs(string $table, string $order = 'name'): array
    {
        return $this->pdo->query("SELECT id, name FROM {$table} WHERE is_active = 1 ORDER BY {$order}")->fetchAll() ?: [];
    }
}
