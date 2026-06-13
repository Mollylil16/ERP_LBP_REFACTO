<?php

namespace App\Models;

class RhEmployee
{
    public function __construct(
        public readonly ?int $id,
        public readonly ?string $employeeNumber,
        public readonly string $fullName,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly ?int $serviceId,
        public readonly ?int $functionId,
        public readonly ?int $statusId,
        public readonly ?string $hireDate,
        public readonly ?string $startDate,
        public readonly ?string $exitDate,
        public readonly bool $isActive,
    ) {}
}
