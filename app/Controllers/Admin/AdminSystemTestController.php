<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Helpers\Csrf;
use App\Middleware\AdminMiddleware;
use App\Models\Database;
use App\Repositories\Admin\SystemTestRepository;
use App\Services\Admin\SystemTestService;
use App\View\Pages\Admin\SystemTestsPage;
use Throwable;

final class AdminSystemTestController extends AdminBaseController
{
    private SystemTestService $service;

    public function __construct()
    {
        $pdo = Database::getConnection();
        $this->service = new SystemTestService(new SystemTestRepository($pdo));
    }

    public function index(): void
    {
        AdminMiddleware::check();

        $this->adminView('admin/system_tests/index', 'Santé & Tests ERP', 'tests', [
            'page' => new SystemTestsPage(
                Csrf::token(),
                $this->service->dashboardSummary(),
                $this->service->moduleCards(),
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
