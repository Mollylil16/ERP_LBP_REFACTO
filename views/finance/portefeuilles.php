<?php

declare(strict_types=1);

use App\View\Components\Finance;

/** @var array<int, array<string, mixed>> $wallets */
/** @var array<int, array<string, mixed>> $recentTx */

echo Finance::portefeuillesPage($wallets, $recentTx);
