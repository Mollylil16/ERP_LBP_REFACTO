<?php

namespace App\Services\Admin;

use App\Helpers\Auth;
use App\Models\User;
use App\Repositories\Admin\PermissionRepository;
use App\Repositories\Rh\RhPersonnelRepository;
use App\Repositories\Admin\UserRepository;
use App\Security\PermissionAction;
use PDO;
use RuntimeException;

class AdminService
{
    public function __construct(
        private UserRepository $users,
        private PermissionRepository $permissions,
        private RhPersonnelRepository $personnel,
        private PDO $pdo,
    ) {}

    public function dashboard(): array
    {
        return [
            'statistics' => $this->users->statistics(),
            'entities' => $this->permissions->entities(),
            'grantedPermissions' => $this->permissions->grantedCount(),
        ];
    }

    public function listUsers(array $query): array
    {
        $status = (string) ($query['status'] ?? '');
        if (!in_array($status, ['', 'active', 'inactive', 'blocked'], true)) {
            $status = '';
        }
        $profile = (string) ($query['profile'] ?? '');
        if (!in_array($profile, ['', 'admin', 'user'], true)) {
            $profile = '';
        }
        $filters = [
            'q' => trim((string) ($query['q'] ?? '')),
            'status' => $status,
            'profile' => $profile,
        ];

        return [
            'filters' => $filters,
            'pagination' => $this->users->paginate($filters, (int) ($query['page'] ?? 1)),
        ];
    }

    public function user(int $id): array
    {
        $user = $this->requireUser($id);
        return [
            'user' => $user,
            'employee' => $user->rhEmployeeId ? $this->personnel->find((int) $user->rhEmployeeId) : null,
            'permissions' => $this->permissions->forUser($id),
        ];
    }

    public function userCreationData(): array
    {
        return [
            'employees' => $this->personnel->availableForUserAccount(),
            'permissions' => $this->permissions->forUser(0),
        ];
    }

    public function createUser(array $input): int
    {
        $employeeId = (int) ($input['rh_employee_id'] ?? 0);
        $employee = $this->personnel->findForUserAccount($employeeId);
        if (!$employee) {
            throw new RuntimeException('Le profil RH sélectionné est invalide ou possède déjà un compte.');
        }
        if (trim((string) ($employee['email'] ?? '')) === '') {
            throw new RuntimeException('Le profil RH doit disposer d’une adresse email avant la création du compte.');
        }

        $data = $this->validateAccountSettings($input, true);
        $email = strtolower(trim((string) $employee['email']));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('L’adresse email du profil RH est invalide.');
        }
        if ($this->users->emailExists($email)) {
            throw new RuntimeException('L’adresse email du profil RH est déjà utilisée.');
        }

        $this->pdo->beginTransaction();
        try {
            $id = $this->users->create(new User(
                id: null,
                fullName: (string) $employee['full_name'],
                email: $email,
                phone: $employee['phone'] ?: null,
                passwordHash: (string) $data['password_hash'],
                status: 'active',
                isAdmin: $data['is_admin'],
                rhEmployeeId: $employeeId,
            ));
            if (!$data['is_admin']) {
                $this->replacePermissions($id, $input);
            }
            $this->pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function updateUser(int $id, array $input, int $actorId): void
    {
        $user = $this->requireUser($id);
        $data = $this->validateAccountSettings($input, false);
        $data['status'] = $user->status;
        $rhProfile = $user->rhEmployeeId ? $this->personnel->find((int) $user->rhEmployeeId) : null;
        if ($rhProfile) {
            $data['full_name'] = (string) $rhProfile['full_name'];
            $data['email'] = strtolower(trim((string) $rhProfile['email']));
            $data['phone'] = $rhProfile['phone'] ?: null;
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('L’adresse email du profil RH est invalide.');
            }
            if ($this->users->emailExists($data['email'], $id)) {
                throw new RuntimeException('L’adresse email du profil RH est déjà utilisée.');
            }
        } else {
            $data['full_name'] = $user->fullName;
            $data['email'] = $user->email;
            $data['phone'] = $user->phone;
        }
        if ($id === $actorId && (!$data['is_admin'] || $data['status'] !== 'active')) {
            throw new RuntimeException('Vous ne pouvez pas retirer votre propre accès administrateur ni désactiver votre compte.');
        }
        $this->users->updateFromAdmin($id, $data);
    }

    public function setUserActive(int $id, bool $active, int $actorId): void
    {
        $this->requireUser($id);
        if (!$active && $id === $actorId) {
            throw new RuntimeException('Vous ne pouvez pas désactiver votre propre compte.');
        }
        $this->users->setStatus($id, $active ? 'active' : 'inactive');
    }

    public function savePermissions(int $userId, array $input): void
    {
        $user = $this->requireUser($userId);
        if ($user->isAdmin) {
            throw new RuntimeException('Un administrateur dispose déjà de tous les droits.');
        }

        $this->replacePermissions($userId, $input);
    }

    private function replacePermissions(int $userId, array $input): void
    {
        $allowedIds = array_map(
            static fn($entity): int => (int) $entity->id,
            $this->permissions->entities()
        );
        $submitted = is_array($input['permissions'] ?? null) ? $input['permissions'] : [];
        $permissions = [];
        foreach ($submitted as $entityId => $rights) {
            $entityId = (int) $entityId;
            if (in_array($entityId, $allowedIds, true) && is_array($rights)) {
                $permissions[$entityId] = PermissionAction::normalize($rights);
            }
        }
        $this->permissions->replaceForUser($userId, $permissions);
        Auth::reset();
    }

    public function matrix(): array
    {
        return [
            'entities' => $this->permissions->entities(),
            'users' => $this->permissions->matrix(),
        ];
    }

    private function validateAccountSettings(array $input, bool $passwordRequired): array
    {
        $password = (string) ($input['password'] ?? '');
        if ($passwordRequired && strlen($password) < 8) {
            throw new RuntimeException('Le mot de passe doit contenir au moins 8 caractères.');
        }
        if (!$passwordRequired && $password !== '' && strlen($password) < 8) {
            throw new RuntimeException('Le nouveau mot de passe doit contenir au moins 8 caractères.');
        }

        return [
            'password_hash' => $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null,
            'is_admin' => isset($input['is_admin']) && $input['is_admin'] === '1',
        ];
    }

    private function requireUser(int $id): User
    {
        $user = $this->users->findById($id);
        if (!$user) {
            throw new RuntimeException('Utilisateur introuvable.');
        }
        return $user;
    }
}
