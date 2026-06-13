<?php

namespace App\Modules\Finance\Controllers;

use App\Controllers\BaseController;
use App\Modules\Finance\Services\PaiementService;
use App\Core\Response;

class PaiementController extends BaseController
{
    public function __construct(private PaiementService $service = new PaiementService()) {}

    public function listPaiements(): void
    {
        $this->checkPermission('paiements.read');
        $paiements = $this->service->listPaiements($this->getQueryParams());
        Response::success(['paiements' => $paiements]);
    }

    public function createPaiement(): void
    {
        $this->checkPermission('paiements.create');
        try {
            $paiement = $this->service->createPaiement($this->getRequestBody());
            Response::success(['paiement' => $paiement], 'Paiement enregistré', 201);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }
}
