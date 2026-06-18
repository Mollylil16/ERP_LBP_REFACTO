<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Employee\EmployeeRequestCatalog;
use Tests\TestCase;

final class EmployeeRequestCatalogTest extends TestCase
{
    public function test_catalog_contains_all_professional_request_types(): void
    {
        $catalog = EmployeeRequestCatalog::all();
        self::assertSame(
            ['leave', 'absence', 'lateness', 'attendance_correction', 'salary_advance', 'document', 'other'],
            array_keys($catalog)
        );
    }

    public function test_each_type_has_distinct_field_schema(): void
    {
        $catalog = EmployeeRequestCatalog::all();
        self::assertContains('amount', $catalog['salary_advance']['fields']);
        self::assertNotContains('start_date', $catalog['salary_advance']['fields']);
        self::assertContains('attachment', $catalog['absence']['fields']);
        self::assertContains('arrival_time', $catalog['lateness']['fields']);
        self::assertContains('document_kind', $catalog['document']['fields']);
        self::assertNotContains('start_date', $catalog['document']['fields']);
    }
}
