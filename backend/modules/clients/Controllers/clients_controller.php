<?php

namespace App\Modules\Clients\Controllers;

use App\Controllers\BaseController;
use App\Modules\Clients\Services\ClientsService;
use App\Core\Response;
use App\Core\Validator;

class ClientsController extends BaseController
{
    public function __construct(private ClientsService $service = new ClientsService()) {}

    public function index(): void
    {
        $this->authenticate(); // S'assurer que l'utilisateur est logué
        
        $query = $_GET['search'] ?? null;
        if ($query) {
            $clients = $this->service->searchClients($query);
        } else {
            $clients = $this->service->getAllClients();
        }

        Response::success(['clients' => $clients]);
    }

    public function create(): void
    {
        $this->authenticate();
        $payload = $this->getRequestBody();

        $validatedData = Validator::validate($payload, [
            'nom' => 'required'
            // email => email (optionnel, on l'ajoute si nécessaire)
        ]);

        $client = $this->service->createClient($validatedData);
        Response::success(['client' => $client], 'Client créé avec succès', 201);
    }
}
