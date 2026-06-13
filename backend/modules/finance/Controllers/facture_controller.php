<?php

namespace App\Modules\Finance\Controllers;

use App\Controllers\BaseController;
use App\Modules\Finance\Services\FactureService;
use App\Core\Response;

class FactureController extends BaseController
{
    public function __construct(private FactureService $service = new FactureService()) {}

    public function listFactures(): void
    {
        $this->checkPermission('factures.read');
        $factures = $this->service->listFactures($this->getQueryParams());
        Response::success(['factures' => $factures]);
    }

    public function getFacture(int $id): void
    {
        $this->checkPermission('factures.read');
        try {
            $facture = $this->service->getFacture($id);
            Response::success(['facture' => $facture]);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 404);
        }
    }

    public function createFacture(): void
    {
        $this->checkPermission('factures.create');
        $user = $this->authenticate();
        $payload = $this->getRequestBody();

        try {
            $operatorService = new \App\Modules\Rh\Services\OperatorService();
            $code = $payload['operator_code'] ?? null;
            
            if (!$code) {
                Response::error('Le code secret (mot de passe) de l\'opérateur est requis pour cette agence.', 400);
            }
            
            $operator = $operatorService->authenticateOperateur($user['id_agence'], $code);
            $payload['id_createur'] = $user['id'];
            $payload['id_operateur'] = $operator['id'];

            $facture = $this->service->createFacture($payload);
            Response::success(['facture' => $facture], 'Facture créée', 201);
        } catch (\RuntimeException | \InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }
}
