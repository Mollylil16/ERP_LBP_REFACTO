<?php

namespace App\Modules\Logistique\Controllers;

use App\Controllers\BaseController;
use App\Modules\Logistique\Services\ExpeditionService;
use App\Core\Response;

class ExpeditionController extends BaseController
{
    public function __construct(private ExpeditionService $service = new ExpeditionService()) {}

    public function listExpeditions(): void
    {
        $this->checkPermission('expeditions.read');
        $expeditions = $this->service->listExpeditions($this->getQueryParams());
        Response::success(['expeditions' => $expeditions]);
    }

    public function getExpedition(int $id): void
    {
        $this->checkPermission('expeditions.read');
        try {
            $expedition = $this->service->getExpedition($id);
            Response::success(['expedition' => $expedition]);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 404);
        }
    }

    public function createExpedition(): void
    {
        $this->checkPermission('expeditions.create');
        try {
            $expedition = $this->service->createExpedition($this->getRequestBody());
            Response::success(['expedition' => $expedition], 'Expédition créée', 201);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    public function updateStatut(int $id): void
    {
        $this->checkPermission('expeditions.update');
        $payload = $this->getRequestBody();

        try {
            $expedition = $this->service->updateStatut($id, $payload['statut'] ?? '');
            Response::success(['expedition' => $expedition], 'Statut mis à jour');
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 404);
        }
    }
}
