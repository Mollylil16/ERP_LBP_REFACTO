<?php

declare(strict_types=1);

use App\View\Components\Facturation;

/** @var array<string, mixed> $facture */
/** @var bool $canModify */
/** @var array<int, array<string, mixed>> $auditLog */

echo Facturation::factureEditPage($facture, $canModify, $auditLog);
