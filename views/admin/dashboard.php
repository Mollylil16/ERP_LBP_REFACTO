<?php

use App\Helpers\View;

require BASE_PATH . '/views/admin/_navigation.php';
ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <section class="finea-page-header admin-hero">
            <div>
                <p class="admin-eyebrow">Administration et sécurité</p>
                <h1>Piloter les comptes et les habilitations</h1>
                <p>Un espace central pour maîtriser les utilisateurs, leurs accès et les droits CRUD.</p>
            </div>
            <div class="finea-header-actions">
                <a class="finea-action-btn finea-action-btn--secondary" href="<?= View::url('admin/permissions') ?>">Voir la matrice</a>
                <a class="finea-action-btn finea-action-btn--accent" href="<?= View::url('admin/users/nouveau') ?>">Nouvel utilisateur</a>
            </div>
        </section>

        <section class="finea-grid finea-kpi-grid">
            <?php foreach ([
                ['Utilisateurs', $statistics['total'], 'Comptes enregistrés'],
                ['Comptes actifs', $statistics['active'], 'Accès à la plateforme'],
                ['Accès restreints', $statistics['restricted'], 'Inactifs ou bloqués'],
                ['Administrateurs', $statistics['administrators'], 'Accès complets'],
                ['Droits attribués', $grantedPermissions, 'Couples utilisateur / entité'],
            ] as [$label, $value, $meta]): ?>
                <article class="finea-kpi-card">
                    <span class="finea-kpi-label"><?= View::e($label) ?></span>
                    <strong class="finea-kpi-value"><?= (int) $value ?></strong>
                    <small class="finea-kpi-meta"><?= View::e($meta) ?></small>
                </article>
            <?php endforeach; ?>
        </section>

        <div class="admin-dashboard-grid">
            <section class="finea-section-card">
                <p class="admin-eyebrow">Référentiel</p>
                <h2 class="finea-section-title">Entités sécurisées</h2>
                <div class="admin-entity-list">
                    <?php foreach ($entities as $entity): ?>
                        <div>
                            <span class="admin-module-chip"><?= View::e($entity->module) ?></span>
                            <span><strong><?= View::e($entity->name) ?></strong><small><?= View::e($entity->description) ?></small></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <aside class="admin-security-card">
                <p class="admin-eyebrow">Bonnes pratiques</p>
                <h2>Contrôle d’accès explicite</h2>
                <p>Les administrateurs possèdent tous les droits. Les autres comptes reçoivent uniquement les permissions enregistrées dans leur profil.</p>
                <a href="<?= View::url('admin/users') ?>">Gérer les utilisateurs</a>
                <a href="<?= View::url('admin/permissions') ?>">Auditer la matrice</a>
            </aside>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
