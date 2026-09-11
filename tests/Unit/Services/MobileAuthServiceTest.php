<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Repositories\Mobile\MobileDeviceRepository;
use App\Services\Mobile\MobileAuthService;
use PDO;
use Tests\TestCase;

/**
 * Verrouillage de l'application de direction par code PIN.
 *
 * Les scénarios vérifiés ici protègent l'accès à l'ERP depuis un téléphone qui peut
 * être perdu ou volé : force du code, escalade du blocage, et refus du bon code tant
 * que le blocage court.
 */
final class MobileAuthServiceTest extends TestCase
{
    public function test_un_code_doit_faire_exactement_six_chiffres(): void
    {
        self::assertTrue(MobileAuthService::pinValide('482913'));
        self::assertFalse(MobileAuthService::pinValide('48291'));
        self::assertFalse(MobileAuthService::pinValide('4829134'));
        self::assertFalse(MobileAuthService::pinValide('48a913'));
        self::assertFalse(MobileAuthService::pinValide(''));
        self::assertFalse(MobileAuthService::pinValide('  4829'));
    }

    public function test_les_codes_devinables_sont_refuses(): void
    {
        foreach (['111111', '000000', '999999', '123456', '654321', '456789'] as $faible) {
            self::assertTrue(MobileAuthService::pinTropFaible($faible), "Le code {$faible} devrait être refusé.");
        }

        foreach (['482913', '740126', '305817'] as $correct) {
            self::assertFalse(MobileAuthService::pinTropFaible($correct), "Le code {$correct} devrait être accepté.");
        }
    }

    public function test_le_jeton_d_appareil_n_est_jamais_stocke_en_clair(): void
    {
        $pdo = $this->baseAppareils();
        $depot = new MobileDeviceRepository($pdo);

        $jeton = $depot->appairer(1, '482913', 'iPhone du DG', 'Mozilla/5.0');

        $ligne = $pdo->query('SELECT * FROM lbp_mobile_devices WHERE id = 1')->fetch(PDO::FETCH_ASSOC);

        self::assertNotSame($jeton, $ligne['token_hash']);
        self::assertSame(hash('sha256', $jeton), $ligne['token_hash']);
        self::assertNotSame('482913', $ligne['pin_hash']);
        self::assertTrue(password_verify('482913', $ligne['pin_hash']));
    }

    public function test_un_jeton_inconnu_ne_retrouve_aucun_appareil(): void
    {
        $depot = new MobileDeviceRepository($this->baseAppareils());
        $jeton = $depot->appairer(1, '482913', null, null);

        self::assertNotNull($depot->trouverParJeton($jeton));
        self::assertNull($depot->trouverParJeton('jeton-invente'));
    }

    public function test_le_blocage_se_declenche_au_cinquieme_echec(): void
    {
        [$service, $depot, $jeton] = $this->appareilAppaire();

        for ($essai = 1; $essai <= 4; $essai++) {
            $resultat = $service->deverrouiller($depot->trouverParJeton($jeton), '000000');
            self::assertFalse($resultat['ok']);
            self::assertSame(0, $resultat['bloque'], "Aucun blocage attendu au {$essai}e échec.");
        }

        $resultat = $service->deverrouiller($depot->trouverParJeton($jeton), '000000');

        self::assertFalse($resultat['ok']);
        self::assertGreaterThan(0, $resultat['bloque']);
        self::assertStringContainsString('bloqu', $resultat['message']);
    }

    public function test_le_bon_code_reste_refuse_pendant_le_blocage(): void
    {
        [$service, $depot, $jeton] = $this->appareilAppaire();

        for ($essai = 1; $essai <= 5; $essai++) {
            $service->deverrouiller($depot->trouverParJeton($jeton), '000000');
        }

        $resultat = $service->deverrouiller($depot->trouverParJeton($jeton), '482913');

        self::assertFalse($resultat['ok'], 'Un blocage qui laisse passer le bon code ne protège de rien.');
    }

    public function test_le_compteur_repart_de_zero_apres_un_deverrouillage(): void
    {
        [$service, $depot, $jeton, $pdo] = $this->appareilAppaire();

        $service->deverrouiller($depot->trouverParJeton($jeton), '000000');
        $service->deverrouiller($depot->trouverParJeton($jeton), '000000');

        self::assertSame(2, (int) $depot->trouverParJeton($jeton)['failed_attempts']);

        $resultat = $service->deverrouiller($depot->trouverParJeton($jeton), '482913');

        self::assertTrue($resultat['ok']);
        self::assertSame(0, (int) $depot->trouverParJeton($jeton)['failed_attempts']);
    }

    public function test_un_appareil_revoque_n_est_plus_reconnu(): void
    {
        [$service, $depot, $jeton] = $this->appareilAppaire();

        $_COOKIE[MobileAuthService::COOKIE_APPAREIL] = $jeton;
        self::assertNotNull($service->appareilCourant());

        $depot->revoquer(1);

        self::assertNull($service->appareilCourant(), 'Un téléphone révoqué doit perdre son accès immédiatement.');
    }

    public function test_un_compte_desactive_ne_peut_plus_deverrouiller(): void
    {
        $pdo = $this->baseAppareils();
        $pdo->exec("UPDATE users SET status = 'inactive' WHERE id = 1");

        $depot = new MobileDeviceRepository($pdo);
        $service = new MobileAuthService($depot);
        $jeton = $depot->appairer(1, '482913', null, null);

        $_COOKIE[MobileAuthService::COOKIE_APPAREIL] = $jeton;

        self::assertNull($service->appareilCourant());
    }

    public function test_changer_le_code_leve_le_blocage_en_cours(): void
    {
        $depot = new MobileDeviceRepository($this->baseAppareils());
        $jeton = $depot->appairer(1, '482913', null, null);

        $depot->enregistrerEchec(1, 5, date('Y-m-d H:i:s', time() + 600));
        $depot->changerPin(1, '740126');

        $appareil = $depot->trouverParJeton($jeton);

        self::assertTrue(password_verify('740126', $appareil['pin_hash']));
        self::assertFalse(password_verify('482913', $appareil['pin_hash']));
        self::assertSame(0, (int) $appareil['failed_attempts']);
        self::assertNull($appareil['locked_until']);
    }

    // -----------------------------------------------------------------

    /**
     * @return array{0: MobileAuthService, 1: MobileDeviceRepository, 2: string, 3: PDO}
     */
    private function appareilAppaire(): array
    {
        $pdo = $this->baseAppareils();
        $depot = new MobileDeviceRepository($pdo);
        $service = new MobileAuthService($depot);
        $jeton = $depot->appairer(1, '482913', 'Appareil de test', null);

        return [$service, $depot, $jeton, $pdo];
    }

    /**
     * Base en mémoire reproduisant les deux tables nécessaires. NOW() est traduit
     * car SQLite ne le connaît pas.
     */
    private function baseAppareils(): PDO
    {
        $pdo = new class ('sqlite::memory:') extends PDO {
            public function prepare(string $query, array $options = []): \PDOStatement|false
            {
                return parent::prepare(str_replace('NOW()', "datetime('now')", $query), $options);
            }
        };

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, full_name TEXT, email TEXT, status TEXT DEFAULT \'active\')');
        $pdo->exec("INSERT INTO users VALUES (1, 'Directeur Général', 'dg@lbp.ci', 'active')");
        $pdo->exec('CREATE TABLE lbp_mobile_devices (
            id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, token_hash TEXT, pin_hash TEXT,
            label TEXT, user_agent TEXT, failed_attempts INTEGER DEFAULT 0, locked_until TEXT,
            last_unlocked_at TEXT, last_seen_at TEXT, revoked_at TEXT, created_at TEXT)');

        return $pdo;
    }
}
