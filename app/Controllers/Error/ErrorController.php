<?php

declare(strict_types=1);

namespace App\Controllers\Error;

use App\Controllers\BaseController;
use App\View\Pages\Error\ErrorPage;

final class ErrorController extends BaseController
{
    public function show(int $statusCode, string $detail = ''): void
    {
        $statusCode = $statusCode >= 400 && $statusCode <= 599 ? $statusCode : 500;
        http_response_code($statusCode);

        $this->view('errors/error', [
            'page' => ErrorPage::forStatus($statusCode, $detail),
            'pageTitle' => 'Erreur ' . $statusCode,
        ]);
    }

    public function notFound(string $requestedPath = ''): void
    {
        http_response_code(404);

        $this->view('errors/404', [
            'page' => ErrorPage::notFound($requestedPath),
            'pageTitle' => 'Page introuvable',
        ]);
    }

    /** @param array<string,mixed> $maintenance */
    public function maintenance(array $maintenance): void
    {
        http_response_code(503);

        $this->view('errors/maintenance', [
            'page' => ErrorPage::maintenance(
                (string) ($maintenance['slug'] ?? 'module'),
                (string) ($maintenance['reason'] ?? '')
            ),
            'pageTitle' => 'Module en maintenance',
        ]);
    }
}
