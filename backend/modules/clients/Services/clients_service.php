<?php

namespace App\Modules\Clients\Services;

use App\Modules\Clients\Repositories\ClientsRepository;

class ClientsService
{
    public function __construct(private ClientsRepository $repository = new ClientsRepository()) {}

    public function getAllClients(): array
    {
        return $this->repository->getAll();
    }

    public function searchClients(string $query): array
    {
        return $this->repository->search($query);
    }

    public function createClient(array $data): array
    {
        // La validation se fera via Validator dans le Controller
        return $this->repository->create($data);
    }
}
