<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Repositories\Rh\RhPersonnelRepository;
use App\Security\PermissionEntityRegistry;
use App\Services\Support\DataVisibilityService;
use App\Services\Rh\RhPersonnelService;
use RuntimeException;
use Tests\TestCase;

final class RhPersonnelServiceTest extends TestCase
{
    public function test_create_rejects_missing_full_name(): void
    {
        $repository = $this->createMock(RhPersonnelRepository::class);
        $repository->expects(self::never())->method('create');

        $service = new RhPersonnelService($repository, new OpenVisibilityService());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Le nom complet est obligatoire.');

        $service->create(['email' => 'user@erp-lbp.local'], [], 1);
    }

    public function test_create_rejects_invalid_email(): void
    {
        $repository = $this->createMock(RhPersonnelRepository::class);
        $repository->expects(self::never())->method('create');

        $service = new RhPersonnelService($repository, new OpenVisibilityService());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('L’adresse email est invalide.');

        $service->create(['full_name' => 'Agent Transit', 'email' => 'not-an-email'], [], 1);
    }

    public function test_create_normalizes_employee_payload_before_repository(): void
    {
        $repository = $this->createMock(RhPersonnelRepository::class);
        $repository->expects(self::once())
            ->method('create')
            ->with(
                self::callback(static function (array $data): bool {
                    return $data['employee_number'] === 'EMP-001'
                        && $data['full_name'] === 'Agent Transit'
                        && $data['email'] === 'agent@erp-lbp.local'
                        && $data['gender'] === null
                        && $data['service_id'] === 5
                        && $data['children_count'] === 0
                        && $data['hire_date'] === '2026-06-18'
                        && $data['start_date'] === null;
                }),
                99,
                [],
                []
            )
            ->willReturn(123);

        $service = new RhPersonnelService($repository, new OpenVisibilityService());

        $id = $service->create([
            'employee_number' => ' EMP-001 ',
            'full_name' => ' Agent Transit ',
            'email' => ' agent@erp-lbp.local ',
            'gender' => 'invalid',
            'service_id' => '5',
            'children_count' => '-2',
            'hire_date' => '2026-06-18',
            'start_date' => 'bad-date',
        ], [], 99);

        self::assertSame(123, $id);
    }

    public function test_dossier_requires_existing_employee(): void
    {
        $repository = $this->createMock(RhPersonnelRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->with(404)
            ->willReturn(null);

        $service = new RhPersonnelService($repository, new OpenVisibilityService());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Collaborateur introuvable.');

        $service->dossier(404);
    }

    public function test_exit_hides_exit_reason_when_visibility_is_restricted(): void
    {
        $repository = $this->createMock(RhPersonnelRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->with(12)
            ->willReturn(['id' => 12, 'full_name' => 'Agent Transit']);
        $repository->expects(self::once())
            ->method('exitEmployee')
            ->with(
                12,
                self::callback(static fn(array $data): bool => $data['exit_date'] === '2026-06-18' && $data['exit_reason_id'] === null),
                3
            );

        $service = new RhPersonnelService($repository, new RestrictedExitReasonVisibilityService());

        $service->exit(12, ['exit_date' => '2026-06-18', 'exit_reason_id' => '8'], 3);
    }
}

final class OpenVisibilityService extends DataVisibilityService
{
    public function canView(string $table): bool
    {
        return true;
    }
}

final class RestrictedExitReasonVisibilityService extends DataVisibilityService
{
    public function canView(string $table): bool
    {
        return $table !== PermissionEntityRegistry::RH_EXIT_REASONS;
    }
}
