<?php

use App\View\Components\Ui;
use App\View\Components\Dashboard;
use App\View\Components\Rh;
use App\View\Pages\Rh\DashboardPage;

/** @var DashboardPage $page */

ob_start();
?>
<div class="finea-shell rh-dashboard">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Pilotage centralise du personnel',
            'Le module RH consolide les effectifs, les affectations de service, les fonctions et les recrutements recents sans sortir du shell de navigation decaissement.',
            [
                'eyebrow' => 'Ressources humaines',
                'class' => 'rh-hero',
                'actions' => [
                    Ui::badge(
                        $page->dashboard->pendingTotal . ' action'
                            . ($page->dashboard->pendingTotal > 1 ? 's' : '') . ' a verifier',
                        'neutral',
                        ['class' => 'rh-pending-chip', 'unstyled' => true]
                    ),
                ],
            ]
        ) ?>

        <?= Dashboard::quickActions($page->quickActions) ?>

        <?= Rh::restrictedData($page->restrictedTables) ?>

        <?= Dashboard::tabs($page->tabs, $page->mode) ?>

        <?php if ($page->mode === 'classic'): ?>
            <?= Dashboard::alerts($page->dashboard->alerts) ?>

            <?= Dashboard::priorities($page->pendingRequests) ?>

            <?= Dashboard::kpis([
                ['label' => 'Effectif global', 'value' => number_format($page->dashboard->stats['total'], 0, ',', ' '), 'meta' => 'Collaborateurs recenses', 'href' => 'rh/personnel?scope=all'],
                ['label' => 'En poste', 'value' => number_format($page->dashboard->stats['active'], 0, ',', ' '), 'meta' => 'Sans date de sortie', 'href' => 'rh/personnel?scope=active', 'tone' => 'success'],
                ['label' => 'Sorties', 'value' => number_format($page->dashboard->stats['inactive'], 0, ',', ' '), 'meta' => 'Personnel archive', 'href' => 'rh/mouvements', 'tone' => 'warning'],
                ['label' => 'Recrutements annee', 'value' => number_format($page->dashboard->stats['currentYearHires'], 0, ',', ' '), 'meta' => 'Depuis janvier', 'href' => 'rh/cycle-vie?section=recruitment'],
                ['label' => 'Services couverts', 'value' => number_format($page->dashboard->stats['services'], 0, ',', ' '), 'meta' => 'Services actifs', 'href' => 'rh/parametrage?catalog=services'],
            ]) ?>

            <?php
            $legalRequestsCount = (int) ($page->dashboard->alerts[0]['count'] ?? 0);
            $leaveOpeningMissingCount = (int) ($page->dashboard->alerts[4]['count'] ?? 0);
            ?>
            <?= Dashboard::distributionWithActions(
                $page->dashboard->services,
                (int) $page->dashboard->stats['total'],
                [
                    ['label' => 'Integrer un collaborateur', 'href' => 'rh/personnel/nouveau', 'hint' => 'Creer un nouveau dossier RH complet avec pieces justificatives', 'tone' => 'success'],
                    ['label' => 'Pointage du jour', 'href' => 'rh/pointage', 'hint' => 'Saisir rapidement les presences, absences, missions et HS'],
                    ['label' => 'Pointage mensuel (1 personne)', 'href' => 'rh/pointage?vue=mensuel', 'hint' => 'Vue jour par jour pour verifier un salarie et corriger le pointage'],
                    ['label' => 'Demandes employe', 'href' => 'rh/validations', 'hint' => 'Valider ou refuser absences, prets, avances salaire et heures sup', 'count' => $legalRequestsCount, 'count_tone' => 'success'],
                    ['label' => 'Initialisation conges', 'href' => 'rh/parametrage', 'hint' => 'Renseigner les soldes de depart avant calcul ERP', 'count' => $leaveOpeningMissingCount, 'count_tone' => 'info'],
                    ['label' => 'Demandes d\'explications', 'href' => 'rh/explications', 'hint' => 'Creer, notifier, relancer et cloturer les demandes adressees au personnel'],
                ]
            ) ?>

            <?= Dashboard::classicRankings(
                $page->dashboard->functions,
                $page->dashboard->statuses
            ) ?>
            <?= Dashboard::recentRecords(
                $page->recentRows,
                [
                    'full_name' => 'Collaborateur',
                    'employee_number' => 'Matricule',
                    'service_name' => 'Service',
                    'function_name' => 'Fonction',
                    'hire_date' => 'Recrutement',
                    'status' => 'Statut',
                ],
                [
                    'eyebrow' => 'Recrutements recents',
                    'title' => 'Dernieres integrations',
                    'empty' => "Aucun collaborateur n'est encore enregistre dans le nouveau socle RH.",
                    'title_key' => 'full_name',
                    'subtitle_key' => 'employee_number',
                    'status_key' => 'status',
                ]
            ) ?>
        <?php elseif ($page->mode === 'statistique'): ?>
            <?= Dashboard::statIntro() ?>

            <?= Dashboard::statKPIs($page->dashboard->analytics) ?>

            <?= Dashboard::dailyAttendanceChart($page->dashboard->dailyAttendance) ?>

            <?= Dashboard::statThreeColumns() ?>

            <?= Dashboard::statTachesEtCharge() ?>

            <?= Dashboard::monthlyTrendChart($page->dashboard->monthlyTrend) ?>
        <?php else: ?>
            <?= Dashboard::alerts($page->dashboard->alerts) ?>
            <?= Dashboard::analyticDashboard(
                $page->dashboard->services,
                $page->dashboard->statuses,
                $page->dashboard->employeeList
            ) ?>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
