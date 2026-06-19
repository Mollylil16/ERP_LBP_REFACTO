<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Helpers\Csrf;
use App\Middleware\AdminMiddleware;
use App\Models\Database;
use App\Repositories\Admin\SystemTestRepository;
use App\Repositories\Admin\ModuleMaintenanceRepository;
use App\Services\Admin\ModuleMaintenanceService;
use App\Services\Admin\SystemTestService;
use App\View\Pages\Admin\SystemTestsPage;
use Throwable;

final class AdminSystemTestController extends AdminBaseController
{
    private SystemTestService $service;
    private ModuleMaintenanceService $maintenance;

    public function __construct()
    {
        $pdo = Database::getConnection();
        $this->service = new SystemTestService(new SystemTestRepository($pdo));
        $this->maintenance = new ModuleMaintenanceService(new ModuleMaintenanceRepository($pdo));
    }

    public function index(): void
    {
        AdminMiddleware::check();

        $states = $this->maintenance->states();
        $modules = array_map(static function (array $module) use ($states): array {
            $state = $states[(string) $module['slug']] ?? [];
            return $module + [
                'is_maintenance' => (bool) ($state['is_maintenance'] ?? false),
                'maintenance_reason' => (string) ($state['reason'] ?? ''),
            ];
        }, $this->service->moduleCards());

        $this->adminView('admin/system_tests/index', 'Santé & Tests ERP', 'tests', [
            'page' => new SystemTestsPage(
                Csrf::token(),
                $this->service->dashboardSummary(),
                $modules,
                $this->service->latestRuns(8),
            ),
        ], [
            'additionalStyles' => ['css/finea-ui.css', 'css/admin.css', 'css/system-tests.css'],
            'additionalScripts' => ['js/admin.js', 'js/system-tests.js'],
        ]);
    }

    public function runAll(): void
    {
        AdminMiddleware::check();
        $this->guardAjaxPost();
        $this->jsonSafe(fn(): array => $this->service->runApplicationSuite());
    }

    public function runModule(string $module): void
    {
        AdminMiddleware::check();
        $this->guardAjaxPost();
        $this->jsonSafe(fn(): array => $this->service->runModuleSuite($module));
    }

    public function latest(): void
    {
        AdminMiddleware::check();
        $this->jsonSafe(fn(): array => [
            'ok' => true,
            'runs' => $this->service->latestRuns(10),
        ]);
    }

    public function maintenance(string $module): void
    {
        AdminMiddleware::check();
        $this->guardAjaxPost();
        try {
            $state = $this->maintenance->update(
                $module,
                (string) ($_POST['maintenance'] ?? '') === '1',
                (string) ($_POST['reason'] ?? ''),
                (int) \App\Helpers\Auth::id(),
            );
            $this->json(['ok' => true, 'module' => $module, 'state' => $state]);
        } catch (\RuntimeException $exception) {
            $this->json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    /** @param callable(): array<string,mixed> $callback */
    private function jsonSafe(callable $callback): never
    {
        ob_start();

        try {
            $payload = $callback();
            $noise = ob_get_clean();
            if (is_string($noise) && trim($noise) !== '') {
                $payload['output_noise'] = trim(strip_tags($noise));
            }
            $this->json($payload);
        } catch (Throwable $exception) {
            $noise = ob_get_clean();
            $this->json([
                'ok' => false,
                'status' => 'failed',
                'score' => 0,
                'message' => "Erreur serveur pendant l'exécution des tests : " . $exception->getMessage(),
                'checks' => [[
                    'name' => 'Erreur serveur',
                    'status' => 'failed',
                    'message' => $exception->getMessage(),
                    'details' => [
                        'file' => $exception->getFile(),
                        'line' => $exception->getLine(),
                        'output' => is_string($noise) ? trim(strip_tags($noise)) : '',
                    ],
                ]],
            ], 500);
        }
    }

    private function guardAjaxPost(): void
    {
        if (!Csrf::verify($_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
            $this->json([
                'ok' => false,
                'status' => 'failed',
                'message' => 'Jeton CSRF invalide. Rechargez la page puis réessayez.',
            ], 419);
        }
    }

    /** @param array<string,mixed> $payload */
    private function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
