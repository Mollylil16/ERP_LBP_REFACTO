<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;

/**
 * Contient toute la logique métier liée à l'authentification.
 */
class AuthService
{
    public function __construct(private UserRepository $users) {}

    /**
     * Inscrit un nouvel utilisateur après validation métier.
     *
     * @return array{success: bool, message: string, user_id?: int}
     */
    public function register(array $data): array
    {
        $fullName = trim((string) ($data['full_name'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $phone = trim((string) ($data['phone'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $passwordConfirmation = (string) ($data['password_confirmation'] ?? '');

        if ($fullName === '') {
            return ['success' => false, 'message' => 'Le nom complet est obligatoire.'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Adresse email invalide.'];
        }

        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Le mot de passe doit contenir au moins 8 caractères.'];
        }

        if ($password !== $passwordConfirmation) {
            return ['success' => false, 'message' => 'Les mots de passe ne correspondent pas.'];
        }

        if ($this->users->findByEmail($email)) {
            return ['success' => false, 'message' => 'Cette adresse email est déjà utilisée.'];
        }

        $user = new User(
            id: null,
            fullName: $fullName,
            email: $email,
            phone: $phone !== '' ? $phone : null,
            passwordHash: password_hash($password, PASSWORD_DEFAULT),
        );

        $userId = $this->users->create($user);

        return [
            'success' => true,
            'message' => 'Compte créé avec succès.',
            'user_id' => $userId,
        ];
    }

    /**
     * Connecte un utilisateur avec son email et son mot de passe.
     *
     * @return array{success: bool, message: string, user?: User}
     */
    public function login(array $data): array
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            return ['success' => false, 'message' => 'Identifiants invalides.'];
        }

        $user = $this->users->findByEmail($email);

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
