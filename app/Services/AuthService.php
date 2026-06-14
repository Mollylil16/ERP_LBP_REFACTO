<?php

namespace App\Services;

use App\Repositories\UserRepository;

class AuthService
{
    public function __construct(private UserRepository $users) {}

    /**
     * @return array{success: bool, message: string, user?: \App\Models\User}
     */
    public function login(array $data): array
    {
        $identifier = trim((string) ($data['email'] ?? $data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($identifier === '' || $password === '') {
            return ['success' => false, 'message' => 'Identifiants invalides.'];
        }
        if (str_contains($identifier, '@') && !filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Identifiant invalide.'];
        }

        $user = $this->users->findByIdentifier(strtolower($identifier));
        if (!$user || !password_verify($password, $user->passwordHash)) {
            return ['success' => false, 'message' => 'Email ou mot de passe incorrect.'];
        }
        if ($user->status !== 'active') {
            return ['success' => false, 'message' => 'Ce compte n’est pas actif.'];
        }

        return [
            'success' => true,
            'message' => 'Connexion réussie.',
            'user' => $user,
        ];
    }
}
