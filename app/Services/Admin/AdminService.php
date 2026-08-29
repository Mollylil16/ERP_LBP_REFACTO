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
    public const AVAILABLE_ROLES = [
        'dg'                   => 'Directeur Général',
        'assistant_dg'         => 'Assistant DG',
        'chef_agence'          => 'Chef d\'Agence',
        'caissiere_principale' => 'Caissière Principale',
        'caissiere'            => 'Caissière',
        'comptable'            => 'Comptable',
        'superviseur_general'  => 'Superviseur Général',
        'superviseur_regional' => 'Superviseur Régional',
        'agent_enregistrement' => 'Agent d\'Enregistrement',
        'agent_groupage'       => 'Agent Groupage',
    ];

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
        $role = trim((string) ($query['role'] ?? ''));
        if (!array_key_exists($role, self::AVAILABLE_ROLES)) {
            $role = '';
        }

        $filters = [
            'q' => trim((string) ($query['q'] ?? '')),
            'status' => $status,
            'profile' => $profile,
            'role' => $role,
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
            'auditLogs' => $this->getUserAuditLogs($id),
        ];
    }

    public function userCreationData(): array
    {
        return [
            'employees' => $this->personnel->availableForUserAccount(),
            'permissions' => $this->permissions->forUser(0),
            'allUsers' => $this->users->allSimple(),
        ];
    }

    public function createUser(array $input): int
    {
        $employeeId = (int) ($input['rh_employee_id'] ?? 0);
        if ($employeeId > 0) {
            $employee = $this->personnel->findForUserAccount($employeeId);
            if (!$employee) {
                throw new RuntimeException('Le profil RH sélectionné est invalide ou possède déjà un compte.');
            }
            if (trim((string) ($employee['email'] ?? '')) === '') {
                throw new RuntimeException('Le profil RH doit disposer d’une adresse email avant la création du compte.');
            }
            $fullName = (string) $employee['full_name'];
            $email = strtolower(trim((string) $employee['email']));
            $phone = $employee['phone'] ?: null;
        } else {
            // Mode Secours : Création directe par l'administrateur sans profil RH préalable
            $fullName = trim((string) ($input['full_name'] ?? ''));
            $email = strtolower(trim((string) ($input['email'] ?? '')));
            $phone = trim((string) ($input['phone'] ?? '')) ?: null;
            $employeeId = null;

            if ($fullName === '') {
                throw new RuntimeException('Le nom complet est obligatoire pour la création directe d’un compte.');
            }
            if ($email === '') {
                throw new RuntimeException('L’adresse email est obligatoire pour la création directe d’un compte.');
            }
        }

        $data = $this->validateAccountSettings($input, true);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('L’adresse email saisie est invalide.');
        }
        if ($this->users->emailExists($email)) {
            throw new RuntimeException('L’adresse email est déjà utilisée par un autre compte.');
        }

        $agenceId = isset($input['agence_id']) && $input['agence_id'] !== '' ? (int) $input['agence_id'] : null;

        $this->pdo->beginTransaction();
        try {
            $id = $this->users->create(new User(
                id: null,
                fullName: $fullName,
                email: $email,
                phone: $phone,
                passwordHash: (string) $data['password_hash'],
                status: 'active',
                isAdmin: $data['is_admin'],
                rhEmployeeId: $employeeId,
                agenceId: $agenceId,
            ));

            if (!$data['is_admin']) {
                $this->replacePermissions($id, $input);
            }

            // Save functional roles
            $submittedRoles = is_array($input['roles'] ?? null) ? $input['roles'] : [];
            $allowedRoles = array_keys(self::AVAILABLE_ROLES);
            $roles = array_values(array_filter($submittedRoles, fn($r) => in_array($r, $allowedRoles, true)));
            $this->users->setRoles($id, $roles);

            $this->pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function resetPassword(int $userId): void
    {
        $this->requireUser($userId);
        $defaultHash = password_hash('lbp2026', PASSWORD_DEFAULT);
        $this->users->updatePassword($userId, $defaultHash);
    }

    public function bulkSetStatus(array $userIds, bool $active, int $actorId): void
    {
        foreach ($userIds as $userId) {
            $userId = (int) $userId;
            if ($userId > 0 && $userId !== $actorId) {
                $this->setUserActive($userId, $active, $actorId);
            }
        }
    }

    public function getUserAuditLogs(int $userId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT al.*, COALESCE(u.full_name, 'Système') AS user_name
                FROM audit_log al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE (al.table_name = 'users' AND al.record_id = :user_id)
                   OR (al.user_id = :user_id2)
                ORDER BY al.created_at DESC
                LIMIT 15
            ");
            $stmt->execute(['user_id' => $userId, 'user_id2' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    }

    public function updateUser(int $id, array $input, int $actorId): void
    {
        $user = $this->requireUser($id);
        $data = $this->validateAccountSettings($input, false);
        $data['status'] = $user->status;

        // Fix: read agence_id BEFORE any RH sync to preserve the submitted value
        $data['agence_id'] = isset($input['agence_id']) && $input['agence_id'] !== '' ? (int) $input['agence_id'] : null;

        $rhProfile = $user->rhEmployeeId ? $this->personnel->find((int) $user->rhEmployeeId) : null;
        if ($rhProfile) {
            $data['full_name'] = !empty($rhProfile['full_name']) ? (string) $rhProfile['full_name'] : $user->fullName;
            $rhEmail = strtolower(trim((string) ($rhProfile['email'] ?? '')));
            $data['email'] = filter_var($rhEmail, FILTER_VALIDATE_EMAIL) ? $rhEmail : $user->email;
            $data['phone'] = $rhProfile['phone'] ?: $user->phone;
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

        // Fix: save permissions on update (was only done on create before)
        if (!$data['is_admin'] && isset($input['permissions'])) {
            $this->replacePermissions($id, $input);
        }

        // Save functional roles
        $submittedRoles = is_array($input['roles'] ?? null) ? $input['roles'] : [];
        $allowedRoles = array_keys(self::AVAILABLE_ROLES);
        $roles = array_values(array_filter($submittedRoles, fn($r) => in_array($r, $allowedRoles, true)));
        $this->users->setRoles($id, $roles);

        Auth::reset();
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
        if ($passwordRequired && strlen($password) < 7) {
            throw new RuntimeException('Le mot de passe doit contenir au moins 7 caractères.');
        }
        if (!$passwordRequired && $password !== '' && strlen($password) < 7) {
            throw new RuntimeException('Le nouveau mot de passe doit contenir au moins 7 caractères.');
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
