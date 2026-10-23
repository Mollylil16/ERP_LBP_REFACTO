<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\MotDePasseProvisoire;
use PHPUnit\Framework\TestCase;

final class MotDePasseProvisoireTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testLeProvisoireSeDicteEtNeSeRepetePas(): void
    {
        $vus = [];
        for ($i = 0; $i < 50; $i++) {
            $mdp = MotDePasseProvisoire::generer();
            self::assertMatchesRegularExpression('/^[a-km-zA-HJ-NP-Z2-9]{4}-[a-km-zA-HJ-NP-Z2-9]{4}-[a-km-zA-HJ-NP-Z2-9]{4}$/', $mdp);
            self::assertDoesNotMatchRegularExpression('/[01lIoO]/', $mdp, 'Signes faciles à confondre au téléphone.');
            $vus[$mdp] = true;
        }
        self::assertCount(50, $vus);
    }

    public function testUnBonMotDePasseEstAccepte(): void
    {
        self::assertNull(MotDePasseProvisoire::refus('fret du lundi matin', 'fret du lundi matin', 'Kx7m-Qp4r-Tz9w', 'prince.kadjo@labelleporte.ci'));
    }

    /** @return array<string, array{0:string, 1:string, 2:string}> */
    public static function refus(): array
    {
        return [
            'confirmation différente' => ['fret du lundi matin', 'fret du lundi soir', 'identiques'],
            'trop court' => ['court', 'court', 'au moins 10'],
            'le provisoire lui-même' => ['Kx7m-Qp4r-Tz9w', 'Kx7m-Qp4r-Tz9w', 'différent'],
            'mot de passe public' => ['LBP2026', 'LBP2026', 'au moins 10'],
            'mot de passe connu' => ['1234567890', '1234567890', 'trop connu'],
            'reprend l’e-mail' => ['prince.kadjo2026', 'prince.kadjo2026', 'adresse e-mail'],
        ];
    }

    /** @dataProvider refus */
    #[\PHPUnit\Framework\Attributes\DataProvider('refus')]
    public function testLesMauvaisChoixSontRefusesAvecUneRaison(string $nouveau, string $confirmation, string $attendu): void
    {
        $refus = MotDePasseProvisoire::refus($nouveau, $confirmation, 'Kx7m-Qp4r-Tz9w', 'prince.kadjo@labelleporte.ci');

        self::assertNotNull($refus);
        self::assertStringContainsString($attendu, $refus);
    }

    public function testLExigenceNeVautQuePourUnUtilisateurConnecte(): void
    {
        MotDePasseProvisoire::exiger(true);
        self::assertFalse(MotDePasseProvisoire::estExige());

        $_SESSION['auth_user_id'] = 44;
        self::assertTrue(MotDePasseProvisoire::estExige());

        MotDePasseProvisoire::exiger(false);
        self::assertFalse(MotDePasseProvisoire::estExige());
    }

    public function testSeulsLEcranDuMotDePasseEtLaDeconnexionRestentOuverts(): void
    {
        self::assertTrue(MotDePasseProvisoire::estLibre('/compte/mot-de-passe'));
        self::assertTrue(MotDePasseProvisoire::estLibre('/ERP_LBP_REFACTO/compte/mot-de-passe/'));
        self::assertTrue(MotDePasseProvisoire::estLibre('/logout'));
        self::assertFalse(MotDePasseProvisoire::estLibre('/selection_portail'));
        self::assertFalse(MotDePasseProvisoire::estLibre('/messagerie/api/nouveautes'));
        self::assertFalse(MotDePasseProvisoire::estLibre('/compte/mot-de-passe-oublie-faux'));
    }
}
