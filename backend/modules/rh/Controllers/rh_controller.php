<?php

namespace App\Modules\Rh\Controllers;

use App\Controllers\BaseController;
use App\Modules\Rh\Services\RhService;
use App\Core\Response;

class RhController extends BaseController
{
    public function __construct(private RhService $service = new RhService()) {}

    public function index(): void
    {
        Response::success([
            'module' => 'rh',
            'message' => 'RH module opérationnel',
            'endpoints' => [
                '/api/rh/users',
                '/api/rh/users/:id',
                '/api/rh/roles',
                '/api/rh/agences',
                '/api/rh/permissions',
            ],
        ]);
    }

    public function listUsers(): void
    {
        $this->checkPermission('users.read');
        $users = $this->service->listUsers($this->getQueryParams());
        Response::success(['users' => $users]);
    }

    public function getUser(int $id): void
    {
        $authUser = $this->authenticate();

        if ($authUser['id'] !== $id) {
            $this->checkPermission('users.read');
        }

        try {
            $user = $this->service->getUser($id);
            Response::success(['user' => $user]);
        } catch (\RuntimeException $exception) {
            Response::error($exception->getMessage(), 404);
        }
    }

    public function createUser(): void
    {
        $this->checkPermission('users.create');
        $payload = $this->getRequestBody();

        try {
            $user = $this->service->createUser($payload);
            Response::success(['user' => $user], 'Utilisateur créé');
        } catch (\InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 400);
        } catch (\RuntimeException $exception) {
            Response::error($exception->getMessage(), 500);
        }
    }

    public function updateUser(int $id): void
    {
        $this->checkPermission('users.update');
        $payload = $this->getRequestBody();

        try {
            $user = $this->service->updateUser($id, $payload);
            Response::success(['user' => $user], 'Utilisateur mis à jour');
        } catch (\InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 400);
        } catch (\RuntimeException $exception) {
            Response::error($exception->getMessage(), 500);
        }
    }

    public function toggleActive(int $id): void
    {
        $this->checkPermission('users.update');

        try {
            $user = $this->service->toggleUserActive($id);
            Response::success(['user' => $user], 'Statut d’activation mis à jour');
        } catch (\RuntimeException $exception) {
            Response::error($exception->getMessage(), 404);
        }
    }

    public function deleteUser(int $id): void
    {
        $this->checkPermission('users.delete');

        try {
            $this->service->deleteUser($id);
            Response::success([], 'Utilisateur désactivé');
        } catch (\RuntimeException $exception) {
            Response::error($exception->getMessage(), 500);
        }
    }

    public function resetPassword(int $id): void
    {
        $this->checkPermission('users.update');
        $payload = $this->getRequestBody();

        try {
            $result = $this->service->resetPassword($id, $payload['new_password'] ?? null);
            Response::success($result, 'Mot de passe réinitialisé');
        } catch (\InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 400);
        } catch (\RuntimeException $exception) {
            Response::error($exception->getMessage(), 500);
        }
    }

    public function changePassword(int $id): void
    {
        $authUser = $this->authenticate();
        if ($authUser['id'] !== $id) {
            Response::error('Vous ne pouvez changer que votre propre mot de passe', 403);
        }
        $this->checkPermission('setup.bypass');

        $payload = $this->getRequestBody();
        $oldPassword = $payload['old_password'] ?? '';
        $newPassword = $payload['new_password'] ?? '';

        if ($oldPassword === '' || $newPassword === '') {
            Response::error('Les anciens et nouveaux mots de passe sont requis', 400);
        }

        try {
            $result = $this->service->changePassword($id, $oldPassword, $newPassword);
            Response::success($result, 'Mot de passe changé');
        } catch (\RuntimeException $exception) {
            Response::error($exception->getMessage(), 400);
        }
    }

    public function selectAgence(int $id): void
    {
        $authUser = $this->authenticate();
        if ($authUser['id'] !== $id) {
            $this->checkPermission('users.update');
        } else {
            $this->checkPermission('setup.bypass');
        }

        $payload = $this->getRequestBody();
        $agenceId = isset($payload['agence_id']) ? (int) $payload['agence_id'] : 0;
        
        // Données de géolocalisation
        $lat = isset($payload['latitude']) ? (float) $payload['latitude'] : null;
        $lng = isset($payload['longitude']) ? (float) $payload['longitude'] : null;

        if ($agenceId <= 0) {
            Response::error('L’identifiant de l’agence est requis', 400);
        }

        try {
            $user = $this->service->selectAgence($id, $agenceId, $lat, $lng);
            Response::success(['user' => $user], 'Agence sélectionnée');
        } catch (\InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 400);
        } catch (\RuntimeException $exception) {
            Response::error($exception->getMessage(), 500);
        }
    }

    public function listRoles(): void
    {
        $this->checkPermission('users.read');
        $roles = $this->service->listRoles();
        Response::success(['roles' => $roles]);
    }

    public function listAgences(): void
    {
        $this->checkPermission('users.read');
        $agences = $this->service->listAgences();
        Response::success(['agences' => $agences]);
    }

    public function listPermissions(): void
    {
        $this->checkPermission('users.read');
        $permissions = $this->service->listPermissions();
        Response::success(['permissions' => $permissions]);
    }
}
