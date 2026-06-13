<?php

namespace App\Modules\Colissage\Controllers;

use App\Controllers\BaseController;
use App\Modules\Colissage\Services\ClientService;
use App\Core\Response;

class ClientController extends BaseController
{
    public function __construct(private ClientService $service = new ClientService()) {}

    public function listClients(): void
    {
        $this->checkPermission('clients.read');
        $clients = $this->service->listClients($this->getQueryParams());
        Response::success(['clients' => $clients]);
    }

    public function getClient(int $id): void
    {
        $this->checkPermission('clients.read');
        try {
            $client = $this->service->getClient($id);
            Response::success(['client' => $client]);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 404);
        }
    }

    public function createClient(): void
    {
        $this->checkPermission('clients.create');
        try {
            $client = $this->service->createClient($this->getRequestBody());
            Response::success(['client' => $client], 'Client créé', 201);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }
}
