<?php

use App\View\Components\RapprochementEnvois;

/** @var array<string, mixed> $rappro */
echo RapprochementEnvois::exportPdf($rappro);
