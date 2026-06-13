<?php

namespace App\Modules\Finance\Controllers;

use App\Controllers\BaseController;
use App\Modules\Finance\Services\CaisseService;
use App\Core\Response;

class CaisseController extends BaseController
{
    public function __construct(private CaisseService $service = new CaisseService()) {}

    public function getStatus(): void
    {
        $this->checkPermission('finance.read');
        $user = $this->authenticate();
        $id_agence = $this->getQueryParams()['id_agence'] ?? $user['id_agence'];

        $caisse = $this->service->getCaisseStatus((int)$id_agence);
        Response::success(['caisse' => $caisse]);
    }

    public function listMouvements(): void
    {
        $this->checkPermission('finance.read');
        $user = $this->authenticate();
        $id_agence = $this->getQueryParams()['id_agence'] ?? $user['id_agence'];

        $caisse = $this->service->getCaisseStatus((int)$id_agence);
        
        $filters = $this->getQueryParams();
        $filters['id_caisse'] = $caisse['id'];

        $mouvements = $this->service->listMouvements($filters);
        Response::success(['mouvements' => $mouvements]);
    }

    public function addApprovisionnement(): void
    {
        $this->checkPermission('finance.create');
        $user = $this->authenticate();
        try {
            $mouvement = $this->service->addApprovisionnement($user['id_agence'], $user['id'], $this->getRequestBody());
            Response::success(['mouvement' => $mouvement], 'Approvisionnement enregistré avec succès (Fiche Recette: ' . $mouvement['numero_fiche_recette'] . ')', 201);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function addDecaissement(): void
    {
        $this->checkPermission('finance.create');
        $user = $this->authenticate();
        try {
            $mouvement = $this->service->addDecaissement($user['id_agence'], $user['id'], $this->getRequestBody());
            Response::success(['mouvement' => $mouvement], 'Décaissement enregistré avec succès (Ordre: ' . $mouvement['numero_ordre_decaissement'] . ')', 201);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function addEntree(): void
    {
        $this->checkPermission('finance.create');
        $user = $this->authenticate();
        try {
            $mouvement = $this->service->addEntree($user['id_agence'], $user['id'], $this->getRequestBody());
            Response::success(['mouvement' => $mouvement], 'Entrée de caisse enregistrée avec succès', 201);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
