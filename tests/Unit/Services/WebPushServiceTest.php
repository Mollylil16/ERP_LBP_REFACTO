<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Mobile\WebPushService;
use PDO;
use Tests\TestCase;

/**
 * Chiffrement et signature des notifications push.
 *
 * Le test décisif rejoue le déchiffrement dans le sens du navigateur : il fabrique
 * une paire de clés d'abonnement, demande au service de chiffrer un message pour
 * elle, puis le déchiffre avec la clé privée du client. Si la mise en œuvre de la
 * RFC 8291 dérive, ce test le voit.
 */
final class WebPushServiceTest extends TestCase
{
    /** En-tête DER d'une clé publique EC sur prime256v1. */
    private const ENTETE_DER = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->nouvelleCleEc() === false) {
            self::markTestSkipped(
                'OpenSSL ne peut pas générer de clé sur la courbe prime256v1 dans cet environnement. '
                . 'Vérifiez la variable OPENSSL_CONF : elle pointe parfois vers un fichier absent.'
            );
        }
    }

    public function test_la_cle_vapid_est_un_point_ec_non_compresse(): void
    {
        $service = new WebPushService($this->base());

        $publique = $service->clePubliqueVapid();
        self::assertIsString($publique);

        $brut = WebPushService::base64UrlDecode($publique);

        self::assertSame(65, strlen($brut));
        self::assertSame("\x04", $brut[0]);
    }

    public function test_la_cle_reste_identique_entre_deux_appels(): void
    {
        $service = new WebPushService($this->base());

        // Régénérer invaliderait tous les abonnements déjà enregistrés.
        self::assertSame($service->clePubliqueVapid(), $service->clePubliqueVapid());
    }

    public function test_la_signature_vapid_est_verifiable_par_openssl(): void
    {
        $service = new WebPushService($this->base());
        $publique = (string) $service->clePubliqueVapid();

        $entetes = $this->invoquer($service, 'entetesVapid', [
            'https://fcm.googleapis.com/fcm/send/abc',
            $publique,
            (string) $this->invoquer($service, 'reglage', ['vapid_private_key']),
        ]);

        self::assertCount(1, $entetes);
        self::assertStringStartsWith('Authorization: vapid t=', $entetes[0]);

        preg_match('/t=([^,]+), k=(.+)$/', $entetes[0], $trouve);
        [$entete, $charge, $signature] = explode('.', $trouve[1]);

        self::assertSame($publique, $trouve[2], 'La clé publique doit accompagner le jeton.');

        $donnees = json_decode(WebPushService::base64UrlDecode($charge), true);
        self::assertSame('https://fcm.googleapis.com', $donnees['aud'], 'L\'audience se limite à l\'origine du service.');
        self::assertGreaterThan(time(), $donnees['exp']);
        self::assertLessThanOrEqual(time() + 86400, $donnees['exp'], 'VAPID impose une validité inférieure à 24 h.');

        $brute = WebPushService::base64UrlDecode($signature);
        self::assertSame(64, strlen($brute));

        $verdict = openssl_verify(
            $entete . '.' . $charge,
            $this->signatureVersDer($brute),
            $this->pemPublic(WebPushService::base64UrlDecode($publique)),
            OPENSSL_ALGO_SHA256
        );

        self::assertSame(1, $verdict, 'La signature ES256 doit être vérifiable avec la clé publique publiée.');
    }

    public function test_le_message_chiffre_est_retrouve_a_l_identique_par_le_navigateur(): void
    {
        $service = new WebPushService($this->base());

        $cleClient = $this->nouvelleCleEc();
        $pointClient = $this->point($cleClient);
        $secretAuth = random_bytes(16);

        $clair = json_encode(
            ['titre' => 'Écart de caisse', 'corps' => 'Agence Abobo : -62 900 XOF'],
            JSON_UNESCAPED_UNICODE
        );

        $paquet = $this->invoquer($service, 'chiffrer', [
            $clair,
            WebPushService::base64Url($pointClient),
            WebPushService::base64Url($secretAuth),
        ]);

        $sel = substr($paquet, 0, 16);
        self::assertSame(4096, unpack('N', substr($paquet, 16, 4))[1]);

        $longueur = ord($paquet[20]);
        self::assertSame(65, $longueur);

        $pointServeur = substr($paquet, 21, $longueur);
        $corps = substr($paquet, 21 + $longueur);

        $partage = openssl_pkey_derive(openssl_pkey_get_public($this->pemPublic($pointServeur)), $cleClient, 32);
        self::assertNotFalse($partage);

        $prk = hash_hkdf('sha256', $partage, 32, "WebPush: info\x00" . $pointClient . $pointServeur, $secretAuth);
        $cleContenu = hash_hkdf('sha256', $prk, 16, "Content-Encoding: aes128gcm\x00", $sel);
        $nonce = hash_hkdf('sha256', $prk, 12, "Content-Encoding: nonce\x00", $sel);

        $dechiffre = openssl_decrypt(
            substr($corps, 0, -16),
            'aes-128-gcm',
            $cleContenu,
            OPENSSL_RAW_DATA,
            $nonce,
            substr($corps, -16)
        );

        self::assertNotFalse($dechiffre, 'Le navigateur doit pouvoir déchiffrer ce que le serveur a produit.');
        self::assertSame("\x02", substr($dechiffre, -1), 'Le délimiteur d\'enregistrement doit clore le message.');
        self::assertSame($clair, substr($dechiffre, 0, -1));
    }

    public function test_chaque_envoi_utilise_un_sel_et_une_cle_ephemere_neufs(): void
    {
        $service = new WebPushService($this->base());

        $cleClient = $this->nouvelleCleEc();
        $p256dh = WebPushService::base64Url($this->point($cleClient));
        $auth = WebPushService::base64Url(random_bytes(16));

        $premier = $this->invoquer($service, 'chiffrer', ['message', $p256dh, $auth]);
        $second = $this->invoquer($service, 'chiffrer', ['message', $p256dh, $auth]);

        self::assertNotSame($premier, $second, 'Réutiliser le sel exposerait les messages.');
    }

    public function test_une_cle_d_abonnement_invalide_est_rejetee(): void
    {
        $service = new WebPushService($this->base());

        $this->expectExceptionMessageMatches('/abonnement invalide/i');

        $this->invoquer($service, 'chiffrer', [
            'message',
            WebPushService::base64Url('trop court'),
            WebPushService::base64Url(random_bytes(16)),
        ]);
    }

    public function test_l_encodage_base64_url_fait_un_aller_retour(): void
    {
        foreach ([random_bytes(1), random_bytes(16), random_bytes(65)] as $donnees) {
            $encode = WebPushService::base64Url($donnees);

            self::assertStringNotContainsString('=', $encode);
            self::assertStringNotContainsString('+', $encode);
            self::assertStringNotContainsString('/', $encode);
            self::assertSame($donnees, WebPushService::base64UrlDecode($encode));
        }
    }

    // -----------------------------------------------------------------

    /**
     * @param array<int, mixed> $arguments
     */
    private function invoquer(WebPushService $service, string $methode, array $arguments): mixed
    {
        $reflexion = new \ReflectionMethod($service, $methode);
        $reflexion->setAccessible(true);

        return $reflexion->invokeArgs($service, $arguments);
    }

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

    private function pemPublic(string $point): string
    {
        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode(hex2bin(self::ENTETE_DER) . $point), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }

    /**
     * Convertit une signature JWS (r || s) en structure DER, seul format qu'accepte
     * openssl_verify.
     */
    private function signatureVersDer(string $brute): string
    {
        $entier = static function (string $valeur): string {
            $valeur = ltrim($valeur, "\x00");
            if ($valeur === '') {
                $valeur = "\x00";
            }
            if (ord($valeur[0]) > 0x7f) {
                $valeur = "\x00" . $valeur;
            }

            return "\x02" . chr(strlen($valeur)) . $valeur;
        };

        $sequence = $entier(substr($brute, 0, 32)) . $entier(substr($brute, 32));

        return "\x30" . chr(strlen($sequence)) . $sequence;
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
