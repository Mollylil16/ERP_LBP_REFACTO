<?php

declare(strict_types=1);

namespace App\Controllers;

final class ModuleDashboardController extends BusinessModuleController
{
    public function employee(): void
    {
        $this->redirect('/espace-employe/dashboard');
    }
}
