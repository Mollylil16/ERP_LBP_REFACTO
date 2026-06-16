<?php

namespace App\Services;

use App\Repositories\RhContractRepository;
use InvalidArgumentException;

class RhContractService
{
    public function __construct(private RhContractRepository $repository) {}

    public function list(array $params): array
    {
        $page = max(1, (int)($params['page'] ?? 1));
        return $this->repository->paginate($page, 20, $params);
    }

    public function save(array $data, array $allowances = []): int
    {
        $this->validate($data);

        if (empty($data['id'])) {
            $id = $this->repository->insert($data, $allowances);
        } else {
            $id = (int)$data['id'];
            $this->repository->update($id, $data, $allowances);
        }

        // Si le contrat est actif, on passe les anciens contrats de l'employe en "terminated"
        if (($data['status'] ?? 'active') === 'active') {
            $this->repository->terminatePreviousContracts((int)$data['employee_id'], $id);
        }

        return $id;
    }

    private function validate(array $data): void
    {
        if (empty($data['employee_id'])) {
            throw new InvalidArgumentException("L'employé est obligatoire.");
        }
        if (empty($data['contract_type'])) {
            throw new InvalidArgumentException("Le type de contrat est obligatoire.");
        }
        if (empty($data['start_date'])) {
            throw new InvalidArgumentException("La date de début est obligatoire.");
        }
        if (!empty($data['end_date']) && $data['end_date'] < $data['start_date']) {
            throw new InvalidArgumentException("La date de fin ne peut pas être antérieure à la date de début.");
        }
        if ((float)($data['base_salary'] ?? 0) < 0) {
            throw new InvalidArgumentException("Le salaire de base ne peut pas être négatif.");
        }
    }

    public function get(int $id): ?array
    {
        return $this->repository->find($id);
    }

    public function employeeOptions(): array
    {
        return $this->repository->activeEmployeeOptions();
    }
}
