<?php

declare(strict_types=1);

use App\View\Components\ColisageGuide;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());

echo ColisageGuide::page();
