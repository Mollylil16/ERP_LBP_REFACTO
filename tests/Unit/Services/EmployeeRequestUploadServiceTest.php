<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Employee\EmployeeRequestUploadService;
use RuntimeException;
use Tests\TestCase;

final class EmployeeRequestUploadServiceTest extends TestCase
{
    public function test_no_file_returns_null(): void
    {
        self::assertNull((new EmployeeRequestUploadService())->store(null));
        self::assertNull((new EmployeeRequestUploadService())->store(['error' => UPLOAD_ERR_NO_FILE]));
    }

    public function test_rejects_oversized_file_before_storage(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('5 Mo maximum');
        (new EmployeeRequestUploadService())->store([
            'error' => UPLOAD_ERR_OK, 'size' => 6 * 1024 * 1024, 'tmp_name' => 'unused',
        ]);
    }

    public function test_rejects_php_upload_error(): void
    {
        $this->expectException(RuntimeException::class);
        (new EmployeeRequestUploadService())->store(['error' => UPLOAD_ERR_PARTIAL]);
    }
}
