<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Repositories\Admin\ModuleMaintenanceRepository;
use App\Services\Admin\ModuleMaintenanceService;
use PDO;
use RuntimeException;
use Tests\TestCase;

final class ModuleMaintenanceServiceTest extends TestCase
{
    private function service(): ModuleMaintenanceService
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE module_maintenance (
                module_slug TEXT PRIMARY KEY,
                is_maintenance INTEGER NOT NULL DEFAULT 0,
                reason TEXT NULL,
                updated_by INTEGER NULL,
                updated_at TEXT NULL
            )'
        );
        return new ModuleMaintenanceService(new ModuleMaintenanceRepository($pdo));
    }

    public function test_enables_and_disables_maintenance_with_reason(): void
    {
        $service = $this->service();
        $enabled = $service->update('rh', true, 'Migration de la base RH', 1);
        self::assertTrue($enabled['is_maintenance']);
        self::assertSame('Migration de la base RH', $enabled['reason']);

        $disabled = $service->update('rh', false, '', 1);
        self::assertFalse($disabled['is_maintenance']);
        self::assertSame('', $disabled['reason']);
    }

    public function test_requires_reason_and_protects_admin_module(): void
    {
        $service = $this->service();
        $this->expectException(RuntimeException::class);
        $service->update('rh', true, 'x', 1);
    }

    public function test_admin_cannot_be_placed_in_maintenance(): void
    {
        $service = $this->service();
        $this->expectException(RuntimeException::class);
        $service->update('admin', true, 'Maintenance administration', 1);
    }

    public function test_lookup_fails_open_when_storage_is_unavailable(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $service = new ModuleMaintenanceService(new ModuleMaintenanceRepository($pdo));

        self::assertSame([], $service->states());
        self::assertSame([
            'is_maintenance' => false,
            'reason' => '',
            'updated_by' => null,
            'updated_at' => '',
        ], $service->state('rh'));
    }
}
