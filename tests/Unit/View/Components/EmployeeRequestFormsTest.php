<?php

declare(strict_types=1);

namespace Tests\Unit\View\Components;

use App\View\Components\EmployeeRequestForms;
use App\Services\FormIntegrityService;
use Tests\TestCase;

final class EmployeeRequestFormsTest extends TestCase
{
    public function test_renders_seven_distinct_specialized_forms(): void
    {
        $html = EmployeeRequestForms::render('csrf-test', 'absence');
        self::assertSame(7, substr_count($html, '<form'));
        self::assertStringContainsString('data-request-panel="absence"', $html);
        self::assertStringContainsString('data-request-panel="lateness"', $html);
        self::assertStringContainsString('enctype="multipart/form-data"', $html);
        self::assertStringContainsString('name="attachment"', $html);
        self::assertSame([], (new FormIntegrityService())->inspectHtml($html)['broken']);
    }

    public function test_amount_exists_only_in_salary_advance_form(): void
    {
        $html = EmployeeRequestForms::render('csrf-test');
        self::assertSame(1, substr_count($html, 'name="amount"'));
        self::assertSame(1, substr_count($html, 'name="repayment_months"'));
    }

    public function test_document_form_has_no_date_inputs(): void
    {
        $html = EmployeeRequestForms::render('csrf-test', 'document');
        preg_match('/<form[^>]+data-request-panel="document".*?<\/form>/s', $html, $match);
        self::assertNotEmpty($match);
        self::assertStringNotContainsString('type="date"', $match[0]);
        self::assertStringContainsString('name="document_kind"', $match[0]);
    }
}
