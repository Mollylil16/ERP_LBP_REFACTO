<?php

declare(strict_types=1);

use App\View\Components\Colisage;

/** @var array<int, array<string, mixed>> $tonnageData */
/** @var array<int, array<string, mixed>> $caData */
/** @var array<int, array<string, mixed>> $delaiData */
/** @var string $dateDebut */
/** @var string $dateFin */

echo Colisage::reportingPage($tonnageData, $caData, $delaiData, $dateDebut, $dateFin);
