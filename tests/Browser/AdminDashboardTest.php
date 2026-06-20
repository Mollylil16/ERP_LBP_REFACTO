<?php

declare(strict_types=1);

namespace Tests\Browser;

use Tests\Panther\PantherTestCase;

final class AdminDashboardTest extends PantherTestCase
{
    public function testAdministratorCanOpenAdminDashboard(): void
    {
        $client = $this->browser();
        $this->loginAsAdministrator($client);

        $crawler = $client->request('GET', '/admin/dashboard');

        $this->assertCurrentPathContains($client, '/admin/dashboard');
        self::assertCount(1, $crawler->filter('h1'));
        self::assertStringContainsStringIgnoringCase('admin', $crawler->filter('body')->text());
        $this->assertPageHasNoInternalServerError($client);

        $analytics = $client->request('GET', '/site-admin/analytics');

        $this->assertCurrentPathContains($client, '/site-admin/analytics');
        self::assertStringContainsString('Audience du site', $analytics->filter('h1')->text());
        $this->assertPageHasNoInternalServerError($client);
    }
}
