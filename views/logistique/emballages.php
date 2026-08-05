<?php

declare(strict_types=1);

use App\View\Components\Logistique;

/** @var array<int, array<string, mixed>> $stocks */
/** @var array<int, array<string, mixed>> $mouvements */
/** @var array<int, array<string, mixed>> $sites */
/** @var int|null $selectedAgenceId */

echo Logistique::emballagesPage($stocks, $mouvements, $sites, $selectedAgenceId);
