<?php

use App\Helpers\View;
use App\View\Components\Form;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());
/** @var array $user */
/** @var array<int, array<string, mixed>> $modules */
/** @var array<int, array<string, mixed>> $content */

$moduleIcon = static function (string $name): string {
    $icons = [
        'finance' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V9.5L12 5l8 4.5V19"/><path d="M8 19v-6h8v6"/><path d="M9 10h6"/><path d="M12 13v6"/></svg>',
        'rh' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 19v-1.5a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4V19"/><circle cx="10" cy="7.5" r="3"/><path d="M20 19v-1.2a3.2 3.2 0 0 0-2.4-3.1"/><path d="M15.5 4.8a3 3 0 0 1 0 5.4"/></svg>',
        'colisage' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 8.5 12 4l8 4.5-8 4.5-8-4.5Z"/><path d="M4 8.5v7L12 20l8-4.5v-7"/><path d="M12 13v7"/><path d="m8.2 6.2 8 4.5"/></svg>',
        'logistique' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7h11v9H3z"/><path d="M14 10h4l3 3v3h-7"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/><path d="M6 11h5"/></svg>',
        'admin' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l7 3v5c0 4.5-2.8 8.2-7 10-4.2-1.8-7-5.5-7-10V6l7-3Z"/><path d="M9.5 12.2 11.2 14l3.6-4"/></svg>',
        'employee' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 19v-1.5a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4V19"/><circle cx="10" cy="7.5" r="3"/><path d="M20 19v-1.2a3.2 3.2 0 0 0-2.4-3.1"/><path d="M15.5 4.8a3 3 0 0 1 0 5.4"/></svg>',
        'crm' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19v-7a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4v7"/><circle cx="9" cy="8" r="3"/><path d="M15 11h3"/><path d="M15 15h3"/><path d="M7 19v-2a3 3 0 0 1 6 0v2"/></svg>',
        'tickets' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v10H8l-4 4V5Z"/><path d="M8 9h8"/><path d="M8 12h5"/></svg>',
        'website' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18"/><path d="M12 3a14 14 0 0 0 0 18"/></svg>',
        'customs' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h16"/><path d="M6 20V8l6-4 6 4v12"/><path d="M9 20v-6h6v6"/><path d="M9 10h6"/><path d="M7 8h10"/></svg>',
        'tracking' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-4.4 7-11a7 7 0 1 0-14 0c0 6.6 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5"/><path d="M3 21h18"/></svg>',
        'billing' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h10v18l-2-1-2 1-2-1-2 1-2-1V3Z"/><path d="M9 8h6"/><path d="M9 12h6"/><path d="M9 16h3"/></svg>',
        'warehouse' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 10 12 4l9 6v10H3V10Z"/><path d="M7 20v-7h10v7"/><path d="M9 15h6"/><path d="M9 18h6"/></svg>',
        'fleet' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7h11v9H3z"/><path d="M14 10h4l3 3v3h-7"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/><path d="M6 11h5"/><path d="M16 13h3"/></svg>',
        'portfolio' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v1"/><path d="M4 7h16v12H4z"/><path d="M4 12h16"/><path d="M10 12v2h4v-2"/></svg>',
        'agents' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="7" cy="8" r="3"/><circle cx="17" cy="8" r="3"/><path d="M2.5 20a5 5 0 0 1 9 0"/><path d="M12.5 20a5 5 0 0 1 9 0"/><path d="M10 13h4"/></svg>',
        'pilotage' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V5"/><path d="M4 19h16"/><path d="M8 16v-5"/><path d="M12 16V8"/><path d="M16 16v-3"/><path d="M8 8l4-3 4 5 4-6"/></svg>',
    ];

    return $icons[$name] ?? $icons['admin'];
};
ob_start();
?>

<section class="finea-portal-hero">
    <div class="finea-hero-main">
        <p class="finea-eyebrow">ERP transit • Portail modules</p>
        <h2>Bonjour <?= View::e($user['name'] ?? 'Administrateur'); ?>, choisissez votre espace de travail.</h2>
        <p>Une entrée unique, propre et évolutive pour gérer les opérations transit sans mélanger le portail avec les tableaux de bord métier.</p>
    </div>

    <div class="finea-hero-side">
        <span class="finea-version-chip">LBP ERP</span>
        <strong><?= count($modules); ?> modules</strong>
        <small>Transit, douane, tracking, CRM, tickets, site, RH, finance</small>
    </div>
</section>

<section class="finea-module-toolbar" aria-label="Recherche de modules">
    <div class="finea-search-box">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="m21 21-4.2-4.2" />
            <circle cx="11" cy="11" r="7" />
        </svg>
        <?= Form::input('module_search', [
            'label' => 'Recherche de module',
            'id' => 'moduleSearchInput',
            'type' => 'search',
            'placeholder' => 'Rechercher un module : douane, tracking, facturation, entrepôts, flotte, CRM, tickets...',
            'autocomplete' => 'off',
            'aria-label' => 'Rechercher un module',
        ]) ?>
    </div>
    <div class="finea-toolbar-meta">
        <span id="moduleSearchCount"><?= count($modules); ?> modules disponibles</span>
    </div>
</section>

<section class="finea-module-grid" id="moduleGrid" aria-label="Modules ERP disponibles">
    <?php foreach ($modules as $module): ?>
        <article
            class="finea-module-card <?= View::e($module['class']); ?>"
            data-module-card
            data-search="<?= View::e(strtolower($module['label'] . ' ' . $module['code'] . ' ' . $module['description'] . ' ' . $module['keywords'])); ?>">
            <a href="<?= View::url($module['url']); ?>" class="finea-module-link" aria-label="Ouvrir le module <?= View::e($module['label']); ?>">
                <span class="finea-module-glow" aria-hidden="true"></span>
                <span class="finea-module-topline">
                    <span class="finea-module-icon"><?= $moduleIcon($module['icon']); ?></span>
                    <span class="finea-module-code"><?= View::e($module['code']); ?></span>
                </span>
                <span class="finea-module-title"><?= View::e($module['label']); ?></span>
                <span class="finea-module-description"><?= View::e($module['description']); ?></span>
                <span class="finea-module-footer">
                    <span class="finea-module-status"><?= View::e($module['status']); ?></span>
                    <span class="finea-module-open">Ouvrir</span>
                </span>
            </a>
        </article>
    <?php endforeach; ?>
</section>

<p class="finea-empty-state" id="moduleEmptyState" hidden>Aucun module ne correspond à votre recherche.</p>

<section class="finea-portal-note">
    <div>
        <p class="finea-eyebrow">Bonne base dès le départ</p>
        <h3>Le portail reste l’accueil privé, chaque module gardera son propre dashboard.</h3>
    </div>
    <a href="<?= View::url('logout'); ?>" class="finea-secondary-action">Déconnexion</a>
</section>

<?php
$content = ob_get_clean();

require BASE_PATH . '/views/layouts/app.php';
