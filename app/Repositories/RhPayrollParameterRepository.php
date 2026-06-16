<?php

namespace App\Repositories;

use PDO;

class RhPayrollParameterRepository
{
    public function __construct(private PDO $pdo) {}

    public function paginate(int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        
        $countSql = "SELECT COUNT(*) FROM rh_payroll_parameters";
        $total = (int) $this->pdo->query($countSql)->fetchColumn();

        $sql = "SELECT * FROM rh_payroll_parameters ORDER BY year DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM rh_payroll_parameters WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByYear(int $year): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM rh_payroll_parameters WHERE year = ?");
        $stmt->execute([$year]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function insert(array $data): int
    {
        $sql = "INSERT INTO rh_payroll_parameters 
                (year, smig, cnps_ceiling, cnps_employee_rate, cnps_employer_rate, cmu_employee_rate, cmu_employer_rate, cn_rate)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['year'],
            $data['smig'] ?? 75000,
            $data['cnps_ceiling'] ?? 1647315,
            $data['cnps_employee_rate'] ?? 3.2,
            $data['cnps_employer_rate'] ?? 7.7,
            $data['cmu_employee_rate'] ?? 2.0,
            $data['cmu_employer_rate'] ?? 2.0,
            $data['cn_rate'] ?? 1.5,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $sql = "UPDATE rh_payroll_parameters SET 
                year = ?, smig = ?, cnps_ceiling = ?, cnps_employee_rate = ?, cnps_employer_rate = ?, 
                cmu_employee_rate = ?, cmu_employer_rate = ?, cn_rate = ?, updated_at = NOW()
                WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['year'],
            $data['smig'],
            $data['cnps_ceiling'],
            $data['cnps_employee_rate'],
            $data['cnps_employer_rate'],
            $data['cmu_employee_rate'],
            $data['cmu_employer_rate'],
            $data['cn_rate'],
            $id
        ]);
    }
}
