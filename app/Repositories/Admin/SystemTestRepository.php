<?php

declare(strict_types=1);

namespace App\Repositories\Admin;

use PDO;
use Throwable;

final class SystemTestRepository
{
    public function __construct(private PDO $pdo)
    {
        $this->ensureTables();
    }

    public function ping(): bool
    {
        return (int) $this->pdo->query('SELECT 1')->fetchColumn() === 1;
    }

    public function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table");
        $stmt->execute(['table' => $table]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function tableCount(string $table): ?int
    {
        if (!$this->tableExists($table)) {
            return null;
        }

        $safe = str_replace('`', '', $table);
        return (int) $this->pdo->query("SELECT COUNT(*) FROM `{$safe}`")->fetchColumn();
    }

    /** @return array<int, array<string, mixed>> */
    public function inspectTables(array $tables): array
    {
        $items = [];
        foreach ($tables as $table) {
            try {
                $count = $this->tableCount((string) $table);
                $items[] = [
                    'name' => (string) $table,
                    'exists' => $count !== null,
                    'count' => $count,
                    'status' => $count === null ? 'warning' : 'passed',
                    'message' => $count === null ? 'Table absente ou pas encore migrée.' : $count . ' ligne(s) accessibles.',
                ];
            } catch (Throwable $e) {
                $items[] = [
                    'name' => (string) $table,
                    'exists' => false,
                    'count' => null,
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $items;
    }

    /** @param array<string, mixed> $payload */
    public function storeRun(string $scope, string $module, string $status, int $score, array $payload): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO system_test_runs (scope, module, status, score, payload, created_at) VALUES (:scope, :module, :status, :score, :payload, NOW())");
        $stmt->execute([
            'scope' => $scope,
            'module' => $module,
            'status' => $status,
            'score' => $score,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<int, array<string, mixed>> */
    public function latestRuns(int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = $this->pdo->query("SELECT id, scope, module, status, score, created_at FROM system_test_runs ORDER BY id DESC LIMIT {$limit}");
        return $stmt->fetchAll() ?: [];
    }

    /** @return array<string, mixed>|null */
    public function latestRun(): ?array
    {
        $stmt = $this->pdo->query('SELECT id, scope, module, status, score, payload, created_at FROM system_test_runs ORDER BY id DESC LIMIT 1');
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $row['payload'] = json_decode((string) $row['payload'], true) ?: [];
        return $row;
    }

    private function ensureTables(): void
    {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS system_test_runs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            scope VARCHAR(30) NOT NULL,
            module VARCHAR(80) NOT NULL DEFAULT 'application',
            status ENUM('passed','warning','failed') NOT NULL DEFAULT 'warning',
            score TINYINT UNSIGNED NOT NULL DEFAULT 0,
            payload JSON NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_system_test_runs_scope_created (scope, created_at),
            KEY idx_system_test_runs_module_created (module, created_at),
            KEY idx_system_test_runs_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}
