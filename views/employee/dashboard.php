<?php

declare(strict_types=1);

use App\Helpers\View;
use App\View\Components\Dashboard;
use App\View\Components\EmployeeRequestList;
use App\View\Components\Ui;

/** @var array<string,mixed> $employee */
/** @var array<string,int|float|string> $stats */
/** @var array<int,array<string,mixed>> $attendance */
/** @var array<int,array<string,mixed>> $requests */
/** @var array<int,array<string,mixed>> $explanations */
/** @var array<int,array<string,mixed>> $documents */
$employee = array_replace(['full_name' => 'Collaborateur', 'function_name' => '', 'service_name' => ''], is_array($employee ?? null) ? $employee : []);
$stats = array_replace(['openRequests' => 0, 'leaveRemaining' => 0, 'presenceRate' => 0, 'pendingExplanations' => 0], is_array($stats ?? null) ? $stats : []);
$attendance = is_array($attendance ?? null) ? $attendance : [];
$requests = is_array($requests ?? null) ? $requests : [];
$explanations = is_array($explanations ?? null) ? $explanations : [];
$documents = is_array($documents ?? null) ? $documents : [];

$date = static fn(?string $value): string => $value ? date('d/m/Y', strtotime($value) ?: time()) : '—';
$firstName = trim((string) $employee['full_name']) !== '' ? explode(' ', trim((string) $employee['full_name']))[0] : 'Collaborateur';

ob_start();
?>
<div class="finea-shell employee-shell">
    <div class="finea-container">
        <?= Ui::pageHeader('Mon espace personnel',
            (($employee['function_name'] ?: 'Fonction non renseignée') . ' · ' . ($employee['service_name'] ?: 'Service non renseigné')),
            ['eyebrow' => 'Bonjour ' . $firstName, 'actions' => Ui::button('Soumettre une demande', ['href' => 'espace-employe/demandes/nouvelle', 'variant' => 'accent']), 'class' => 'employee-hero']
        ) ?>

        <?= Dashboard::kpis([
            ['label' => 'Demandes ouvertes', 'value' => $stats['openRequests'], 'meta' => 'En cours de validation', 'href' => 'espace-employe#demandes'],
            ['label' => 'Congés disponibles', 'value' => $stats['leaveRemaining'] . ' j', 'meta' => 'Solde estimé ' . date('Y'), 'href' => 'espace-employe/demandes/nouvelle?type=leave'],
            ['label' => 'Présence du mois', 'value' => $stats['presenceRate'] . '%', 'meta' => count($attendance) . ' journée(s) suivie(s)', 'href' => 'espace-employe#pointage'],
            ['label' => 'Explications attendues', 'value' => $stats['pendingExplanations'], 'meta' => 'Réponses à transmettre', 'href' => 'espace-employe#explications'],
        ]) ?>

        <?= Ui::section(
            'Mes demandes',
            '<div class="employee-heading"><div><p class="employee-eyebrow">Self-service RH</p></div>' . Ui::button('Nouvelle demande', ['href' => 'espace-employe/demandes/nouvelle', 'variant' => 'secondary']) . '</div>' . EmployeeRequestList::render($requests),
            '',
            ['id' => 'demandes']
        ) ?>

        <div class="employee-two-columns">
            <?= Ui::section(
                'Mon pointage du mois',
                '<div class="employee-heading"><div><p class="employee-eyebrow">Temps de travail</p></div></div>'
                    . Dashboard::attendanceList($attendance, $date)
                    . '<a class="employee-inline-action" href="' . View::url('espace-employe/demandes/nouvelle?type=attendance_correction') . '">Signaler une anomalie</a>',
                '',
                ['id' => 'pointage']
            ) ?>

            <?= Ui::section(
                'Demandes d’explications',
                '<div class="employee-heading"><div><p class="employee-eyebrow">Échanges RH</p></div></div>' . Dashboard::explanationList($explanations, $date),
                '',
                ['id' => 'explications']
            ) ?>
        </div>

        <?= Ui::section(
            'Mes documents',
            '<div class="employee-heading"><div><p class="employee-eyebrow">Dossier personnel</p></div></div>' . Dashboard::documentGrid($documents),
            '',
            ['id' => 'documents']
        ) ?>
    </div>
</div>
<?php $content = ob_get_clean(); require BASE_PATH . '/views/layouts/module.php'; ?>
