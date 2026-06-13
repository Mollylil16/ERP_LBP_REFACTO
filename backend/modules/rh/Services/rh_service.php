<?php

namespace App\Modules\Rh\Services;

use App\Modules\Rh\Repositories\RhRepository;

class RhService
{
    public function __construct(private RhRepository $repository = new RhRepository()) {}

    public function listUsers(array $filters = []): array
    {
        return $this->repository->fetchUsers($filters);
    }

    public function getUser(int $id): array
    {
        $user = $this->repository->fetchUserById($id);
        if ($user === null) {
            throw new \RuntimeException('Utilisateur introuvable');
        }

        return $user;
    }

    public function createUser(array $payload): array
    {
        if (empty($payload['username']) || empty($payload['fullname'] ?? $payload['nom_complet'])) {
            throw new \InvalidArgumentException('Le nom d’utilisateur et le nom complet sont requis');
        }

        $plainPassword = $payload['password'] ?? $this->generateTemporaryPassword();
        $payload['password'] = password_hash($plainPassword, PASSWORD_DEFAULT);
        $payload['password_plain'] = $plainPassword;
        $payload['code_acces'] = $payload['code_acces'] ?? 1;
        $payload['must_change_password'] = $payload['must_change_password'] ?? true;
        $payload['isActive'] = $payload['isActive'] ?? true;
        $payload['agence_selected'] = $payload['agence_selected'] ?? false;

        $user = $this->repository->createUser($this->normalizePayload($payload));
        $user['temp_password'] = $plainPassword;

        return $user;
    }

    public function updateUser(int $id, array $payload): array
    {
        if (isset($payload['password'])) {
            $plainPassword = $payload['password'];
            $payload['password'] = password_hash($plainPassword, PASSWORD_DEFAULT);
            $payload['password_plain'] = $plainPassword;
        }

        $user = $this->repository->updateUser($id, $this->normalizePayload($payload));
        if ($user === null) {
            throw new \RuntimeException('Impossible de mettre à jour l’utilisateur');
        }

        return $user;
    }

    public function toggleUserActive(int $id): array
    {
        $user = $this->repository->toggleUserActive($id);
        if ($user === null) {
            throw new \RuntimeException('Utilisateur introuvable');
        }

        return $user;
    }

    public function deleteUser(int $id): void
    {
        $this->repository->deactivateUser($id);
    }

    public function resetPassword(int $id, ?string $newPassword = null): array
    {
        $plainPassword = $newPassword ?: $this->generateTemporaryPassword();
        $payload = [
            'password' => password_hash($plainPassword, PASSWORD_DEFAULT),
            'password_plain' => $plainPassword,
            'must_change_password' => true,
        ];

        $user = $this->repository->updateUser($id, $payload);
        if ($user === null) {
            throw new \RuntimeException('Utilisateur introuvable');
        }

        return [
            'user' => $user,
            'temp_password' => $plainPassword,
            'message' => 'Mot de passe temporaire enregistré',
        ];
    }

    public function changePassword(int $id, string $oldPassword, string $newPassword): array
    {
        $credentials = $this->repository->fetchCredentialsById($id);
        if ($credentials === null) {
            throw new \RuntimeException('Utilisateur introuvable');
        }

        $storedPassword = $credentials['password'];
        if (!password_verify($oldPassword, $storedPassword) && $storedPassword !== $oldPassword) {
            throw new \RuntimeException('Ancien mot de passe incorrect');
        }

        $payload = [
            'password' => password_hash($newPassword, PASSWORD_DEFAULT),
            'password_plain' => null,
            'must_change_password' => false,
        ];

        $user = $this->repository->updateUser($id, $payload);
        if ($user === null) {
            throw new \RuntimeException('Impossible de changer le mot de passe');
        }

        return ['message' => 'Mot de passe changé avec succès', 'user' => $user];
    }

    public function selectAgence(int $id, int $agenceId, ?float $lat = null, ?float $lng = null): array
    {
        $agence = $this->repository->findAgenceById($agenceId);
        if ($agence === null) {
            throw new \InvalidArgumentException('Agence introuvable');
        }

        $userCurrent = $this->repository->fetchUserById($id);

        $user = $this->repository->updateUser($id, [
            // On ne modifie pas l'agence d'origine (id_agence) si c'est juste un déplacement
            // mais on marque agence_selected à true. 
            // Note: si on doit changer l'agence courante, on peut le faire, mais on trace la position.
            'agence_selected' => true,
        ]);

        if ($user === null) {
            throw new \RuntimeException('Impossible de sélectionner l’agence');
        }

        // Si l'agence sélectionnée est différente de l'agence principale (ou même à chaque sélection), on log la position
        if ($lat !== null && $lng !== null) {
            $this->repository->logUserLocation($id, $agenceId, $lat, $lng);
        }

        return $user;
    }

    public function listRoles(): array
    {
        return $this->repository->listRoles();
    }

    public function listAgences(): array
    {
        return $this->repository->listAgences();
    }

    public function listPermissions(): array
    {
        return $this->repository->listPermissions();
    }

    private function normalizePayload(array $payload): array
    {
        if (isset($payload['nom_complet']) && !isset($payload['fullname'])) {
            $payload['fullname'] = $payload['nom_complet'];
            unset($payload['nom_complet']);
        }

        return $payload;
    }

    private function generateTemporaryPassword(int $length = 12): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_';
        $password = '';
        $maxIndex = strlen($chars) - 1;

        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $maxIndex)];
        }

        return $password;
    }
}
