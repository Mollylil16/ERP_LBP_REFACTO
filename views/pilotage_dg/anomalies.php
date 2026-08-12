<?php

declare(strict_types=1);

use App\View\Components\PilotageDg;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());
/** @var array<int, array<string, mixed>> $signalements */
/** @var array<int, array<string, mixed>> $ecartsCaisse */
/** @var array<int, array<string, mixed>> $agentsSuspects */
/** @var array<int, array<string, mixed>> $agencesImpayes */
/** @var array<int, array<string, mixed>> $colisSuspects */
/** @var array<int, array<string, mixed>> $rapprochementIndependant */

$signalements ??= [];
$colisSuspects ??= [];
$rapprochementIndependant ??= [];
ob_start();
echo PilotageDg::anomaliesPage($ecartsCaisse, $agentsSuspects, $agencesImpayes, $signalements, $colisSuspects, $rapprochementIndependant);
$content = ob_get_clean();

require BASE_PATH . '/views/layouts/module.php';
