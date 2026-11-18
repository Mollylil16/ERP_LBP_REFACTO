<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Helpers\Auth;
use App\Helpers\Session;
use App\Models\User;
use App\Security\AccesEtendu;
use Tests\TestCase;

/**
 * Accès étendus décidés le 18/11/2026 : l'accès Finance de Soro, l'accès à
 * tous les modules de la responsable RH — jamais l'Administration, le
 * Pilotage DG, le module RH réservé, ni un écran que son module réserve à
 * la direction.
 */
final class AccesEtenduTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->connecter(null);
        parent::tearDown();
    }

    /** @param array<int, string>|null $roles */
    private function connecter(?array $roles): void
    {
        $user = $roles === null ? null : new User(
            id: 41, fullName: 'Agent', email: 'agent@labelleporte.ci', phone: null,
            passwordHash: '', roles: $roles,
        );
        Session::set('auth_user_id', $roles === null ? null : 41);
        $ref = new \ReflectionClass(Auth::class);
        $ref->getProperty('cachedUser')->setValue(null, $user);
        $ref->getProperty('cachedUserId')->setValue(null, $roles === null ? null : 41);
    }

    public function testLAccesFinanceOuvreToutLeModuleFinanceEtRienDAutre(): void
    {
        $this->connecter(['rh_agent', AccesEtendu::ROLE_FINANCE]);

        self::assertTrue(AccesEtendu::ouvre('/finance/factures', ['comptable']));
        self::assertTrue(AccesEtendu::ouvre('/ERP_LBP_REFACTO/finance/fonds', ['dg', 'comptable']));
        self::assertFalse(AccesEtendu::ouvre('/finance/controle-caisse', ['dg']), 'Le contrôle des caisses reste au DG.');
        self::assertFalse(AccesEtendu::ouvre('/colisage/parcels', ['agent_saisie']));
        self::assertFalse(AccesEtendu::tousModules());
    }

    public function testLAccesATousLesModulesRespecteLesReservesDeLaDirection(): void
    {
        $this->connecter(['responsable_rh', AccesEtendu::ROLE_TOUS_MODULES]);

        self::assertTrue(AccesEtendu::tousModules());
        self::assertTrue(AccesEtendu::ouvre('/colisage/parcels', ['agent_saisie', 'chef_agence']));
        self::assertTrue(AccesEtendu::ouvre('/logistique/dashboard', ['dg', 'responsable_logistique']));
        self::assertFalse(AccesEtendu::ouvre('/call-center/direction', ['dg']), 'Une page réservée au DG reste au DG.');
        self::assertFalse(AccesEtendu::ouvre('/pilotage-dg/dashboard', ['dg', 'assistant_dg']));
        self::assertFalse(AccesEtendu::ouvre('/admin/users', ['admin']));
        self::assertFalse(AccesEtendu::ouvre('/finance/controle-caisse', ['dg', 'comptable']));
    }

    public function testSansRoleEtenduRienNeSOuvre(): void
    {
        $this->connecter(['agent_saisie']);

        self::assertFalse(AccesEtendu::ouvre('/finance/factures', ['comptable']));
        self::assertFalse(AccesEtendu::ouvre('/colisage/parcels', ['chef_agence']));
    }
}
