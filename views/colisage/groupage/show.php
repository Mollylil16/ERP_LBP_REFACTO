<?php

declare(strict_types=1);

use App\View\Components\Colisage;

/** @var array<string, mixed> $exp */
/** @var array<int, array<string, mixed>> $availableParcels */

echo Colisage::groupageShowPage($exp, $availableParcels);
