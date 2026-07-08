<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Helpers\Auth;
use App\Helpers\Session;
use App\Models\User;
use App\Models\Database;
use Tests\TestCase;

final class LbpSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Auth::reset();
        Session::forget('auth_user_id');
    }

    protected function tearDown(): void
    {
        Auth::reset();
        Session::forget('auth_user_id');
        parent::tearDown();
    }

    public function test_has_role_identifies_assigned_roles(): void
    {
        // On mocke un utilisateur avec le rôle caissière
        $user = new User(
            id: 10,
            fullName: 'Test Caissiere',
            email: 'caissiere@lbp.local',
            phone: '0102030405',
            passwordHash: 'hash',
            status: 'active',
            isAdmin: false,
            agenceId: 1,
            zoneRegionaleId: null,
            roles: ['caissiere']
        );

        // Configurer la session
        Session::set('auth_user_id', 10);
        
        // Injecter l'utilisateur connecté dans le cache pour éviter d'interroger la base de données
        $ref = new \ReflectionClass(Auth::class);
        $cachedUserProp = $ref->getProperty('cachedUser');
        $cachedUserProp->setAccessible(true);
        $cachedUserProp->setValue(null, $user);

        $cachedUserIdProp = $ref->getProperty('cachedUserId');
        $cachedUserIdProp->setAccessible(true);
        $cachedUserIdProp->setValue(null, 10);

        self::assertTrue(Auth::hasRole('caissiere'));
        self::assertFalse(Auth::hasRole('dg'));
        self::assertTrue(Auth::hasAnyRole(['caissiere', 'dg']));
        self::assertFalse(Auth::hasAnyRole(['comptable', 'dg']));
    }

    public function test_admin_passes_all_role_checks(): void
    {
        $user = new User(
            id: 1,
            fullName: 'Admin',
            email: 'admin@lbp.local',
            phone: '0102030405',
            passwordHash: 'hash',
            status: 'active',
            isAdmin: true,
            roles: [] // Aucun rôle spécifique
        );

        Session::set('auth_user_id', 1);
        
        $ref = new \ReflectionClass(Auth::class);
        $cachedUserProp = $ref->getProperty('cachedUser');
        $cachedUserProp->setAccessible(true);
        $cachedUserProp->setValue(null, $user);

        $cachedUserIdProp = $ref->getProperty('cachedUserId');
        $cachedUserIdProp->setAccessible(true);
        $cachedUserIdProp->setValue(null, 1);

        self::assertTrue(Auth::hasRole('comptable'));
        self::assertTrue(Auth::hasAnyRole(['caissiere', 'dg']));
    }

    public function test_agency_scope_restriction_for_local_role(): void
    {
        $user = new User(
            id: 11,
            fullName: 'Local Agent',
            email: 'agent@lbp.local',
            phone: '0102030405',
            passwordHash: 'hash',
            status: 'active',
            isAdmin: false,
            agenceId: 2, // Agence ID 2
            zoneRegionaleId: null,
            roles: ['agent_groupage']
        );

        Session::set('auth_user_id', 11);
        
        $ref = new \ReflectionClass(Auth::class);
        $cachedUserProp = $ref->getProperty('cachedUser');
        $cachedUserProp->setAccessible(true);
        $cachedUserProp->setValue(null, $user);

        $cachedUserIdProp = $ref->getProperty('cachedUserId');
        $cachedUserIdProp->setAccessible(true);
        $cachedUserIdProp->setValue(null, 11);

        // Un rôle local à l'agence 2 peut accéder à l'agence 2 mais pas à l'agence 1
        self::assertTrue(Auth::checkAgencyScope(2));
        self::assertFalse(Auth::checkAgencyScope(1));
    }

    public function test_agency_scope_restriction_for_global_role(): void
    {
        $user = new User(
            id: 12,
            fullName: 'DG Account',
            email: 'dg@lbp.local',
            phone: '0102030405',
            passwordHash: 'hash',
            status: 'active',
            isAdmin: false,
            agenceId: null,
            zoneRegionaleId: null,
            roles: ['dg']
        );

        Session::set('auth_user_id', 12);
        
        $ref = new \ReflectionClass(Auth::class);
        $cachedUserProp = $ref->getProperty('cachedUser');
        $cachedUserProp->setAccessible(true);
        $cachedUserProp->setValue(null, $user);

        $cachedUserIdProp = $ref->getProperty('cachedUserId');
        $cachedUserIdProp->setAccessible(true);
        $cachedUserIdProp->setValue(null, 12);

        // Un rôle global (dg) a accès à toutes les agences
        self::assertTrue(Auth::checkAgencyScope(1));
        self::assertTrue(Auth::checkAgencyScope(2));
        self::assertTrue(Auth::checkAgencyScope(999));
    }
}
