<?php

namespace App\Modules\Litiges\Controllers;

use App\Controllers\BaseController;
use App\Modules\Litiges\Services\LitigesService;
use App\Core\Response;
use App\Core\Validator;

class LitigesController extends BaseController
{
    public function __construct(private LitigesService $service = new LitigesService()) {}

    public function index(): void
    {
        $this->authenticate();
        $litiges = $this->service->getAllLitiges();
        Response::success(['litiges' => $litiges]);
    }

    public function create(): void
    {
        $this->authenticate();
        $payload = $this->getRequestBody();

        $validatedData = Validator::validate($payload, [
            'id_client' => 'required|numeric',
            'type_litige' => 'required',
            'description' => 'required'
        ]);

        $litige = $this->service->createLitige($validatedData);
        Response::success(['litige' => $litige], 'Litige déclaré avec succès', 201);
    }
}
