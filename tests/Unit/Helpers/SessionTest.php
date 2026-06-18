<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\Session;
use Tests\TestCase;

final class SessionTest extends TestCase
{
    public function test_set_get_has_and_forget(): void
    {
        Session::set('auth_user_id', 42);

        self::assertTrue(Session::has('auth_user_id'));
        self::assertSame(42, Session::get('auth_user_id'));

        Session::forget('auth_user_id');

        self::assertFalse(Session::has('auth_user_id'));
        self::assertSame('fallback', Session::get('auth_user_id', 'fallback'));
    }

    public function test_flash_message_is_consumed_once(): void
    {
        Session::flash('success', 'Opération réussie');

        self::assertSame('Opération réussie', Session::getFlash('success'));
        self::assertNull(Session::getFlash('success'));
    }
}
