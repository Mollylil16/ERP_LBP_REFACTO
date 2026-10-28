<?php

declare(strict_types=1);

use App\View\Components\MouvementsCaisse;

/** @var array<string, mixed> $mouvements */
/** @var string $editePar */
echo MouvementsCaisse::journalPdf($mouvements, $editePar);
