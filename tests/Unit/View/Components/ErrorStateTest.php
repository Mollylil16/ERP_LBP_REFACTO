<?php

declare(strict_types=1);

namespace Tests\Unit\View\Components;

use App\View\Components\ErrorState;
use App\View\Pages\Error\ErrorPage;
use Tests\TestCase;

final class ErrorStateTest extends TestCase
{
    public function test_not_found_page_escapes_requested_path(): void
    {
        $html = ErrorState::page(ErrorPage::notFound('/<script>alert(1)</script>'));

        self::assertStringContainsString('404', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('selection_portail', $html);
    }

    public function test_maintenance_page_displays_reason_and_shared_actions(): void
    {
        $html = ErrorState::page(ErrorPage::maintenance('rh', 'Mise à jour planifiée'));

        self::assertStringContainsString('503', $html);
        self::assertStringContainsString('Ressources humaines', $html);
        self::assertStringContainsString('Mise à jour planifiée', $html);
        self::assertStringContainsString('error-state--maintenance', $html);
        self::assertStringContainsString('Ce que cela signifie', $html);
        self::assertStringContainsString('Que faire maintenant', $html);
    }

    public function test_error_views_only_assemble_page_component_and_layout(): void
    {
        foreach (['404.php', 'maintenance.php', 'error.php'] as $file) {
            $source = (string) file_get_contents(BASE_PATH . '/views/errors/' . $file);

            self::assertStringContainsString('/** @var ErrorPage $page */', $source);
            self::assertStringContainsString('ErrorState::page($page)', $source);
            self::assertStringContainsString('/views/layouts/guest.php', $source);
            self::assertStringNotContainsString('http_response_code(', $source);
        }
    }

    public function test_common_http_errors_have_a_french_explanation(): void
    {
        foreach ([400, 401, 403, 408, 419, 422, 429, 500, 502, 503, 504] as $status) {
            $page = ErrorPage::forStatus($status);
            $html = ErrorState::page($page);

            self::assertSame($status, $page->statusCode);
            self::assertNotSame('', $page->explanation);
            self::assertNotEmpty($page->suggestions);
            self::assertStringContainsString('Erreur ' . $status, $html);
        }
    }
}
