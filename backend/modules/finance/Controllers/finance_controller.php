<?php

namespace App\Modules\Finance\Controllers;

use App\Controllers\BaseController;
use App\Modules\Finance\Services\FinanceService;
use App\Core\Response;

class FinanceController extends BaseController
{
    public function __construct(private FinanceService $service = new FinanceService()) {}

    public function index(): void
    {
        Response::success(['module' => 'finance', 'message' => 'Finance module skeleton']);
    }
}
