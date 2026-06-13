<?php

namespace App\Modules\Administration\Services;

use App\Modules\Administration\Repositories\AdministrationRepository;

class AdministrationService
{
    public function __construct(private AdministrationRepository $repository = new AdministrationRepository()) {}

    public function listConfig(): array
    {
        return [];
    }
}
