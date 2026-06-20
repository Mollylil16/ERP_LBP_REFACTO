<?php

declare(strict_types=1);

use App\Models\Database;
use App\Models\User;
use App\Repositories\Admin\UserRepository;
use App\Repositories\Employee\EmployeePortalRepository;
use App\Repositories\Rh\RhLifecycleRepository;
use App\Repositories\Rh\RhPersonnelRepository;
use App\Services\Employee\EmployeePortalService;
use App\Services\Rh\RhLifecycleService;
use App\Services\Rh\RhPersonnelService;

require dirname(__DIR__, 2) . '/bootstrap/app.php';

$pdo = Database::getConnection();
$admin = (new UserRepository($pdo))->findByIdentifier('admin');
if (!$admin) {
    throw new RuntimeException('SMOKE_EMPLOYEE_PORTAL: compte admin introuvable.');
}

$personnel = new RhPersonnelService(new RhPersonnelRepository($pdo));
$employeePortal = new EmployeePortalService(new EmployeePortalRepository($pdo));
$rhWorkflow = new RhLifecycleService(new RhLifecycleRepository($pdo));
$employeeId = null;
$requestId = 0;
$requestIds = [];

try {
    $suffix = bin2hex(random_bytes(4));
    $employeeId = $personnel->create([
        'employee_number' => 'PORTAL-' . strtoupper($suffix),
        'full_name' => 'Smoke Employee Portal',
        'email' => 'smoke.employee.' . $suffix . '@erp-lbp.local',
        'phone' => '+22500000001',
        'hire_date' => date('Y-m-d'),
        'start_date' => date('Y-m-d'),
    ], [], (int) $admin->id);

    $user = new User(
        900001,
        'Smoke Employee',
        'smoke.employee@local',
        null,
        'x',
        rhEmployeeId: $employeeId
    );

    $requestId = $employeePortal->createRequest($user, [
        'request_type' => 'leave',
        'start_date' => date('Y-m-d', strtotime('+10 days')),
        'end_date' => date('Y-m-d', strtotime('+12 days')),
        'leave_kind' => 'annual',
        'reason' => 'Smoke test du workflow de demande de congé.',
    ]);
    $requestIds[] = $requestId;

    foreach ([
        ['request_type' => 'absence', 'start_date' => date('Y-m-d'), 'absence_kind' => 'planned', 'reason' => 'Smoke absence planifiée.'],
        ['request_type' => 'lateness', 'incident_date' => date('Y-m-d'), 'arrival_time' => '09:10', 'reason' => 'Smoke signalement de retard.'],
        ['request_type' => 'attendance_correction', 'incident_date' => date('Y-m-d'), 'correction_kind' => 'missing_entry', 'check_in_time' => '08:00', 'reason' => 'Smoke correction du pointage.'],
        ['request_type' => 'salary_advance', 'amount' => 50000, 'repayment_months' => '2', 'reason' => 'Smoke avance sur salaire.'],
        ['request_type' => 'document', 'document_kind' => 'work_certificate', 'delivery_format' => 'digital', 'reason' => 'Smoke demande de document.'],
        ['request_type' => 'other', 'subject' => 'Question RH', 'reason' => 'Smoke demande RH libre suffisamment détaillée.'],
    ] as $payload) {
        $createdId = $employeePortal->createRequest($user, $payload);
        $requestIds[] = $createdId;
        if ($employeePortal->request($user, $createdId)['request_type'] !== $payload['request_type']) {
            throw new RuntimeException('Un type de demande spécialisé n’a pas été conservé.');
        }
    }

    $request = $employeePortal->request($user, $requestId);
    if ($request['status'] !== 'submitted' || $request['current_step'] !== 'manager') {
        throw new RuntimeException('La demande initiale ne démarre pas au niveau manager.');
    }

    $otherUser = new User(900002, 'Other Employee', 'other@local', null, 'x', rhEmployeeId: $employeeId + 999999);
    try {
        $employeePortal->request($otherUser, $requestId);
        throw new RuntimeException('Une demande a été exposée à un autre collaborateur.');
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'Demande introuvable.') throw $e;
    }

    $rhWorkflow->decideEmployeeRequest($requestId, 'approve', 1, 'Validation manager');
    $rhWorkflow->decideEmployeeRequest($requestId, 'approve', 1, 'Validation RH');
    $rhWorkflow->decideEmployeeRequest($requestId, 'approve', 1, 'Décision Direction');

    $completed = $employeePortal->request($user, $requestId);
    if ($completed['status'] !== 'approved' || $completed['current_step'] !== 'completed') {
        throw new RuntimeException('Le workflow complet n’aboutit pas au statut approuvé.');
    }
    if (count($completed['events']) !== 4) {
        throw new RuntimeException('La chronologie du workflow est incomplète.');
    }

    echo "SMOKE_EMPLOYEE_PORTAL_OK\n";
} finally {
    if ($requestIds !== []) {
        $placeholders = implode(',', array_fill(0, count($requestIds), '?'));
        $stmt = $pdo->prepare("DELETE FROM employee_legal_requests WHERE id IN ({$placeholders})");
        $stmt->execute($requestIds);
    }
    if ($employeeId !== null) {
        $pdo->prepare('DELETE FROM rh_employees WHERE id = :id')->execute(['id' => $employeeId]);
    }
}
