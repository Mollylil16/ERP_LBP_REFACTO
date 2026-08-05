<?php

declare(strict_types=1);

use App\View\Components\Finance;

/** @var array<int, array<string, mixed>> $landedCosts */
/** @var array<int, array<string, mixed>> $trajets */

echo Finance::coutsApprochePage($landedCosts, $trajets);
