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

            <?= Dashboard::distributionWithActions(
                $page->dashboard->services,
                (int) $page->dashboard->stats['total'],
                [
                    ['label' => 'Integrer un collaborateur', 'href' => 'rh/personnel/nouveau', 'hint' => 'Creer un nouveau dossier RH complet avec pieces justificatives'],
                    ['label' => 'Pointage du jour', 'href' => 'rh/pointage', 'hint' => 'Saisir rapidement les presences, absences, missions et HS'],
                    ['label' => 'Pointage mensuel (1 personne)', 'href' => 'rh/pointage?vue=mensuel', 'hint' => 'Vue jour par jour pour verifier un salarie et corriger le pointage'],
                    ['label' => 'Demandes employe', 'href' => 'rh/validations', 'hint' => 'Valider ou refuser absences, prets, avances salaire et heures sup'],
                    ['label' => 'Initialisation conges', 'href' => 'rh/parametrage', 'hint' => 'Renseigner les soldes de depart avant calcul ERP'],
                    ['label' => 'Demandes d\'explications', 'href' => 'rh/explications', 'hint' => 'Creer, notifier, relancer et cloturer les demandes adressees personnel'],
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
            <?= Dashboard::kpis([
                ['label' => 'Effectif global', 'value' => number_format($page->dashboard->stats['total'], 0, ',', ' '), 'meta' => 'Collaborateurs recenses', 'href' => 'rh/personnel?scope=all'],
                ['label' => 'En poste', 'value' => number_format($page->dashboard->stats['active'], 0, ',', ' '), 'meta' => 'Sans date de sortie', 'href' => 'rh/personnel?scope=active', 'tone' => 'success'],
                ['label' => 'Sorties', 'value' => number_format($page->dashboard->stats['inactive'], 0, ',', ' '), 'meta' => 'Personnel archive', 'href' => 'rh/mouvements', 'tone' => 'warning'],
                ['label' => 'Recrutements annee', 'value' => number_format($page->dashboard->stats['currentYearHires'], 0, ',', ' '), 'meta' => 'Depuis janvier', 'href' => 'rh/cycle-vie?section=recruitment'],
                ['label' => 'Services couverts', 'value' => number_format($page->dashboard->stats['services'], 0, ',', ' '), 'meta' => 'Services actifs', 'href' => 'rh/parametrage?catalog=services'],
            ]) ?>
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
            <?= Dashboard::kpis([
                ['label' => 'Effectif global', 'value' => number_format($page->dashboard->stats['total'], 0, ',', ' '), 'meta' => 'Collaborateurs recenses', 'href' => 'rh/personnel?scope=all'],
                ['label' => 'En poste', 'value' => number_format($page->dashboard->stats['active'], 0, ',', ' '), 'meta' => 'Sans date de sortie', 'href' => 'rh/personnel?scope=active', 'tone' => 'success'],
                ['label' => 'Sorties', 'value' => number_format($page->dashboard->stats['inactive'], 0, ',', ' '), 'meta' => 'Personnel archive', 'href' => 'rh/mouvements', 'tone' => 'warning'],
                ['label' => 'Recrutements annee', 'value' => number_format($page->dashboard->stats['currentYearHires'], 0, ',', ' '), 'meta' => 'Depuis janvier', 'href' => 'rh/cycle-vie?section=recruitment'],
                ['label' => 'Services couverts', 'value' => number_format($page->dashboard->stats['services'], 0, ',', ' '), 'meta' => 'Services actifs', 'href' => 'rh/parametrage?catalog=services'],
            ]) ?>
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
