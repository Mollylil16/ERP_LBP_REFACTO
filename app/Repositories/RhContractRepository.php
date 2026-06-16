<?php

namespace App\Repositories;

use PDO;

class RhContractRepository
{
    public function __construct(private PDO $pdo) {}

    public function paginate(int $page, int $perPage, array $filters = []): array
    {
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT c.*, e.full_name as employee_name, e.employee_number
                FROM rh_contracts c
                JOIN rh_employees e ON c.employee_id = e.id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND (e.full_name LIKE :search OR e.employee_number LIKE :search)";
            $params['search'] = "%" . $filters['search'] . "%";
        }
        if (!empty($filters['status'])) {
            $sql .= " AND c.status = :status";
            $params['status'] = $filters['status'];
        }

        $countSql = "SELECT COUNT(*) FROM ($sql) as t";
        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $sql .= " ORDER BY c.created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(":$k", $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'data' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*, e.full_name as employee_name 
            FROM rh_contracts c 
            JOIN rh_employees e ON c.employee_id = e.id 
            WHERE c.id = ?
        ");
        $stmt->execute([$id]);
        $contract = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$contract) return null;

        $stmt = $this->pdo->prepare("SELECT * FROM rh_contract_allowances WHERE contract_id = ?");
        $stmt->execute([$id]);
        $contract['allowances'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $contract;
    }

    public function findActiveByEmployee(int $employeeId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id FROM rh_contracts WHERE employee_id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$employeeId]);
        $id = $stmt->fetchColumn();
        return $id ? $this->find((int)$id) : null;
    }

    public function insert(array $data, array $allowances = []): int
    {
        $sql = "INSERT INTO rh_contracts (employee_id, contract_type, start_date, end_date, trial_end_date, base_salary, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['employee_id'],
            $data['contract_type'],
            $data['start_date'],
            $data['end_date'] ?: null,
            $data['trial_end_date'] ?: null,
            $data['base_salary'] ?: 0,
            $data['status'] ?? 'active'
        ]);
        $id = (int)$this->pdo->lastInsertId();

        $this->saveAllowances($id, $allowances);
        return $id;
    }

    public function update(int $id, array $data, array $allowances = []): void
    {
        $sql = "UPDATE rh_contracts SET 
                employee_id = ?, contract_type = ?, start_date = ?, end_date = ?, trial_end_date = ?, base_salary = ?, status = ?, updated_at = NOW()
                WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['employee_id'],
            $data['contract_type'],
            $data['start_date'],
            $data['end_date'] ?: null,
            $data['trial_end_date'] ?: null,
            $data['base_salary'] ?: 0,
            $data['status'],
            $id
        ]);

        $this->saveAllowances($id, $allowances);
    }

    private function saveAllowances(int $contractId, array $allowances): void
    {
        $this->pdo->prepare("DELETE FROM rh_contract_allowances WHERE contract_id = ?")->execute([$contractId]);
        if (empty($allowances)) return;

        $sql = "INSERT INTO rh_contract_allowances (contract_id, name, amount, is_taxable) VALUES (?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        foreach ($allowances as $a) {
            if (empty($a['name']) || empty($a['amount'])) continue;
            $stmt->execute([
                $contractId,
                $a['name'],
                $a['amount'],
                !empty($a['is_taxable']) ? 1 : 0
            ]);
        }
    }

    public function terminatePreviousContracts(int $employeeId, int $excludeContractId): void
    {
        $stmt = $this->pdo->prepare("UPDATE rh_contracts SET status = 'terminated', end_date = COALESCE(end_date, CURRENT_DATE) WHERE employee_id = ? AND id != ? AND status = 'active'");
        $stmt->execute([$employeeId, $excludeContractId]);
    }
}
