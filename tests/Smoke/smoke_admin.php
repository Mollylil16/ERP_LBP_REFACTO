<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap/app.php';

use App\Models\Database;
use App\Repositories\PermissionRepository;
use App\Repositories\RhPersonnelRepository;
use App\Repositories\UserRepository;
use App\Services\AdminService;
use App\Services\AuthService;
use App\Services\RhPersonnelService;
use App\Security\PermissionEntityRegistry;

$pdo = Database::getConnection();
$users = new UserRepository($pdo);
$permissions = new PermissionRepository($pdo);
$personnelRepository = new RhPersonnelRepository($pdo);
$service = new AdminService($users, $permissions, $personnelRepository, $pdo);
$rhService = new RhPersonnelService($personnelRepository);
$authService = new AuthService($users);
$admin = $users->findByIdentifier('admin');

if (!$admin || !$admin->isAdmin) {
    throw new RuntimeException('Le compte admin doit être actif et administrateur.');
}

$testUserId = null;
$testEmployeeId = null;

try {
    $suffix = bin2hex(random_bytes(4));

    $testEmployeeId = $rhService->create([
        'employee_number' => 'TEST-' . strtoupper($suffix),
        'full_name' => 'Test Admin Module',
        'email' => 'admin-smoke-' . $suffix . '@erp-lbp.local',
        'phone' => '+22500000000',
        'hire_date' => date('Y-m-d'),
        'start_date' => date('Y-m-d'),
    ], [], (int) $admin->id);

    $entities = $permissions->entities();
    $entityCodes = array_map(static fn($entity): string => $entity->code, $entities);
    $expectedEntities = PermissionEntityRegistry::codes();

    $missing = array_values(array_diff($expectedEntities, $entityCodes));

    if ($missing !== []) {
        throw new RuntimeException(
            "Des entités attendues par PermissionEntityRegistry sont absentes en base : " .
                json_encode($missing, JSON_UNESCAPED_UNICODE)
        );
    }

    echo "OK permissions: " . count($entityCodes) . " entités en base.\n";

    $entityId = (int) $entities[0]->id;
    $testUserId = $service->createUser([
        'rh_employee_id' => (string) $testEmployeeId,
        'password' => 'Test1234!',
        'permissions' => [
            $entityId => ['create' => '1'],
        ],
    ]);

    $userData = $service->user($testUserId);
    if ((int) $userData['user']->rhEmployeeId !== $testEmployeeId) {
        throw new RuntimeException('Le compte n’est pas lié au profil RH temporaire.');
    }

    $granted = array_filter(
        $userData['permissions'],
        static fn(array $permission): bool => (bool) $permission['can_view']
    );
    if (count($granted) !== 1) {
        throw new RuntimeException('La permission temporaire n’a pas été enregistrée.');
    }

    $service->setUserActive($testUserId, false, (int) $admin->id);
    if ($users->findById($testUserId)?->status !== 'inactive') {
        throw new RuntimeException('La désactivation du compte temporaire a échoué.');
    }
    if ($authService->login([
        'email' => $userData['user']->email,
        'password' => 'Test1234!',
    ])['success']) {
        throw new RuntimeException('Un compte désactivé ne doit pas pouvoir se connecter.');
    }

    $service->setUserActive($testUserId, true, (int) $admin->id);
    if ($users->findById($testUserId)?->status !== 'active') {
        throw new RuntimeException('La réactivation du compte temporaire a échoué.');
    }
    if (!$authService->login([
        'email' => $userData['user']->email,
        'password' => 'Test1234!',
    ])['success']) {
        throw new RuntimeException('Le compte réactivé doit pouvoir se connecter.');
    }

    echo "SMOKE_ADMIN_OK\n";
} finally {
    if ($testUserId !== null) {
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $testUserId]);
    }
    if ($testEmployeeId !== null) {
        $stmt = $pdo->prepare('DELETE FROM rh_employees WHERE id = :id');
        $stmt->execute(['id' => $testEmployeeId]);
    }
}
