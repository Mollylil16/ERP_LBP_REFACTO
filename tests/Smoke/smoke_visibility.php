<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap/app.php';

use App\Helpers\Auth;
use App\Helpers\Session;
use App\Models\Database;
use App\Repositories\PermissionRepository;
use App\Repositories\RhPersonnelRepository;
use App\Repositories\UserRepository;
use App\Services\AdminService;
use App\Services\DataVisibilityService;
use App\Services\RhPersonnelService;
use App\Security\OperationPolicy;
use App\Security\PermissionAction;
use App\Security\PermissionEntityRegistry;

$pdo = Database::getConnection();
$users = new UserRepository($pdo);
$permissions = new PermissionRepository($pdo);
$personnel = new RhPersonnelRepository($pdo);
$adminService = new AdminService($users, $permissions, $personnel, $pdo);
$rhService = new RhPersonnelService($personnel, new DataVisibilityService());
$admin = $users->findByIdentifier('admin');

if (!$admin) {
    throw new RuntimeException('Compte admin introuvable.');
}

$employeeId = null;
$userId = null;

try {
    $suffix = bin2hex(random_bytes(4));
    $employeeId = $personnel->create([
        'employee_number' => 'VIS-' . strtoupper($suffix),
        'full_name' => 'Test Visibilité',
        'email' => 'visibility-' . $suffix . '@erp-lbp.local',
        'phone' => null,
        'gender' => null,
        'birth_date' => null,
        'birth_place' => null,
        'marital_status' => null,
        'address' => null,
        'site' => null,
        'service_id' => null,
        'function_id' => null,
        'status_id' => null,
        'cni_number' => null,
        'cnps_number' => null,
        'contract_duration_months' => null,
        'hire_date' => date('Y-m-d'),
        'start_date' => date('Y-m-d'),
        'father_name' => null,
        'father_phone' => null,
        'mother_name' => null,
        'mother_phone' => null,
        'emergency_contact_name' => null,
        'emergency_contact_phone' => null,
        'children_count' => 0,
    ], (int) $admin->id);

    $entities = [];
    foreach ($permissions->entities() as $entity) {
        $entities[$entity->code] = (int) $entity->id;
    }

    $userId = $adminService->createUser([
        'rh_employee_id' => (string) $employeeId,
        'password' => 'Test1234!',
        'permissions' => [
            $entities['rh_employees'] => ['view' => '1'],
        ],
    ]);

    Session::set('auth_user_id', $userId);
    Auth::reset();
    if (!Auth::can(PermissionEntityRegistry::RH_EMPLOYEES, PermissionAction::VIEW)) {
        throw new RuntimeException('Le droit de lecture du personnel doit etre accorde.');
    }
    if (Auth::can('entite_inconnue', PermissionAction::VIEW)) {
        throw new RuntimeException('Une entite inconnue doit toujours etre refusee.');
    }
    if (Auth::can(PermissionEntityRegistry::RH_EMPLOYEES, 'action_inconnue')) {
        throw new RuntimeException('Une action inconnue doit toujours etre refusee.');
    }
    if (Auth::canOperation(OperationPolicy::RH_MUTATION_CREATE)) {
        throw new RuntimeException('Une operation composite doit exiger tous ses droits.');
    }
    $list = $rhService->list(['q' => 'Test Visibilité', 'scope' => 'all']);
    $row = $list['pagination']['items'][0] ?? null;
    if (!$row || $row['service_name'] !== DataVisibilityService::HIDDEN) {
        throw new RuntimeException('Le service interdit doit être masqué dans les résultats.');
    }

    $adminService->savePermissions($userId, [
        'permissions' => [
            $entities['rh_services'] => ['view' => '1'],
        ],
    ]);
    if (Auth::can(PermissionEntityRegistry::RH_EMPLOYEES, PermissionAction::VIEW)) {
        throw new RuntimeException('Le cache doit etre invalide apres remplacement des permissions.');
    }
    $list = $rhService->list(['q' => 'Test Visibilité', 'scope' => 'all']);
    if ($list['pagination']['items'] !== [] || $list['pagination']['total'] !== 0) {
        throw new RuntimeException('Les lignes du personnel doivent disparaître sans droit sur rh_employees.');
    }

    echo "SMOKE_VISIBILITY_OK\n";
} finally {
    Session::set('auth_user_id', (int) $admin->id);
    Auth::reset();
    if ($userId !== null) {
        $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $userId]);
    }
    if ($employeeId !== null) {
        $pdo->prepare('DELETE FROM rh_employees WHERE id = :id')->execute(['id' => $employeeId]);
    }
}
