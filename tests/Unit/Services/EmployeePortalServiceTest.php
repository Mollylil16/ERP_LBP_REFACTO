<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\User;
use App\Repositories\Employee\EmployeePortalRepository;
use App\Services\Employee\EmployeePortalService;
use App\Services\Employee\EmployeeRequestUploadService;
use RuntimeException;
use Tests\TestCase;

final class EmployeePortalServiceTest extends TestCase
{
    private function user(?int $employeeId = 8): User
    {
        return new User(3, 'Employé Test', 'employee@test.local', null, 'hash', rhEmployeeId: $employeeId);
    }

    public function test_rejects_account_without_employee_link(): void
    {
        $service = new EmployeePortalService($this->createStub(EmployeePortalRepository::class));
        $this->expectException(RuntimeException::class);
        $service->createRequest($this->user(null), ['request_type' => 'other', 'reason' => 'Demande suffisamment détaillée']);
    }

    public function test_salary_advance_requires_positive_amount(): void
    {
        $repository = $this->createMock(EmployeePortalRepository::class);
        $repository->expects(self::never())->method('createRequest');
        $this->expectException(RuntimeException::class);
        (new EmployeePortalService($repository))->createRequest($this->user(), [
            'request_type' => 'salary_advance', 'amount' => 0, 'reason' => 'Besoin financier exceptionnel',
        ]);
    }

    public function test_leave_request_normalizes_payload_and_employee_scope(): void
    {
        $repository = $this->createMock(EmployeePortalRepository::class);
        $repository->expects(self::once())->method('createRequest')
            ->with(8, self::callback(static fn(array $data): bool =>
                $data['request_type'] === 'leave'
                && $data['start_date'] === '2026-07-01'
                && $data['end_date'] === '2026-07-05'
                && str_starts_with($data['reference'], 'REQ-')
            ), 3)->willReturn(44);
        $id = (new EmployeePortalService($repository))->createRequest($this->user(), [
            'request_type' => 'leave', 'start_date' => '2026-07-01', 'end_date' => '2026-07-05', 'leave_kind' => 'annual',
            'reason' => 'Congé familial planifié',
        ]);
        self::assertSame(44, $id);
    }

    public function test_explanation_response_requires_meaningful_text(): void
    {
        $service = new EmployeePortalService($this->createStub(EmployeePortalRepository::class));
        $this->expectException(RuntimeException::class);
        $service->respondExplanation($this->user(), 2, ['response' => 'Trop court']);
    }

    public function test_lateness_requires_date_and_arrival_time_without_amount(): void
    {
        $repository = $this->createMock(EmployeePortalRepository::class);
        $repository->expects(self::once())->method('createRequest')
            ->with(8, self::callback(static function (array $data): bool {
                $metadata = json_decode($data['metadata_json'], true);
                return $data['request_type'] === 'lateness'
                    && $data['start_date'] === '2026-07-03'
                    && $data['amount'] === null
                    && $metadata['arrival_time'] === '09:12';
            }), 3)->willReturn(50);
        $service = new EmployeePortalService($repository);
        self::assertSame(50, $service->createRequest($this->user(), [
            'request_type' => 'lateness', 'incident_date' => '2026-07-03',
            'arrival_time' => '09:12', 'reason' => 'Incident de transport documenté.',
        ]));
    }

    public function test_document_request_does_not_accept_irrelevant_dates_or_amount(): void
    {
        $repository = $this->createMock(EmployeePortalRepository::class);
        $repository->expects(self::once())->method('createRequest')
            ->with(8, self::callback(static function (array $data): bool {
                $metadata = json_decode($data['metadata_json'], true);
                return $data['start_date'] === null && $data['end_date'] === null && $data['amount'] === null
                    && $metadata['document_kind'] === 'work_certificate';
            }), 3)->willReturn(51);
        $service = new EmployeePortalService($repository);
        $service->createRequest($this->user(), [
            'request_type' => 'document', 'document_kind' => 'work_certificate',
            'delivery_format' => 'digital', 'start_date' => '2020-01-01', 'amount' => 999,
            'reason' => 'Document nécessaire pour un dossier administratif.',
        ]);
    }

    public function test_attendance_wrong_time_requires_at_least_one_correct_time(): void
    {
        $service = new EmployeePortalService($this->createStub(EmployeePortalRepository::class));
        $this->expectException(RuntimeException::class);
        $service->createRequest($this->user(), [
            'request_type' => 'attendance_correction', 'incident_date' => '2026-07-03',
            'correction_kind' => 'wrong_time', 'reason' => 'Les horaires enregistrés sont incorrects.',
        ]);
    }

    public function test_forwards_justification_to_upload_component(): void
    {
        $repository = $this->createStub(EmployeePortalRepository::class);
        $repository->method('createRequest')->willReturn(52);
        $uploads = $this->createMock(EmployeeRequestUploadService::class);
        $file = ['name' => 'certificat.pdf', 'error' => UPLOAD_ERR_OK];
        $uploads->expects(self::once())->method('store')->with($file)->willReturn([
            'path' => 'uploads/test.pdf', 'original_name' => 'certificat.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => 100,
        ]);
        $service = new EmployeePortalService($repository, $uploads);
        self::assertSame(52, $service->createRequest($this->user(), [
            'request_type' => 'absence', 'start_date' => '2026-07-03',
            'absence_kind' => 'medical', 'reason' => 'Absence médicale avec justificatif.',
        ], ['attachment' => $file]));
    }
}
