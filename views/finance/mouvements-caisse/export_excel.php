<?php

declare(strict_types=1);

use App\View\Components\MouvementsCaisse;

/** @var array<string, mixed> $mouvements */
/** @var string $sens versements|retraits */
echo MouvementsCaisse::exportExcel($mouvements, $sens);
