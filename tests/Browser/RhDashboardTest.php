<?php

declare(strict_types=1);

namespace Tests\Browser;

use Tests\Panther\PantherTestCase;

final class RhDashboardTest extends PantherTestCase
{
    public function testAdministratorCanOpenRhDashboard(): void
    {
        $client = $this->browser();
        $this->loginAsAdministrator($client);

        $crawler = $client->request('GET', '/rh/dashboard');

        $this->assertCurrentPathContains($client, '/rh/dashboard');
        self::assertCount(1, $crawler->filter('h1'));
        self::assertStringContainsStringIgnoringCase('Ressources humaines', $crawler->filter('body')->text());
        $this->assertPageHasNoInternalServerError($client);
    }
}
