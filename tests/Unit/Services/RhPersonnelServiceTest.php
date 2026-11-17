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

    /** Décidé le 17/11/2026 : un vrai dossier se met en sortie, il ne se supprime pas. */
    public function test_une_fiche_avec_un_vrai_historique_ne_se_supprime_pas(): void
    {
        $repository = $this->createMock(RhPersonnelRepository::class);
        $repository->method('find')->willReturn(['id' => 1016, 'full_name' => 'LASSICI MARIAM']);
        $repository->method('historiqueReel')->willReturn(['rh_contracts.employee_id' => 1, 'rh_payslips.employee_id' => 3]);
        $repository->method('comptesLies')->willReturn([]);
        $repository->expects(self::never())->method('supprimer');

        $service = new RhPersonnelService($repository, new OpenVisibilityService());

        try {
            $service->supprimer(1016, 'erreur de saisie', 1);
            self::fail('La suppression aurait dû être refusée.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('1 contrat(s)', $e->getMessage());
            self::assertStringContainsString('3 bulletin(s) de paie', $e->getMessage());
            self::assertStringContainsString('Sortie', $e->getMessage());
        }
    }

    public function test_le_motif_de_suppression_est_obligatoire(): void
    {
        $repository = $this->createMock(RhPersonnelRepository::class);
        $repository->expects(self::never())->method('supprimer');

        $service = new RhPersonnelService($repository, new OpenVisibilityService());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('motif');

        $service->supprimer(1045, '  ', 1);
    }

    public function test_une_fiche_sans_historique_est_supprimee(): void
    {
        $repository = $this->createMock(RhPersonnelRepository::class);
        $repository->method('find')->willReturn(['id' => 1045, 'full_name' => 'Mariam Lassici']);
        $repository->method('historiqueReel')->willReturn([]);
        $repository->method('comptesLies')->willReturn([
            ['id' => 16, 'full_name' => 'Mariam Lassici', 'email' => 'mariam.lassici@labelleporte.ci', 'status' => 'active'],
        ]);
        $repository->expects(self::once())->method('supprimer')->with(1045);

        $service = new RhPersonnelService($repository, new OpenVisibilityService());
        $avant = $service->avantSuppression(1045);

        self::assertSame([], $avant['bloquants']);
        self::assertCount(1, $avant['comptes'], 'Le compte qui sera désactivé est annoncé avant la suppression.');

        $service->supprimer(1045, 'doublon de la fiche LBP-016', 1);
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
