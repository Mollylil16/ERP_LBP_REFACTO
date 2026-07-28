<?php

declare(strict_types=1);

use App\View\Components\Colisage;

/** @var array<string, mixed> $parcelsData */
/** @var array<string, mixed> $filters */
/** @var array<int, array<string, mixed>> $sites */

$parcels = $parcelsData['items'] ?? [];
$pagination = $parcelsData['pagination'] ?? null;

echo Colisage::autresListPage($parcels, $filters, $pagination);
