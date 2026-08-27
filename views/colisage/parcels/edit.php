<?php

declare(strict_types=1);

use App\View\Components\Colisage;

/** @var array<string, mixed> $colis */
/** @var array<int, array<string, mixed>> $clients */
/** @var array<int, array<string, mixed>> $products */
/** @var array<int, array<string, mixed>> $trajets */

echo Colisage::parcelEditPage($colis, $clients, $products, $trajets);
