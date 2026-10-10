<?php

declare(strict_types=1);

use App\View\Components\MouvementsCaisse;

/** @var array<string, mixed> $mouvements */
/** @var string $sens versements|retraits */
/** @var string $editePar */
echo MouvementsCaisse::exportPdf($mouvements, $sens, $editePar);
