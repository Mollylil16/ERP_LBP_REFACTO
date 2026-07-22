<?php

declare(strict_types=1);

use App\View\Components\Colisage;

/** @var float $tauxChangeEur */
/** @var array<int, array<string, mixed>> $devisesRates */
/** @var array<string, mixed> $allSettings */

echo Colisage::settingsPage($tauxChangeEur, $devisesRates, $allSettings);
