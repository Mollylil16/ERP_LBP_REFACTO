<?php

namespace App\Modules\Finance\Controllers;

use App\Controllers\BaseController;
use App\Modules\Finance\Services\PointCaisseService;
use App\Core\Response;
use App\Core\Auth;

class PointCaisseController extends BaseController
{
    public function __construct(private PointCaisseService $service = new PointCaisseService()) {}

    public function listPoints(): void
    {
        $this->checkPermission('finance.read');
        $filters = $this->getQueryParams();
        $user = $this->authenticate();

        if (!Auth::hasPermission('*', 'finance.global')) {
            $filters['id_agence'] = $user['id_agence'];
        }

        $points = $this->service->listPointsCaisse($filters);
        Response::success(['points' => $points]);
    }

    public function createPoint(): void
    {
        $this->checkPermission('finance.create');
        $user = $this->authenticate();
        $payload = $this->getRequestBody();

        $payload['id_caissiere'] = $user['id'];
        if (empty($payload['id_agence'])) {
            $payload['id_agence'] = $user['id_agence'];
        }

        try {
            $point = $this->service->createPointCaisse($payload);
            Response::success(['point' => $point], 'Point de caisse soumis avec succès', 201);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    public function validerPoint(int $id): void
    {
        // Seule la caissière principale / admin peut valider
        $this->checkPermission('finance.validate');
        $user = $this->authenticate();
        $payload = $this->getRequestBody();

        $action = $payload['action'] ?? 'VALIDE'; // VALIDE ou REJETE

        try {
            $point = $this->service->validerPointCaisse($id, $user['id'], $action);
            Response::success(['point' => $point], 'Point de caisse traité');
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }
}
