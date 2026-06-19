<?php

declare(strict_types=1);

namespace Tests\Unit\View\Pages\Admin;

use App\Models\User;
use App\View\Pages\Admin\UserIndexPage;
use Tests\TestCase;

final class UserIndexPageTest extends TestCase
{
    public function test_prepares_user_rows_and_pagination(): void
    {
        $page = new UserIndexPage([
            'filters' => ['q' => 'alice', 'status' => '', 'profile' => ''],
            'pagination' => [
                'items' => [new User(
                    7,
                    'Alice',
                    'alice@example.test',
                    '0102',
                    'hash',
                    rhEmployeeId: 12,
                    createdAt: '2026-06-19 08:00:00',
                )],
                'total' => 20,
                'page' => 2,
                'totalPages' => 2,
            ],
        ]);

        self::assertSame(20, $page->total);
        self::assertSame('Profil RH #12', $page->users[0]['profile_reference']);
        self::assertSame('19/06/2026', $page->users[0]['created_at']);
        self::assertTrue($page->pagination[1]['active']);
        self::assertStringContainsString('q=alice', $page->pagination[1]['href']);
    }
}
