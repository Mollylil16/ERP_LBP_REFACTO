<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\Core\HomeController;
use App\Controllers\Site\WebsiteController;

/** @var Router $router */

$router->get('/', [HomeController::class, 'index']);

$router->get('/site', [WebsiteController::class, 'publicSite']);
$router->get('/site/tracking', [WebsiteController::class, 'tracking']);
$router->get('/site/agences', [WebsiteController::class, 'agencies']);
$router->get('/site/devis', [WebsiteController::class, 'quote']);
$router->get('/site/contact', [WebsiteController::class, 'contact']);
