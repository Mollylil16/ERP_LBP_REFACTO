<?php

declare(strict_types=1);

use App\View\Components\ColisageEnvoisExport;

/** @var array<string, mixed> $envois */

echo ColisageEnvoisExport::historiqueExcel($envois);
