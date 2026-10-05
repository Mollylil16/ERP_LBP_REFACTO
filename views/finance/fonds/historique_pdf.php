<?php

use App\View\Components\FinanceFondsHistorique;

/**
 * @var array<int, array<string, mixed>> $lignes
 * @var array<string, mixed> $filtres
 * @var string $editePar
 */

echo FinanceFondsHistorique::exportPdf($lignes, $filtres, $editePar);
