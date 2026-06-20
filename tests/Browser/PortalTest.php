<?php

declare(strict_types=1);

namespace Tests\Browser;

use Tests\Panther\PantherTestCase;

final class PortalTest extends PantherTestCase
{
    public function testGuestIsRedirectedToLogin(): void
    {
        $client = $this->browser();
        $client->request('GET', '/selection_portail');

        $this->assertCurrentPathContains($client, '/login');
        $this->assertPageHasNoInternalServerError($client);
    }

    public function testAuthenticatedUserCanSeeThePortal(): void
    {
        $client = $this->browser();
        $this->loginAsAdministrator($client);

        $crawler = $client->request('GET', '/selection_portail');

        $this->assertCurrentPathContains($client, '/selection_portail');
        self::assertCount(1, $crawler->filter('[data-module-card]')->first());
        self::assertStringContainsString('choisissez votre espace de travail', $crawler->filter('body')->text());
        $this->assertPageHasNoInternalServerError($client);
    }
}
