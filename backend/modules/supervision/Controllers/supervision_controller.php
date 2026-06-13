<?php

namespace App\Modules\Supervision\Controllers;

use App\Controllers\BaseController;
use App\Modules\Supervision\Services\SupervisionService;
use App\Core\Response;

class SupervisionController extends BaseController
{
    public function __construct(private SupervisionService $service = new SupervisionService()) {}

    public function getKpisConsolides(): void
    {
        $user = $this->authenticate();
        // Optionnel: On peut créer une permission 'supervision.read'
        
        try {
            $kpis = $this->service->getKpisConsolides($user);
            Response::success(['kpis' => $kpis]);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function signalerAnomalie(): void
    {
        $user = $this->authenticate();
        try {
            $signalement = $this->service->signalerAnomalie($this->getRequestBody(), $user['id']);
            Response::success(['signalement' => $signalement], 'Anomalie signalée avec succès', 201);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function demanderJustification(): void
    {
        $user = $this->authenticate();
        try {
            $justification = $this->service->demanderJustification($this->getRequestBody(), $user['id']);
            Response::success(['justification' => $justification], 'Demande de justification envoyée', 201);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function annoterOperation(): void
    {
        $user = $this->authenticate();
        try {
            $annotation = $this->service->annoterOperation($this->getRequestBody(), $user['id']);
            Response::success(['annotation' => $annotation], 'Annotation ajoutée (visible uniquement par la direction)', 201);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
