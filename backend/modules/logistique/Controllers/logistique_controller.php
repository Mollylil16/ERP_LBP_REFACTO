<?php

namespace App\Modules\Logistique\Controllers;

use App\Controllers\BaseController;
use App\Modules\Logistique\Services\LogistiqueService;
use App\Core\Response;

class LogistiqueController extends BaseController
{
    public function __construct(private LogistiqueService $service = new LogistiqueService()) {}

    public function index(): void
    {
        Response::success(['module' => 'logistique', 'message' => 'Logistique module skeleton']);
    }
}
