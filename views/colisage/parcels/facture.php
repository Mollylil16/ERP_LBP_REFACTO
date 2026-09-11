<?php

declare(strict_types=1);

use App\View\Components\ColisageFacture;

/**
 * @var array<string, mixed> $colis
 * @var \App\Models\Finance\Facture|null $facture
 * @var float $montantEur
 * @var string $operatorName
 */

echo ColisageFacture::document(
    $colis,
    $facture ?? null,
    (float) ($montantEur ?? 0.0),
    (string) ($operatorName ?? 'Service Transit')
);
