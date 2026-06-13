<?php

use App\Helpers\View;
use App\Models\RhDashboard;

/** @var RhDashboard $dashboard */
/** @var string $mode */

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

        <nav class="rh-dashboard-tabs" aria-label="Vues du tableau de bord">
            <?php foreach ($tabs as $key => $tab): ?>
                <a class="rh-dashboard-tab <?= $mode === $key ? 'is-active' : '' ?>" href="<?= View::url('rh/dashboard' . ($key === 'classic' ? '' : '?view=' . $key)) ?>">
                    <strong><?= View::e($tab['label']) ?></strong>
                    <small><?= View::e($tab['description']) ?></small>
                </a>
            <?php endforeach; ?>
        </nav>

        <section class="finea-grid finea-kpi-grid">
            <?php
            $kpis = [
                ['Effectif global', $dashboard->stats['total'], 'Collaborateurs recenses'],
                ['En poste', $dashboard->stats['active'], 'Dossiers actifs'],
                ['Sorties', $dashboard->stats['inactive'], 'Personnel archive'],
                ['Recrutements ' . date('Y'), $dashboard->stats['currentYearHires'], 'Depuis janvier'],
                ['Services couverts', $dashboard->stats['services'], 'Services actifs representes'],
            ];
            ?>
            <?php foreach ($kpis as [$label, $value, $meta]): ?>
                <article class="finea-kpi-card">
                    <span class="finea-kpi-label"><?= View::e((string) $label) ?></span>
                    <strong class="finea-kpi-value"><?= number_format((int) $value, 0, ',', ' ') ?></strong>
                    <small class="finea-kpi-meta"><?= View::e((string) $meta) ?></small>
                </article>
            <?php endforeach; ?>
        </section>

        <?php if ($mode === 'classic'): ?>
            <section class="rh-alert-grid">
                <?php foreach ($dashboard->alerts as $alert): ?>
                    <article class="rh-alert-card tone-<?= View::e($alert['tone']) ?>">
                        <span><?= View::e($alert['label']) ?></span>
                        <strong><?= (int) $alert['count'] ?></strong>
                        <p><?= View::e($alert['description']) ?></p>
                    </article>
                <?php endforeach; ?>
            </section>

            <div class="rh-content-grid">
                <section class="finea-section-card">
                    <div class="rh-section-heading">
                        <div>
                            <p class="rh-eyebrow">Repartition</p>
                            <h2 class="finea-section-title">Services les plus representes</h2>
                        </div>
                        <span><?= $dashboard->stats['total'] ?> collaborateurs</span>
                    </div>
                    <?php if ($dashboard->services === []): ?>
                        <div class="finea-empty-state">Les repartitions apparaitront apres l'integration du personnel.</div>
                    <?php else: ?>
                        <div class="rh-bars">
                            <?php foreach ($dashboard->services as $service): ?>
                                <?php $width = min(100, ((int) $service['total'] / max(1, $dashboard->stats['total'])) * 100); ?>
                                <div class="rh-bar-row">
                                    <div><span><?= View::e($service['label']) ?></span><strong><?= (int) $service['total'] ?></strong></div>
                                    <div class="rh-bar"><span style="width: <?= $width ?>%"></span></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
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
                    <div class="finea-table-wrap">
                        <table class="finea-table">
                            <thead><tr><th>Matricule</th><th>Nom</th><th>Service</th><th>Fonction</th><th>Recrutement</th><th>Statut</th></tr></thead>
                            <tbody>
                            <?php foreach ($dashboard->recentHires as $employee): ?>
                                <tr>
                                    <td><?= View::e($employee['employee_number'] ?: 'Non renseigne') ?></td>
                                    <td><strong><?= View::e($employee['full_name']) ?></strong></td>
                                    <td><?= View::e($employee['service_name']) ?></td>
                                    <td><?= View::e($employee['function_name']) ?></td>
                                    <td><?= View::e($formatDate($employee['hire_date'])) ?></td>
                                    <td><span class="finea-status-badge finea-status-badge--info"><?= View::e($employee['status_name']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php elseif ($mode === 'statistique'): ?>
            <section class="rh-analytics-grid">
                <article class="finea-section-card rh-metric-panel">
                    <p class="rh-eyebrow">Assiduite du mois</p>
                    <strong><?= number_format($dashboard->analytics['presenceRate'], 1, ',', ' ') ?>%</strong>
                    <span>Taux de presence sur <?= (int) $dashboard->analytics['attendanceRows'] ?> lignes de pointage</span>
                </article>
                <article class="finea-section-card rh-metric-panel">
                    <p class="rh-eyebrow">Retards</p>
                    <strong><?= (int) $dashboard->analytics['lateRows'] ?></strong>
                    <span>Arrivees tardives detectees ce mois</span>
                </article>
                <article class="finea-section-card rh-metric-panel">
                    <p class="rh-eyebrow">Heures supplementaires</p>
                    <strong><?= number_format($dashboard->analytics['overtimeHours'], 1, ',', ' ') ?> h</strong>
                    <span>Volume cumule sur la periode</span>
                </article>
                <article class="finea-section-card rh-metric-panel">
                    <p class="rh-eyebrow">Demandes traitees</p>
                    <strong><?= (int) $dashboard->analytics['requestsProcessed'] ?></strong>
                    <span>Validations, refus et annulations du mois</span>
                </article>
            </section>
            <div class="rh-three-columns">
                <?php foreach ([['Services', $dashboard->services], ['Fonctions', $dashboard->functions], ['Statuts', $dashboard->statuses]] as [$title, $rows]): ?>
                    <section class="finea-section-card">
                        <h2 class="finea-section-title"><?= View::e($title) ?></h2>
                        <?php if ($rows === []): ?>
                            <div class="finea-empty-state">Aucune donnee disponible.</div>
                        <?php else: ?>
                            <div class="rh-ranking">
                                <?php foreach ($rows as $row): ?>
                                    <div><span><?= View::e($row['label']) ?></span><strong><?= (int) $row['total'] ?></strong></div>
                                <?php endforeach; ?>
                            </div>
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
            <section class="rh-report-grid">
                <article class="finea-section-card">
                    <h2 class="finea-section-title">Rapport effectifs</h2>
                    <p>Effectif actif, sorties, recrutements et repartition organisationnelle.</p>
                    <button type="button" class="finea-action-btn finea-action-btn--secondary" disabled>Export au prochain lot</button>
                </article>
                <article class="finea-section-card">
                    <h2 class="finea-section-title">Rapport assiduite</h2>
                    <p>Presences, absences, retards et heures supplementaires par periode.</p>
                    <button type="button" class="finea-action-btn finea-action-btn--secondary" disabled>Export au prochain lot</button>
                </article>
                <article class="finea-section-card">
                    <h2 class="finea-section-title">Rapport demandes</h2>
                    <p>Demandes soumises, traitees, validees et refusees par categorie.</p>
                    <button type="button" class="finea-action-btn finea-action-btn--secondary" disabled>Export au prochain lot</button>
                </article>
            </section>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
