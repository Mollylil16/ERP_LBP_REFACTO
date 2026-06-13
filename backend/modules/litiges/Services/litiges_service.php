<?php

namespace App\Modules\Litiges\Services;

use App\Modules\Litiges\Repositories\LitigesRepository;

class LitigesService
{
    public function __construct(private LitigesRepository $repository = new LitigesRepository()) {}

    public function getAllLitiges(): array
    {
        return $this->repository->getAll();
    }

    public function createLitige(array $data): array
    {
        return $this->repository->create($data);
    }
}
