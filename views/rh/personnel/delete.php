<?php

use App\View\Components\RhSuppression;

/** @var array{employee: array<string,mixed>, bloquants: array<int,string>, comptes: array<int,array<string,mixed>>} $avant */

echo RhSuppression::page($avant);
