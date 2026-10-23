<?php

declare(strict_types=1);

use App\View\Components\ColisageReceptionRapport;

/** @var array<string, mixed> $rapport */

echo ColisageReceptionRapport::pdf($rapport);
