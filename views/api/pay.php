<?php

declare(strict_types=1);

use App\View\Components\Finance;

/**
 * @var App\Models\Finance\Facture $facture
 * @var array<string, mixed> $colis
 * @var array<string, mixed> $client
 */

echo Finance::publicPayPage($facture, $colis, $client);
