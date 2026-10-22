<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\DureeSession;
use PHPUnit\Framework\TestCase;

final class DureeSessionTest extends TestCase
{
    private const T0 = 1_800_000_000;

    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function connecter(int $a = self::T0): void
    {
        $_SESSION['auth_user_id'] = 18;
        DureeSession::ouvrir(false, $a);
    }

    public function testUnVisiteurNonConnecteNestPasConcerne(): void
    {
        self::assertTrue(DureeSession::controler('/login', self::T0));
        self::assertArrayNotHasKey('session_activite_at', $_SESSION);
    }

    public function testUneActiviteRepousseLaFermeture(): void
    {
        $this->connecter();

        self::assertTrue(DureeSession::controler('/colisage', self::T0 + 7000));
        self::assertTrue(DureeSession::controler('/colisage', self::T0 + 14000));
        self::assertSame(18, $_SESSION['auth_user_id']);
    }

    public function testDeuxHeuresSansActiviteFerment(): void
    {
        $this->connecter();

        self::assertFalse(DureeSession::controler('/colisage', self::T0 + DureeSession::INACTIVITE_MAX + 1));
        self::assertArrayNotHasKey('auth_user_id', $_SESSION);
        self::assertStringContainsString('2 heures', $_SESSION['_flash']['error'] ?? '');
    }

    public function testLeReleveDeLaMessagerieNeComptePasCommeActivite(): void
    {
        $this->connecter();

        // Le relevé toutes les 3 s pendant deux heures ne garde pas la porte ouverte.
        for ($t = 3; $t <= DureeSession::INACTIVITE_MAX; $t += 600) {
            self::assertTrue(DureeSession::controler('/ERP_LBP_REFACTO/messagerie/api/nouveautes', self::T0 + $t));
        }
        self::assertFalse(DureeSession::controler('/messagerie/api/nouveautes', self::T0 + DureeSession::INACTIVITE_MAX + 1));
    }

    public function testLeReleveEstServiTantQueLaSessionVit(): void
    {
        $this->connecter();

        self::assertTrue(DureeSession::controler('/messagerie/api/nouveautes', self::T0 + 60));
        self::assertSame(18, $_SESSION['auth_user_id']);
    }

    public function testDouzeHeuresAuPlusMemeEnTravaillant(): void
    {
        $this->connecter();

        for ($t = 3600; $t <= DureeSession::DUREE_MAX; $t += 3600) {
            self::assertTrue(DureeSession::controler('/finance', self::T0 + $t));
        }
        self::assertFalse(DureeSession::controler('/finance', self::T0 + DureeSession::DUREE_MAX + 60));
        self::assertStringContainsString('12 heures', $_SESSION['_flash']['error'] ?? '');
    }

    public function testUneSessionAnterieureALaRegleRepartDeMaintenant(): void
    {
        $_SESSION['auth_user_id'] = 18;

        self::assertTrue(DureeSession::controler('/colisage', self::T0));
        self::assertSame(self::T0, $_SESSION['session_ouverte_at']);
        self::assertSame(18, $_SESSION['auth_user_id']);
    }

    public function testSeulsLesRelevesListesSontPassifs(): void
    {
        self::assertTrue(DureeSession::estReleve('/messagerie/api/nouveautes/'));
        self::assertFalse(DureeSession::estReleve('/messagerie/api/conversations/3/messages'));
        self::assertFalse(DureeSession::estReleve('/messagerie'));
    }
}
