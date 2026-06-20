<?php

declare(strict_types=1);

namespace Tests\Unit\View\Components;

use App\View\Components\SiteAnalytics;
use App\View\Pages\SiteAdmin\AnalyticsPage;
use PHPUnit\Framework\TestCase;

final class SiteAnalyticsTest extends TestCase
{
    public function testPageSupportsAnEmptyAnalyticsDatabase(): void
    {
        $html = SiteAnalytics::page(new AnalyticsPage([
            'summary' => [],
            'daily' => [],
            'pages' => [],
            'clicks' => [],
            'visitors' => [],
        ]));

        self::assertStringContainsString('Audience du site', $html);
        self::assertStringContainsString('Activité des 14 derniers jours', $html);
    }
}
