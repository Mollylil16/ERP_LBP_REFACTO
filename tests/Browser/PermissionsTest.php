<?php

declare(strict_types=1);

namespace Tests\Browser;

use Tests\Panther\PantherTestCase;

final class PermissionsTest extends PantherTestCase
{
    public function testProtectedDashboardsRedirectGuestsToLogin(): void
    {
        $client = $this->browser();

        foreach (['/admin/dashboard', '/rh/dashboard'] as $protectedPath) {
            $client->request('GET', $protectedPath);

            $this->assertCurrentPathContains($client, '/login');
            $this->assertPageHasNoInternalServerError($client);
        }
    }
}
