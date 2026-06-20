<?php

declare(strict_types=1);

namespace Tests\Panther;

use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\PantherTestCase as SymfonyPantherTestCase;

abstract class PantherTestCase extends SymfonyPantherTestCase
{
    protected const BASE_URI = 'http://127.0.0.1:8000';

    protected function setUp(): void
    {
        parent::setUp();

        if (static::$pantherClient instanceof Client) {
            static::$pantherClient->restart();
        }
    }

    protected function browser(): Client
    {
        $projectRoot = dirname(__DIR__, 2);
        $serverOptions = [
            'browser' => self::CHROME,
            'webServerDir' => $projectRoot . '/public',
            'hostname' => '127.0.0.1',
            'port' => 8000,
            'router' => $projectRoot . '/public/index.php',
            'readinessPath' => '/login',
            'env' => [
                'APP_ENV' => 'testing',
                'APP_URL' => self::BASE_URI,
            ],
        ];

        if (static::$pantherClient instanceof Client) {
            return static::$pantherClient;
        }

        static::startWebServer($serverOptions);

        $driver = $projectRoot . '/drivers/chromedriver'
            . (PHP_OS_FAMILY === 'Windows' ? '.exe' : '');
        self::assertFileExists(
            $driver,
            'ChromeDriver est absent. Exécutez "vendor/bin/bdi detect drivers".'
        );

        $client = Client::createChromeClient($driver, null, [], static::$baseUri);
        static::$pantherClients[0] = static::$pantherClient = $client;

        return $client;
    }

    protected function assertPageHasNoInternalServerError(Client $client): void
    {
        $source = $client->getPageSource();

        self::assertStringNotContainsString('Incident interne', $source);
        self::assertStringNotContainsString('Erreur 500', $source);
    }

    protected function assertCurrentPathContains(Client $client, string $path): void
    {
        $currentPath = (string) parse_url($client->getCurrentURL(), PHP_URL_PATH);

        self::assertStringContainsString($path, $currentPath);
    }

    protected function loginAsAdministrator(Client $client): void
    {
        $crawler = $client->request('GET', '/login');
        $form = $crawler->filter('form')->form();

        $client->submit($form, [
            'email' => $this->adminIdentifier(),
            'password' => $this->adminPassword(),
        ]);
        $client->waitFor('body');

        $this->assertPageHasNoInternalServerError($client);
        self::assertStringNotContainsString('/login', $client->getCurrentURL(), 'La connexion administrateur a échoué.');
    }

    private function adminIdentifier(): string
    {
        return (string) ($_SERVER['PANTHER_ADMIN_IDENTIFIER'] ?? getenv('PANTHER_ADMIN_IDENTIFIER') ?: 'admin');
    }

    private function adminPassword(): string
    {
        return (string) ($_SERVER['PANTHER_ADMIN_PASSWORD'] ?? getenv('PANTHER_ADMIN_PASSWORD') ?: 'admin');
    }
}
