<?php

declare(strict_types=1);

use App\View\Components\Logistique;

/** @var array<int, array<string, mixed>> $sites */
/** @var string $period */
/** @var string $dateStart */
/** @var string $dateEnd */
/** @var int $selectedAgenceId */
/** @var array<int, array<string, mixed>> $parcels */
/** @var array<string, mixed> $kpis */
/** @var array<int, array{code: string, libelle: string}> $trajets */
/** @var array{trajet: string, q: string} $filtres */
/** @var bool $peutVoirFacture */

echo Logistique::colisageSuiviPage(
    $sites,
    $period,
    $dateStart,
    $dateEnd,
    $selectedAgenceId,
    $parcels,
    $kpis,
    $trajets ?? [],
    $filtres ?? ['trajet' => '', 'q' => ''],
    (bool) ($peutVoirFacture ?? false)
);
