<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\User;
use App\Repositories\Admin\UserRepository;
use App\Services\Auth\AuthService;
use Tests\TestCase;

final class AuthServiceTest extends TestCase
{
    public function test_login_rejects_empty_credentials(): void
    {
        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::never())->method('findByIdentifier');

        $result = (new AuthService($repository))->login(['email' => '', 'password' => '']);

        self::assertFalse($result['success']);
        self::assertSame('Identifiants invalides.', $result['message']);
    }

    public function test_login_rejects_invalid_email_format(): void
    {
        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::never())->method('findByIdentifier');

        $result = (new AuthService($repository))->login(['email' => 'admin@@erp.local', 'password' => 'secret']);

        self::assertFalse($result['success']);
        self::assertSame('Identifiant invalide.', $result['message']);
    }

    public function test_login_rejects_unknown_user_or_wrong_password(): void
    {
        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::once())
            ->method('findByIdentifier')
            ->with('admin@erp-lbp.local')
            ->willReturn(null);

        $result = (new AuthService($repository))->login(['email' => 'admin@erp-lbp.local', 'password' => 'bad']);

        self::assertFalse($result['success']);
        self::assertSame('Email ou mot de passe incorrect.', $result['message']);
    }

    public function test_login_rejects_inactive_user(): void
    {
        $user = new User(
            id: 7,
            fullName: 'Admin Test',
            email: 'admin@erp-lbp.local',
            phone: null,
            passwordHash: password_hash('secret', PASSWORD_DEFAULT),
            status: 'inactive',
            isAdmin: true,
        );

        $repository = $this->createStub(UserRepository::class);
        $repository->method('findByIdentifier')->willReturn($user);

        $result = (new AuthService($repository))->login(['email' => 'admin@erp-lbp.local', 'password' => 'secret']);

        self::assertFalse($result['success']);
        self::assertSame('Ce compte n’est pas actif.', $result['message']);
    }

    public function test_login_accepts_active_user_with_valid_password(): void
    {
        $user = new User(
            id: 7,
            fullName: 'Admin Test',
            email: 'admin@erp-lbp.local',
            phone: null,
            passwordHash: password_hash('secret', PASSWORD_DEFAULT),
            status: 'active',
            isAdmin: true,
        );

        $repository = $this->createStub(UserRepository::class);
        $repository->method('findByIdentifier')->willReturn($user);

        $result = (new AuthService($repository))->login(['email' => 'ADMIN@ERP-LBP.LOCAL', 'password' => 'secret']);

        self::assertTrue($result['success']);
        self::assertSame('Connexion réussie.', $result['message']);
        self::assertSame($user, $result['user']);
    }
}
