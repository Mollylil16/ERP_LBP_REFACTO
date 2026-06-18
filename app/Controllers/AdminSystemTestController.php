<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Middleware\AdminMiddleware;
use App\Models\Database;
use App\Repositories\SystemTestRepository;
use App\Services\SystemTestService;

final class AdminSystemTestController extends BaseController
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

        $this->view('admin/system_tests/index', [
            'pageTitle' => 'Santé & Tests ERP',
            'csrfToken' => Csrf::token(),
            'summary' => $this->service->dashboardSummary(),
            'modules' => $this->service->moduleCards(),
            'latestRuns' => $this->service->latestRuns(8),
        ]);
    }

    public function runAll(): void
    {
        AdminMiddleware::check();
        $this->guardAjaxPost();
        $this->json($this->service->runApplicationSuite());
    }

    public function runModule(string $module): void
    {
        AdminMiddleware::check();
        $this->guardAjaxPost();
        $this->json($this->service->runModuleSuite($module));
    }

    public function latest(): void
    {
        AdminMiddleware::check();
        $this->json([
            'ok' => true,
            'runs' => $this->service->latestRuns(10),
        ]);
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

    /** @param array<string, mixed> $payload */
    private function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
