<?php

declare(strict_types=1);

use App\View\Components\Colisage;

/** @var array<int, array<string, mixed>> $sites */
/** @var array<int, array<string, mixed>> $clients */
/** @var array<int, array<string, mixed>> $products */

echo Colisage::createPage($sites, $clients, $products);
