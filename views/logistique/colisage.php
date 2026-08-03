<?php

declare(strict_types=1);

use App\View\Components\Logistique;

/** @var array<int, array<string, mixed>> $sites */
/** @var string $selectedDate */
/** @var int $selectedAgenceId */
/** @var array<int, array<string, mixed>> $parcels */
/** @var array<string, mixed> $kpis */

echo Logistique::colisageSuiviPage($sites, $selectedDate, $selectedAgenceId, $parcels, $kpis);
