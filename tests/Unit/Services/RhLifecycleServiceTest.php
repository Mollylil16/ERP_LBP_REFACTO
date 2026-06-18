<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Repositories\Rh\RhLifecycleRepository;
use App\Services\Rh\RhLifecycleService;
use RuntimeException;
use Tests\TestCase;

final class RhLifecycleServiceTest extends TestCase
{
    public function test_contract_rejects_inverted_dates(): void
    {
        $repository = $this->createMock(RhLifecycleRepository::class);
        $repository->expects(self::never())->method('createContract');
        $this->expectException(RuntimeException::class);
        (new RhLifecycleService($repository))->createContract([
            'employee_id' => 1, 'contract_type' => 'CDD',
            'start_date' => '2026-07-01', 'end_date' => '2026-06-01',
        ], 1);
    }

    public function test_contract_normalizes_trial_and_alerts(): void
    {
        $repository = $this->createMock(RhLifecycleRepository::class);
        $repository->expects(self::once())->method('createContract')
            ->with(self::callback(static fn(array $data): bool =>
                $data['employee_id'] === 7 && $data['trial_status'] === 'pending' && $data['alert_days'] === '30,15,7'
            ), 3)->willReturn(12);
        $id = (new RhLifecycleService($repository))->createContract([
            'employee_id' => 7, 'contract_type' => 'CDD', 'start_date' => '2026-06-01',
            'end_date' => '2027-05-31', 'trial_start_date' => '2026-06-01', 'trial_end_date' => '2026-09-01',
        ], 3);
        self::assertSame(12, $id);
    }

    public function test_rejects_unknown_evaluation_type_and_workflow_decision(): void
    {
        $service = new RhLifecycleService($this->createStub(RhLifecycleRepository::class));
        try {
            $service->createEvaluation(['employee_id' => 1, 'evaluation_type' => 'unknown'], 1);
            self::fail('Une évaluation inconnue aurait dû être refusée.');
        } catch (RuntimeException) {
            self::assertTrue(true);
        }
        $this->expectException(RuntimeException::class);
        $service->decideWorkflow(1, 'skip', 2);
    }

    public function test_assignment_rejects_end_before_start(): void
    {
        $repository = $this->createMock(RhLifecycleRepository::class);
        $repository->expects(self::never())->method('createAssignment');
        $this->expectException(RuntimeException::class);
        (new RhLifecycleService($repository))->createAssignment([
            'employee_id' => 4,
            'title' => 'Mission portuaire',
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-01',
        ], 1);
    }
}
