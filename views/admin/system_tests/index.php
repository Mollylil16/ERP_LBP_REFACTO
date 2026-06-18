<?php

use App\Helpers\View;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());
/** @var array $summary */
/** @var array<int, array<string, mixed>> $modules */
/** @var array<int, array<string, mixed>> $latestRuns */
/** @var string $csrfToken */

$status = $summary['healthStatus'] ?? 'warning';
$score = (int) ($summary['healthScore'] ?? 0);

ob_start();
?>

<section class="health-hero health-status-<?= View::e($status); ?>">
    <div class="health-hero__content">
        <p class="finea-eyebrow">Module système • Santé & Tests</p>
        <h2>Centre de contrôle qualité ERP LBP</h2>
        <p>Testez toute l’application ou un module précis : PHPUnit, smoke tests, syntaxe PHP, routes, vues et accès BDD métier.</p>
        <div class="health-actions">
            <button type="button" class="health-btn health-btn-primary" data-health-run-all>Lancer le test complet</button>
            <a class="health-btn health-btn-secondary" href="<?= View::url('selection_portail'); ?>">Retour portail</a>
        </div>
    </div>

    <div class="health-gauge" aria-label="Score santé global">
        <div class="health-gauge__ring" style="--score: <?= $score; ?>">
            <span data-health-score><?= $score; ?>%</span>
        </div>
        <strong data-health-global-label><?= $score >= 90 ? 'Très stable' : ($score >= 60 ? 'À surveiller' : 'Critique'); ?></strong>
        <small>Dernier score enregistré</small>
    </div>
</section>

<section class="health-strip">
    <article>
        <span>PHP</span>
        <strong><?= View::e((string) $summary['phpVersion']); ?></strong>
    </article>
    <article>
        <span>Environnement</span>
        <strong><?= View::e((string) $summary['environment']); ?></strong>
    </article>
    <article>
        <span>Dernière exécution</span>
        <strong><?= View::e((string) ($summary['latest']['created_at'] ?? 'Jamais')); ?></strong>
    </article>
</section>

<section class="health-console" data-health-console hidden>
    <div class="health-console__header">
        <div>
            <p class="finea-eyebrow">Exécution en cours</p>
            <h3 data-health-console-title>Préparation du test...</h3>
        </div>
        <span class="health-loader" aria-hidden="true"></span>
    </div>
    <div class="health-progress"><span data-health-progress style="width: 8%"></span></div>
    <p data-health-console-message>Initialisation des contrôles.</p>
</section>

<section class="health-module-grid" aria-label="Modules testables">
    <?php foreach ($modules as $module): ?>
        <article class="health-module-card" style="--accent: <?= View::e((string) $module['accent']); ?>" data-health-module-card="<?= View::e((string) $module['slug']); ?>">
            <header>
                <span class="health-module-code"><?= View::e((string) $module['code']); ?></span>
                <span class="health-pill health-pill-neutral" data-health-card-status>Non testé</span>
            </header>
            <h3><?= View::e((string) $module['label']); ?></h3>
            <p>Contrôle routes, vues, tables, requêtes SQL et cohérence d’exécution du module.</p>
            <div class="health-mini-gauge"><span data-health-card-bar style="width: 0%"></span></div>
            <footer>
                <button type="button" class="health-link-btn" data-health-run-module="<?= View::e((string) $module['slug']); ?>">Tester ce module</button>
                <button type="button" class="health-link-btn muted" data-health-open-details="<?= View::e((string) $module['slug']); ?>">Détails</button>
            </footer>
        </article>
    <?php endforeach; ?>
</section>

<section class="health-results" data-health-results hidden>
    <div class="health-results__header">
        <div>
            <p class="finea-eyebrow">Rapport d’exécution</p>
            <h3 data-health-results-title>Résultat</h3>
        </div>
        <span class="health-pill" data-health-results-status>—</span>
    </div>
    <div class="health-check-list" data-health-check-list></div>
</section>

<section class="health-history">
    <div class="health-results__header">
        <div>
            <p class="finea-eyebrow">Historique</p>
            <h3>Dernières exécutions</h3>
        </div>
    </div>
    <div class="health-history-list">
        <?php if ($latestRuns === []): ?>
            <p class="health-muted">Aucune exécution enregistrée pour le moment.</p>
        <?php endif; ?>
        <?php foreach ($latestRuns as $run): ?>
            <article>
                <strong><?= View::e((string) $run['module']); ?></strong>
                <span><?= View::e((string) $run['scope']); ?> • <?= View::e((string) $run['created_at']); ?></span>
                <em class="health-pill health-pill-<?= View::e((string) $run['status']); ?>"><?= (int) $run['score']; ?>%</em>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<div class="health-modal" data-health-modal hidden>
    <div class="health-modal__overlay" data-health-close-modal></div>
    <article class="health-modal__dialog" role="dialog" aria-modal="true" aria-label="Détails du test">
        <header>
            <h3 data-health-modal-title>Détails</h3>
            <button type="button" data-health-close-modal>×</button>
        </header>
        <pre data-health-modal-body></pre>
    </article>
</div>

<script>
window.ERP_HEALTH_TESTS = {
    csrfToken: <?= json_encode($csrfToken, JSON_UNESCAPED_UNICODE); ?>,
    endpoints: {
        runAll: <?= json_encode(View::url('admin/system-tests/run'), JSON_UNESCAPED_SLASHES); ?>,
        runModule: <?= json_encode(View::url('admin/system-tests/run/'), JSON_UNESCAPED_SLASHES); ?>
    }
};
</script>

<?php
$content = ob_get_clean();
?>
<link href="<?= View::asset('css/system-tests.css'); ?>" rel="stylesheet">
<script src="<?= View::asset('js/system-tests.js'); ?>" defer></script>
<?php
require BASE_PATH . '/views/layouts/app.php';
