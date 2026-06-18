<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Support\FormIntegrityService;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\TestCase;

#[RequiresPhpExtension('dom')]
final class FormIntegrityServiceTest extends TestCase
{
    public function test_accepts_secured_post_form(): void
    {
        $result = (new FormIntegrityService())->inspectHtml(
            '<form method="post" action="/rh/test"><input type="hidden" name="_csrf_token" value="x"></form>'
        );
        self::assertSame(1, $result['forms']);
        self::assertSame([], $result['broken']);
    }

    public function test_detects_missing_action_and_csrf(): void
    {
        $result = (new FormIntegrityService())->inspectHtml('<form method="post"></form>');
        self::assertCount(2, $result['broken']);
    }

    public function test_inspects_php_template_forms(): void
    {
        $result = (new FormIntegrityService())->inspectTemplate(
            '<form method="post" action="<?= View::url(\'rh/test\') ?>"><input name="_csrf_token"></form>'
        );
        self::assertSame(1, $result['forms']);
        self::assertSame([], $result['broken']);
    }
}
