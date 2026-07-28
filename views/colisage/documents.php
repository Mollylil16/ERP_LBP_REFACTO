<?php

declare(strict_types=1);

use App\View\Components\Colisage;

/** @var array<int, array<string, mixed>> $manifests */
/** @var array<int, array<string, mixed>> $parcels */

echo Colisage::documentsPage($manifests, $parcels);
