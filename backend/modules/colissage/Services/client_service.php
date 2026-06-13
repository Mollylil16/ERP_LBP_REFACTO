<?php

namespace App\Modules\Colissage\Services;

use App\Modules\Colissage\Repositories\ClientRepository;

class ClientService
{
    public function __construct(private ClientRepository $repository = new ClientRepository()) {}

    public function listClients(array $filters = []): array
    {
        return $this->repository->fetchClients($filters);
    }

    public function getClient(int $id): array
    {
        $client = $this->repository->fetchClientById($id);
        if ($client === null) {
            throw new \RuntimeException('Client introuvable');
        }
        return $client;
    }

    public function createClient(array $payload): array
    {
        if (empty($payload['nom'])) {
            throw new \InvalidArgumentException('Le nom du client est requis');
        }

        return $this->repository->createClient($payload);
    }
}
