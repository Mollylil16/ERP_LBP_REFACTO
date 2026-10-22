<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Mobile\WebPushService;
use PDO;
use Tests\TestCase;

/**
 * Envoi groupé (lot 4 de la messagerie) : plusieurs notifications en
 * parallèle, chacune avec son résultat, dans l'ordre des envois. Le réseau
 * est réel mais local : un port fermé répond « refusé » aussitôt.
 */
final class WebPushGroupeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('curl') || $this->nouvelleCleEc() === false) {
            self::markTestSkipped('curl ou OpenSSL (courbe prime256v1) indisponible : vérifiez OPENSSL_CONF.');
        }
    }

    public function test_chaque_envoi_a_son_resultat_dans_l_ordre(): void
    {
        $service = new WebPushService($this->base());
        $service->clePubliqueVapid();

        $valide = [
            'endpoint' => 'https://127.0.0.1:9/push/a',
            'p256dh' => WebPushService::base64Url($this->point($this->nouvelleCleEc())),
            'auth' => WebPushService::base64Url(random_bytes(16)),
        ];
        $illisible = ['endpoint' => 'https://127.0.0.1:9/push/b', 'p256dh' => 'abc', 'auth' => 'def'];

        $debut = microtime(true);
        $resultats = $service->envoyerTous([
            'a' => [$valide, ['titre' => 'Un']],
            'b' => [$illisible, ['titre' => 'Deux']],
            'c' => [$valide, ['titre' => 'Trois']],
        ], 60);

        self::assertSame(['a', 'b', 'c'], array_keys($resultats));
        self::assertFalse($resultats['a']['ok']);
        self::assertSame(0, $resultats['a']['status'], 'Port fermé : aucun statut HTTP.');
        self::assertFalse($resultats['a']['expire'], 'Une panne réseau ne supprime pas l’abonnement.');
        self::assertStringContainsString('abonnement invalide', $resultats['b']['message']);
        self::assertLessThan(8.0, microtime(true) - $debut);
    }

    public function test_sans_cles_vapid_rien_ne_part(): void
    {
        $service = new WebPushService($this->base());

        $resultats = $service->envoyerTous([[['endpoint' => 'https://127.0.0.1:9/x', 'p256dh' => 'a', 'auth' => 'b'], []]]);

        self::assertSame('Clés VAPID absentes.', $resultats[0]['message']);
    }

    public function test_seuls_les_services_de_push_connus_sont_admis(): void
    {
        self::assertTrue(WebPushService::endpointAutorise('https://fcm.googleapis.com/fcm/send/abc'));
        self::assertTrue(WebPushService::endpointAutorise('https://updates.push.services.mozilla.com/wpush/v2/abc'));
        self::assertTrue(WebPushService::endpointAutorise('https://web.push.apple.com/QGx'));
        self::assertTrue(WebPushService::endpointAutorise('https://wns2-par02p.notify.windows.com/w/?token=x'));
        self::assertFalse(WebPushService::endpointAutorise('http://fcm.googleapis.com/fcm/send/abc'));
        self::assertFalse(WebPushService::endpointAutorise('https://fcm.googleapis.com.pirate.example/x'));
        self::assertFalse(WebPushService::endpointAutorise('https://127.0.0.1/x'));
    }

    // -----------------------------------------------------------------

    private function nouvelleCleEc(): \OpenSSLAsymmetricKey|false
    {
        return @openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'private_key_bits' => 384,
        ]);
    }

    private function point(\OpenSSLAsymmetricKey $cle): string
    {
        $details = openssl_pkey_get_details($cle);

        return "\x04"
            . str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT)
            . str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);
    }

    private function base(): PDO
    {
        $pdo = new class ('sqlite::memory:') extends PDO {
            public function prepare(string $query, array $options = []): \PDOStatement|false
            {
                if (str_contains($query, 'ON DUPLICATE KEY UPDATE')) {
                    $query = "INSERT INTO lbp_mobile_settings (cle, valeur, updated_at)
                              VALUES (:cle, :valeur, datetime('now'))
                              ON CONFLICT(cle) DO UPDATE SET valeur = excluded.valeur";
                }

                return parent::prepare($query, $options);
            }
        };

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE lbp_mobile_settings (cle TEXT PRIMARY KEY, valeur TEXT, updated_at TEXT)');

        return $pdo;
    }
}
