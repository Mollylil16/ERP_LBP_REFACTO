<?php

use App\View\Components\FinanceFondsHistorique;

/**
 * @var array<int, array<string, mixed>> $lignes
 * @var array<string, mixed> $filtres
 */

echo FinanceFondsHistorique::exportExcel($lignes, $filtres);
