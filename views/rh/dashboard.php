<?php

use App\Helpers\View;
use App\Models\RhDashboard;
use App\View\Components\Ui;
use App\View\Components\Dashboard;
use App\View\Components\RecordList;

/** @var RhDashboard $dashboard */
/** @var string $mode */
$mode = in_array((string) ($mode ?? 'classic'), ['classic', 'statistique', 'analytique'], true) ? (string) ($mode ?? 'classic') : 'classic';

require BASE_PATH . '/views/rh/_navigation.php';

$formatDate = static function (?string $date): string {
    if (!$date) {
        return 'Non renseignee';
    }

    $timestamp = strtotime($date);
    return $timestamp ? date('d/m/Y', $timestamp) : 'Non renseignee';
};

$tabs = [
    'classic' => ['label' => 'Classique', 'description' => 'Effectifs, alertes et acces rapides'],
    'statistique' => ['label' => 'Statistique', 'description' => 'Indicateurs mensuels et repartitions'],
    'analytique' => ['label' => 'Analytique', 'description' => 'Lecture de pilotage et preparation des exports'],
];

ob_start();
?>
<div class="finea-shell rh-dashboard">
    <div class="finea-container">
        <section class="finea-page-header rh-hero">
            <div>
                <p class="rh-eyebrow">Ressources humaines</p>
                <h1>Pilotage centralise du personnel</h1>
                <p>Effectifs, contrats, pointage, demandes et indicateurs RH dans un espace unique.</p>
            </div>
            <div class="finea-header-actions">
                <span class="rh-pending-chip"><?= $dashboard->pendingTotal ?> action<?= $dashboard->pendingTotal > 1 ? 's' : '' ?> a verifier</span>
                <a href="<?= View::url('rh/personnel/nouveau') ?>" class="finea-action-btn finea-action-btn--accent">Nouveau collaborateur</a>
            </div>
        </section>

        <?php require BASE_PATH . '/views/rh/_restricted-data.php'; ?>

        <nav class="rh-dashboard-tabs" aria-label="Vues du tableau de bord">
            <?php foreach ($tabs as $key => $tab): ?>
                <a class="rh-dashboard-tab <?= $mode === $key ? 'is-active' : '' ?>" href="<?= View::url('rh/dashboard' . ($key === 'classic' ? '' : '?view=' . $key)) ?>">
                    <strong><?= View::e($tab['label']) ?></strong>
                    <small><?= View::e($tab['description']) ?></small>
                </a>
            <?php endforeach; ?>
        </nav>

        <?= Dashboard::kpis([
            ['label' => 'Effectif global', 'value' => number_format($dashboard->stats['total'], 0, ',', ' '), 'meta' => 'Collaborateurs recensés', 'href' => 'rh/personnel?scope=all'],
            ['label' => 'En poste', 'value' => number_format($dashboard->stats['active'], 0, ',', ' '), 'meta' => 'Dossiers actifs', 'href' => 'rh/personnel?scope=active'],
            ['label' => 'Sorties', 'value' => number_format($dashboard->stats['inactive'], 0, ',', ' '), 'meta' => 'Personnel archivé', 'href' => 'rh/mouvements'],
            ['label' => 'Recrutements ' . date('Y'), 'value' => number_format($dashboard->stats['currentYearHires'], 0, ',', ' '), 'meta' => 'Depuis janvier', 'href' => 'rh/cycle-vie?section=recruitment'],
            ['label' => 'Services couverts', 'value' => number_format($dashboard->stats['services'], 0, ',', ' '), 'meta' => 'Services actifs représentés', 'href' => 'rh/parametrage?catalog=services'],
        ]) ?>

        <?php if ($mode === 'classic'): ?>
            <?= Dashboard::alerts($dashboard->alerts) ?>

            <div class="rh-content-grid">
                <section class="finea-section-card">
                    <div class="rh-section-heading">
                        <div>
                            <p class="rh-eyebrow">Repartition</p>
                            <h2 class="finea-section-title">Services les plus representes</h2>
                        </div>
                        <span><?= $dashboard->stats['total'] ?> collaborateurs</span>
                    </div>
                    <?= Dashboard::bars($dashboard->services, (int) $dashboard->stats['total']) ?>
                </section>

                <aside class="rh-quick-card">
                    <p class="rh-eyebrow">Acces rapides</p>
                    <h2>Operations RH</h2>
                    <div class="rh-quick-list">
                        <a href="<?= View::url('rh/personnel/nouveau') ?>">Integrer un collaborateur <small>Ouvrir</small></a>
                        <a href="<?= View::url('rh/personnel') ?>">Consulter le personnel <small>Ouvrir</small></a>
                        <a href="<?= View::url('rh/mutations') ?>">Gerer les mutations <small>Ouvrir</small></a>
                        <a href="<?= View::url('rh/mouvements') ?>">Suivre les entrees / sorties <small>Ouvrir</small></a>
                    </div>
                </aside>
            </div>

            <section class="finea-section-card rh-recent-section">
                <div class="rh-section-heading">
                    <div>
                        <p class="rh-eyebrow">Recrutements recents</p>
                        <h2 class="finea-section-title">Dernieres integrations</h2>
                    </div>
                </div>
                <?php if ($dashboard->recentHires === []): ?>
                    <div class="finea-empty-state">Aucun collaborateur n'est encore enregistre dans le nouveau socle RH.</div>
                <?php else: ?>
                    <?php
                    $recentRows = array_map(static function (array $employee) use ($formatDate): array {
                        $employee['employee_number'] = $employee['employee_number'] ?: 'Sans matricule';
                        $employee['hire_date'] = $formatDate($employee['hire_date']);
                        $employee['status'] = $employee['status_name'];
                        return $employee;
                    }, $dashboard->recentHires);
                    ?>
                    <?= RecordList::render($recentRows, [
                        'full_name' => 'Collaborateur',
                        'employee_number' => 'Matricule',
                        'service_name' => 'Service',
                        'function_name' => 'Fonction',
                        'hire_date' => 'Recrutement',
                        'status' => 'Statut',
                    ], ['title_key' => 'full_name', 'subtitle_key' => 'employee_number', 'status_key' => 'status']) ?>
                <?php endif; ?>
            </section>
        <?php elseif ($mode === 'statistique'): ?>
            <?= Dashboard::metricPanels([
                ['label' => 'Assiduite du mois', 'value' => number_format($dashboard->analytics['presenceRate'], 1, ',', ' ') . '%', 'meta' => 'Taux de presence sur ' . (int) $dashboard->analytics['attendanceRows'] . ' lignes de pointage'],
                ['label' => 'Retards', 'value' => (int) $dashboard->analytics['lateRows'], 'meta' => 'Arrivees tardives detectees ce mois'],
                ['label' => 'Heures supplementaires', 'value' => number_format($dashboard->analytics['overtimeHours'], 1, ',', ' ') . ' h', 'meta' => 'Volume cumule sur la periode'],
                ['label' => 'Demandes traitees', 'value' => (int) $dashboard->analytics['requestsProcessed'], 'meta' => 'Validations, refus et annulations du mois'],
            ]) ?>
            <div class="rh-three-columns">
                <?php foreach ([['Services', $dashboard->services], ['Fonctions', $dashboard->functions], ['Statuts', $dashboard->statuses]] as [$title, $rows]): ?>
                    <section class="finea-section-card">
                        <h2 class="finea-section-title"><?= View::e($title) ?></h2>
                        <?php if ($rows === []): ?>
                            <div class="finea-empty-state">Aucune donnee disponible.</div>
                        <?php else: ?>
                            <?= Dashboard::ranking($rows) ?>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <section class="rh-analytic-hero finea-section-card">
                <div>
                    <p class="rh-eyebrow">Lecture analytique</p>
                    <h2>Le socle de reporting RH est pret.</h2>
                    <p>Les filtres par periode, service, statut et collaborateur seront branches sur ce service sans ajouter de SQL dans les vues ou les controleurs.</p>
                </div>
                <span class="finea-status-badge finea-status-badge--ok">Architecture active</span>
            </section>
            <?= Dashboard::reportCards([
                ['title' => 'Rapport effectifs', 'text' => 'Effectif actif, sorties, recrutements et repartition organisationnelle.', 'button' => 'Export au prochain lot'],
                ['title' => 'Rapport assiduite', 'text' => 'Presences, absences, retards et heures supplementaires par periode.', 'button' => 'Export au prochain lot'],
                ['title' => 'Rapport demandes', 'text' => 'Demandes soumises, traitees, validees et refusees par categorie.', 'button' => 'Export au prochain lot'],
            ]) ?>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
