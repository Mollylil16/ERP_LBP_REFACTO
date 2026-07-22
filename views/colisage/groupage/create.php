<?php

declare(strict_types=1);

use App\View\Components\Colisage;

/** @var array<int, array<string, mixed>> $sites */
/** @var string $defaultDepart */

echo Colisage::groupageCreatePage($sites, $defaultDepart);
