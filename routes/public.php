<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\Core\HomeController;
use App\Controllers\Site\WebsiteController;
use App\Controllers\Site\WebsiteCustomerController;
use App\Controllers\Site\WebsiteAnalyticsController;

/** @var Router $router */

$router->get('/', [HomeController::class, 'index']);

$router->get('/site', [WebsiteController::class, 'publicSite']);
$router->get('/site/tracking', [WebsiteController::class, 'tracking']);
$router->get('/site/agences', [WebsiteController::class, 'agencies']);
$router->get('/site/devis', [WebsiteController::class, 'quote']);
$router->get('/site/contact', [WebsiteController::class, 'contact']);
$router->get('/site/shop', [WebsiteController::class, 'shop']);
$router->get('/site/forum', [WebsiteController::class, 'forum']);
$router->get('/site/blog', [WebsiteController::class, 'blog']);
$router->get('/site/blog/{slug}', [WebsiteController::class, 'article']);
$router->get('/site/account', [WebsiteCustomerController::class, 'account']);
$router->post('/site/account/register', [WebsiteCustomerController::class, 'register']);
$router->post('/site/account/login', [WebsiteCustomerController::class, 'login']);
$router->get('/site/account/logout', [WebsiteCustomerController::class, 'logout']);
$router->post('/site/account/messages', [WebsiteCustomerController::class, 'sendMessage']);
$router->get('/site/account/messages', [WebsiteCustomerController::class, 'messages']);
$router->post('/site/analytics', [WebsiteAnalyticsController::class, 'record']);
