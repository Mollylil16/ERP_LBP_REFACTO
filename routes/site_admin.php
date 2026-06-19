<?php

declare(strict_types=1);

use App\Router;
use App\Controllers\SiteAdmin\SiteAdminDashboardController;
use App\Controllers\SiteAdmin\SiteAdminConversationController;

/** @var Router $router */

$router->group('/site-admin', function (Router $router): void {
    $router->get('/', [SiteAdminDashboardController::class, 'index']);
    $router->get('/dashboard', [SiteAdminDashboardController::class, 'index']);
    $router->get('/configuration', [SiteAdminDashboardController::class, 'configuration']);
    $router->post('/configuration/branding', [SiteAdminDashboardController::class, 'updateBranding']);
    $router->post('/configuration/slides', [SiteAdminDashboardController::class, 'saveSlide']);
    $router->post('/configuration/products', [SiteAdminDashboardController::class, 'saveProduct']);
    $router->post('/configuration/announcements', [SiteAdminDashboardController::class, 'saveAnnouncement']);
    $router->post('/configuration/articles', [SiteAdminDashboardController::class, 'saveArticle']);
    $router->get('/messages', [SiteAdminConversationController::class, 'index']);
    $router->post('/messages/{conversationId}', [SiteAdminConversationController::class, 'send']);
    $router->get('/messages/{conversationId}/feed', [SiteAdminConversationController::class, 'feed']);
    $router->get('/analytics', [SiteAdminDashboardController::class, 'analytics']);
});
