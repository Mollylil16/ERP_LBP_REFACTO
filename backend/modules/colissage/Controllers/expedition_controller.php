<?php

namespace App\Modules\Colissage\Controllers;

use App\Controllers\BaseController;
use App\Modules\Colissage\Services\ExpeditionService;
use App\Core\Response;

class ExpeditionController extends BaseController
{
    public function __construct(private ExpeditionService $service = new ExpeditionService()) {}

    public function listExpeditions(): void
    {
        $this->checkPermission('colis.read');
        $expeditions = $this->service->listExpeditions($this->getQueryParams());
        Response::success(['expeditions' => $expeditions]);
    }

    public function createExpedition(): void
    {
        $this->checkPermission('colis.create');
        try {
            $expedition = $this->service->createExpedition($this->getRequestBody());
            Response::success(['expedition' => $expedition], 'Expédition créée', 201);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    public function addGpsTracking(int $id): void
    {
        // Seuls les agents d'exploitation peuvent mettre à jour la position
        $this->checkPermission('logistics.update');
        try {
            $tracking = $this->service->addGpsTracking($id, $this->getRequestBody());
            Response::success(['tracking' => $tracking], 'Position mise à jour sur la carte');
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    public function getGpsTracking(int $id): void
    {
        $this->checkPermission('colis.read');
        try {
            $tracking = $this->service->getGpsTracking($id);
            Response::success(['tracking' => $tracking]);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }
}
