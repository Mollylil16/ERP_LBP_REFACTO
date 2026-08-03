<?php

declare(strict_types=1);

use App\View\Components\Colisage;

/** @var array<string, mixed> $trajet */
/** @var array<int, array<string, mixed>> $sites */
/** @var array<int, array<string, mixed>> $clients */
/** @var array<int, array<string, mixed>> $products */
/** @var float $tauxChangeEur */

echo Colisage::operationSaisiePage($trajet, $sites, $clients, $products, $tauxChangeEur);
