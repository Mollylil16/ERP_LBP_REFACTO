<?php

declare(strict_types=1);

use App\View\Components\Crm;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());
/** @var array<int, array<string, mixed>> $commercialOwners */

echo Crm::clientCreatePage($commercialOwners);
