<?php

declare(strict_types=1);

namespace Tests\Unit\View\Components;

use App\View\Components\RecordList;
use Tests\TestCase;

final class RecordListTest extends TestCase
{
    public function test_renders_fields_as_badge_components_and_status(): void
    {
        $html = RecordList::render([[
            'name' => 'Mission portuaire', 'manager' => 'Awa', 'site' => 'Abidjan', 'status' => 'active',
        ]], ['name' => 'Mission', 'manager' => 'Responsable', 'site' => 'Site', 'status' => 'Statut'], [
            'title_key' => 'name', 'status_key' => 'status',
        ]);
        self::assertStringContainsString('finea-data-badge', $html);
        self::assertStringContainsString('Responsable', $html);
        self::assertStringContainsString('finea-badge--success', $html);
    }
}
