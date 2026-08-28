<?php

declare(strict_types=1);

use App\View\Components\Logistique;

/** @var array<int, array<string, mixed>> $sites */
/** @var string $period */
/** @var string $dateStart */
/** @var string $dateEnd */
/** @var int $selectedAgenceId */
/** @var array<int, array<string, mixed>> $parcels */
/** @var array<string, mixed> $kpis */

echo Logistique::colisageSuiviPage($sites, $period, $dateStart, $dateEnd, $selectedAgenceId, $parcels, $kpis);
