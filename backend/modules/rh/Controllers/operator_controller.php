<?php

namespace App\Modules\Rh\Controllers;

use App\Controllers\BaseController;
use App\Modules\Rh\Services\OperatorService;
use App\Core\Response;

class OperatorController extends BaseController
{
    public function __construct(private OperatorService $service = new OperatorService()) {}

    public function listOperateurs(): void
    {
        $this->checkPermission('users.read');
        $user = $this->authenticate(); // pour récupérer l'agence de session (ou id_agence)
        
        $agenceId = $this->getQueryParams()['id_agence'] ?? $user['id_agence'] ?? 0;
        if ($agenceId <= 0) {
            Response::error('L’agence est requise', 400);
        }

        $operateurs = $this->service->listOperateurs((int)$agenceId);
        Response::success(['operateurs' => $operateurs]);
    }

    public function createOperateur(): void
    {
        $this->checkPermission('users.create');
        $user = $this->authenticate();
        
        $payload = $this->getRequestBody();
        if (empty($payload['id_agence'])) {
            $payload['id_agence'] = $user['id_agence'];
        }

        try {
            $operateur = $this->service->createOperateur($payload);
            Response::success(['operateur' => $operateur], 'Opérateur créé', 201);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    public function toggleOperateur(int $id): void
    {
        $this->checkPermission('users.update');
        try {
            $operateur = $this->service->toggleOperateur($id);
            Response::success(['operateur' => $operateur], 'Statut modifié');
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
