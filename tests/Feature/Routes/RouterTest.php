<?php

declare(strict_types=1);

namespace Tests\Feature\Routes;

use App\Router;
use Tests\TestCase;

final class RouterTest extends TestCase
{
    public function test_dispatch_executes_matching_get_route(): void
    {
        $router = $this->routerWithoutMaintenance();
        $router->get('/ping', static function (): void {
            echo 'pong';
        });

        ob_start();
        $router->dispatch('/ping', 'GET');
        $output = ob_get_clean();

        self::assertSame('pong', $output);
    }

    public function test_dispatch_passes_route_parameters(): void
    {
        $router = $this->routerWithoutMaintenance();
        $router->get('/rh/personnel/{id}', static function (string $id): void {
            echo 'employee:' . $id;
        });

        ob_start();
        $router->dispatch('/rh/personnel/123', 'GET');
        $output = ob_get_clean();

        self::assertSame('employee:123', $output);
    }

    public function test_group_prefix_is_applied(): void
    {
        $router = $this->routerWithoutMaintenance();
        $router->group('/admin', static function (Router $router): void {
            $router->get('/permissions', static function (): void {
                echo 'matrix';
            });
        });

        ob_start();
        $router->dispatch('/admin/permissions', 'GET');
        $output = ob_get_clean();

        self::assertSame('matrix', $output);
    }

    public function test_public_subfolder_is_removed_from_request_uri(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/ERP_LBP_REFACTO/public/index.php';

        $router = $this->routerWithoutMaintenance();
        $router->get('/portail', static function (): void {
            echo 'portal';
        });

        ob_start();
        $router->dispatch('/ERP_LBP_REFACTO/public/portail', 'GET');
        $output = ob_get_clean();

        self::assertSame('portal', $output);
    }

    private function routerWithoutMaintenance(): Router
    {
        return new Router(static fn(string $path): ?array => null);
    }
}
