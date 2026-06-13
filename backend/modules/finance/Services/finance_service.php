<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Repositories\FinanceRepository;

class FinanceService
{
    public function __construct(private FinanceRepository $repository = new FinanceRepository()) {}

    public function listOverview(): array
    {
        return [];
    }
}
