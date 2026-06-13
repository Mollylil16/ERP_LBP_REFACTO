<?php

namespace App\Modules\Colissage\Controllers;

use App\Controllers\BaseController;
use App\Modules\Colissage\Services\InventaireService;
use App\Core\Response;

class InventaireController extends BaseController
{
    public function __construct(private InventaireService $service = new InventaireService()) {}

    public function listInventaires(): void
    {
        $this->checkPermission('colis.read');
        $filters = $this->getQueryParams();
        
        // Scope : Si l'utilisateur n'est pas global, forcer son id_agence
        $user = $this->authenticate();
        if (!\App\Core\Auth::hasPermission('*', 'inventaires.global')) {
            $filters['id_agence'] = $user['id_agence'];
        }

        $inventaires = $this->service->listInventaires($filters);
        Response::success(['inventaires' => $inventaires]);
    }

    public function createInventaire(): void
    {
        $this->checkPermission('colis.create');
        $user = $this->authenticate();
        $payload = $this->getRequestBody();
        
        $payload['id_createur'] = $user['id'];
        if (empty($payload['id_agence'])) {
            $payload['id_agence'] = $user['id_agence'];
        }

        try {
            $inventaire = $this->service->createInventaire($payload);
            Response::success(['inventaire' => $inventaire], 'Inventaire créé', 201);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }
}
