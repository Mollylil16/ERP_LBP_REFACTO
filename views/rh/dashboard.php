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
            'Effectifs, contrats, pointage, demandes et indicateurs RH dans un espace unique.',
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
                    Ui::button('Nouveau collaborateur', [
                        'href' => 'rh/personnel/nouveau',
                        'variant' => 'accent',
                    ]),

                ],
            ]
        ) ?>

        <?= Rh::restrictedData($page->restrictedTables) ?>

        <?= Dashboard::tabs($page->tabs, $page->mode) ?>

        <?= Dashboard::kpis([
            ['label' => 'Effectif global', 'value' => number_format($page->dashboard->stats['total'], 0, ',', ' '), 'meta' => 'Collaborateurs recensés', 'href' => 'rh/personnel?scope=all'],
            ['label' => 'En poste', 'value' => number_format($page->dashboard->stats['active'], 0, ',', ' '), 'meta' => 'Dossiers actifs', 'href' => 'rh/personnel?scope=active'],
            ['label' => 'Sorties', 'value' => number_format($page->dashboard->stats['inactive'], 0, ',', ' '), 'meta' => 'Personnel archivé', 'href' => 'rh/mouvements'],
            ['label' => 'Recrutements ' . date('Y'), 'value' => number_format($page->dashboard->stats['currentYearHires'], 0, ',', ' '), 'meta' => 'Depuis janvier', 'href' => 'rh/cycle-vie?section=recruitment'],
            ['label' => 'Services couverts', 'value' => number_format($page->dashboard->stats['services'], 0, ',', ' '), 'meta' => 'Services actifs représentés', 'href' => 'rh/parametrage?catalog=services'],
        ]) ?>

        <?php if ($page->mode === 'classic'): ?>
            <?= Dashboard::alerts($page->dashboard->alerts) ?>
            <?= Dashboard::distributionWithActions(
                $page->dashboard->services,
                (int) $page->dashboard->stats['total'],
                [
                    ['label' => 'Integrer un collaborateur', 'href' => 'rh/personnel/nouveau'],
                    ['label' => 'Consulter le personnel', 'href' => 'rh/personnel'],
                    ['label' => 'Gerer les mutations', 'href' => 'rh/mutations'],
                    ['label' => 'Suivre les entrees / sorties', 'href' => 'rh/mouvements'],
                ]
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
            <?= Dashboard::metrics([
                [
                    'eyebrow' => 'Assiduite du mois',
                    'value' => number_format($page->dashboard->analytics['presenceRate'], 1, ',', ' ') . '%',
                    'description' => 'Taux de presence sur '
                        . (int) $page->dashboard->analytics['attendanceRows'] . ' lignes de pointage',
                ],
                [
                    'eyebrow' => 'Retards',
                    'value' => (int) $page->dashboard->analytics['lateRows'],
                    'description' => 'Arrivees tardives detectees ce mois',
                ],
                [
                    'eyebrow' => 'Heures supplementaires',
                    'value' => number_format($page->dashboard->analytics['overtimeHours'], 1, ',', ' ') . ' h',
                    'description' => 'Volume cumule sur la periode',
                ],
                [
                    'eyebrow' => 'Demandes traitees',
                    'value' => (int) $page->dashboard->analytics['requestsProcessed'],
                    'description' => 'Validations, refus et annulations du mois',
                ],
            ]) ?>
            <?= Dashboard::rankings([
                ['title' => 'Services', 'rows' => $page->dashboard->services],
                ['title' => 'Fonctions', 'rows' => $page->dashboard->functions],
                ['title' => 'Statuts', 'rows' => $page->dashboard->statuses],
            ]) ?>
        <?php else: ?>
            <?= Dashboard::analyticIntro(
                'Lecture analytique',
                'Le socle de reporting RH est pret.',
                'Les filtres par periode, service, statut et collaborateur seront branches sur ce service '
                    . 'sans ajouter de SQL dans les vues ou les controleurs.',
                'Architecture active'
            ) ?>
            <?= Dashboard::reports([
                [
                    'title' => 'Rapport effectifs',
                    'description' => 'Effectif actif, sorties, recrutements et repartition organisationnelle.',
                    'action' => 'Export au prochain lot',
                    'button' => ['variant' => 'secondary', 'type' => 'button', 'disabled' => true],
                ],
                [
                    'title' => 'Rapport assiduite',
                    'description' => 'Presences, absences, retards et heures supplementaires par periode.',
                    'action' => 'Export au prochain lot',
                    'button' => ['variant' => 'secondary', 'type' => 'button', 'disabled' => true],
                ],
                [
                    'title' => 'Rapport demandes',
                    'description' => 'Demandes soumises, traitees, validees et refusees par categorie.',
                    'action' => 'Export au prochain lot',
                    'button' => ['variant' => 'secondary', 'type' => 'button', 'disabled' => true],
                ],
            ]) ?>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
