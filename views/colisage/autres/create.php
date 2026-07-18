<?php

declare(strict_types=1);

use App\View\Components\Colisage;

/** @var array<int, array<string, mixed>> $sites */
/** @var array<int, array<string, mixed>> $clients */
/** @var array<int, array<string, mixed>> $products */
/** @var float $eur_to_xof_rate */

echo Colisage::autresCreatePage($sites, $clients, $products, $eur_to_xof_rate);
