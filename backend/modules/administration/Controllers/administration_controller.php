<?php

namespace App\Modules\Administration\Controllers;

use App\Controllers\BaseController;
use App\Modules\Administration\Services\AdministrationService;
use App\Core\Response;

class AdministrationController extends BaseController
{
    public function __construct(private AdministrationService $service = new AdministrationService()) {}

    public function index(): void
    {
        Response::success(['module' => 'administration', 'message' => 'Administration module skeleton']);
    }
}
