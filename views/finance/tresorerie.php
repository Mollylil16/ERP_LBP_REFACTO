<?php

declare(strict_types=1);

use App\View\Components\Finance;

/** @var float $totalEncaissementsPrevus */
/** @var float $totalDecaissementsPrevus */
/** @var float $soldeTrésorerieEstime */

echo Finance::tresoreriePage($totalEncaissementsPrevus, $totalDecaissementsPrevus, $soldeTrésorerieEstime);
