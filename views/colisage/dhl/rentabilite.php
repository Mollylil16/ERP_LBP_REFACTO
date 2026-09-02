<?php

declare(strict_types=1);

use App\View\Components\Colisage;

/** @var array<string, mixed> $data */
/** @var array<string, mixed> $filters */
/** @var array<int, array<string, mixed>> $sites */

echo Colisage::dhlRentabilitePage($data, $filters, $sites);
