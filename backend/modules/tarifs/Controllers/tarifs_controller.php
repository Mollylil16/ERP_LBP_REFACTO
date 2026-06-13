<?php

namespace App\Modules\Tarifs\Controllers;

use App\Controllers\BaseController;
use App\Modules\Tarifs\Services\TarifsService;
use App\Core\Response;
use App\Core\Validator;

class TarifsController extends BaseController
{
    public function __construct(private TarifsService $service = new TarifsService()) {}

    public function index(): void
    {
        $this->authenticate();
        $tarifs = $this->service->getAllTarifs();
        Response::success(['tarifs' => $tarifs]);
    }

    public function create(): void
    {
        $this->authenticate(); // TODO: check permission (ex: admin)
        
        $payload = $this->getRequestBody();
        $validatedData = Validator::validate($payload, [
            'type_tarif' => 'required',
            'nom' => 'required',
            'pays_depart' => 'required',
            'pays_arrivee' => 'required',
            'montant' => 'required|numeric'
        ]);

        $tarif = $this->service->createTarif($validatedData);
        Response::success(['tarif' => $tarif], 'Tarif créé avec succès', 201);
    }

    public function calculer(): void
    {
        $this->authenticate();
        $paysDepart = $_GET['depart'] ?? '';
        $paysArrivee = $_GET['arrivee'] ?? '';
        $typeTarif = $_GET['type'] ?? '';
        $poids = (float)($_GET['poids'] ?? 1.0);

        try {
            $result = $this->service->calculerPrix($paysDepart, $paysArrivee, $typeTarif, $poids);
            Response::success($result);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 404);
        }
    }
}
