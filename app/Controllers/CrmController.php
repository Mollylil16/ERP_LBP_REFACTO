<?php

declare(strict_types=1);

namespace App\Controllers;

final class CrmController extends BaseController
{
    public function dashboard(): void
    {
        (new CrmDashboardController())->index();
    }
}
