<?php

namespace App\Modules\Logistique\Services;

use App\Modules\Logistique\Repositories\LogistiqueRepository;

class LogistiqueService
{
    public function __construct(private LogistiqueRepository $repository = new LogistiqueRepository()) {}

    public function listRecords(): array
    {
        return [];
    }
}
