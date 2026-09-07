<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\Csrf;
use Tests\TestCase;

final class CsrfTest extends TestCase
{
    public function test_token_is_stable_during_session_and_verifiable(): void
    {
        $first = Csrf::token();
        $second = Csrf::token();

        self::assertSame($first, $second);
        self::assertTrue(Csrf::verify($first));
        self::assertFalse(Csrf::verify('invalid-token'));
    }

    public function test_input_contains_hidden_csrf_field(): void
    {
        $html = Csrf::input();

        self::assertStringContainsString('type="hidden"', $html);
        self::assertStringContainsString('name="_csrf_token"', $html);
        self::assertStringContainsString(Csrf::token(), $html);
    }

    public function test_field_is_an_alias_of_input(): void
    {
        $field = Csrf::field();
        $input = Csrf::input();

        self::assertSame($input, $field);
        self::assertStringContainsString('name="_csrf_token"', $field);
    }
}
