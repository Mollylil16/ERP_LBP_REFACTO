<?php

declare(strict_types=1);

namespace Tests\Browser;

use Tests\Panther\PantherTestCase;

final class LoginTest extends PantherTestCase
{
    public function testLoginPageContainsTheExpectedForm(): void
    {
        $client = $this->browser();
        $crawler = $client->request('GET', '/login');

        $this->assertCurrentPathContains($client, '/login');
        self::assertCount(1, $crawler->filter('form.auth-form'));
        self::assertCount(1, $crawler->filter('input[name="email"]'));
        self::assertCount(1, $crawler->filter('input[name="password"]'));
        $this->assertPageHasNoInternalServerError($client);
    }
}
