<?php

namespace App\Modules\Colissage\Controllers;

use App\Controllers\BaseController;
use App\Modules\Colissage\Services\ColisService;
use App\Core\Response;

class ColisController extends BaseController
{
    public function __construct(private ColisService $service = new ColisService()) {}

    public function listColis(): void
    {
        $this->checkPermission('colis.read');
        $colis = $this->service->listColis($this->getQueryParams());
        Response::success(['colis' => $colis]);
    }

    public function getColis(int $id): void
    {
        $this->checkPermission('colis.read');
        try {
            $colis = $this->service->getColis($id);
            Response::success(['colis' => $colis]);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 404);
        }
    }

    public function createColis(): void
    {
        $this->checkPermission('colis.create');
        $user = $this->authenticate();
        $payload = $this->getRequestBody();

        try {
            $operatorService = new \App\Modules\Rh\Services\OperatorService();
            $code = $payload['operator_code'] ?? null;
            
            if (!$code) {
                Response::error('Le code secret (mot de passe) de l\'opérateur est requis pour cette agence.', 400);
            }
            
            // Authentifier l'opérateur physique
            $operator = $operatorService->authenticateOperateur($user['id_agence'], $code);
            $payload['id_createur'] = $user['id'];
            $payload['id_operateur'] = $operator['id'];

            $codeAgence = $user['code_agence'] ?? 'CI'; // Default to CI if not set
            $colis = $this->service->createColis($payload, $codeAgence);
            Response::success(['colis' => $colis], 'Colis créé avec succès : ' . $colis['numero_tracking'], 201);
        } catch (\RuntimeException | \InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    public function updateStatut(int $id): void
    {
        $this->checkPermission('colis.update');
        $payload = $this->getRequestBody();

        try {
            $colis = $this->service->updateStatut($id, $payload['statut'] ?? '');
            Response::success(['colis' => $colis], 'Statut mis à jour');
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 404);
        }
    }

    public function retraitColis(int $id): void
    {
        $this->checkPermission('colis.update');
        $payload = $this->getRequestBody();

        try {
            $colis = $this->service->retraitColis($id, $payload);
            Response::success(['colis' => $colis], 'Colis retiré avec succès');
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 404);
        }
    }
}
