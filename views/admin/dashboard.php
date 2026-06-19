<?php

use App\Helpers\View;
use App\View\Components\Dashboard;
use App\View\Components\Ui;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());
require BASE_PATH . '/views/admin/_navigation.php';

/** @var array<string, int> $statistics */
/** @var int $grantedPermissions */
/** @var array<int, mixed> $entities */

ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Piloter les comptes et les habilitations',
            'Un espace central pour maîtriser les utilisateurs, leurs accès et les droits CRUD.',
            [
                'eyebrow' => 'Administration et sécurité',
                'class' => 'admin-hero',
                'actions' => Ui::button('Voir la matrice', ['href' => 'admin/permissions', 'variant' => 'secondary'])
                    . Ui::button('Nouvel utilisateur', ['href' => 'admin/users/nouveau', 'variant' => 'accent']),
            ]
        ) ?>

        <?= Dashboard::kpis([
            ['label' => 'Utilisateurs', 'value' => $statistics['total'], 'meta' => 'Comptes enregistrés', 'href' => 'admin/users'],
            ['label' => 'Comptes actifs', 'value' => $statistics['active'], 'meta' => 'Accès à la plateforme', 'href' => 'admin/users?status=active'],
            ['label' => 'Accès restreints', 'value' => $statistics['restricted'], 'meta' => 'Inactifs ou bloqués', 'href' => 'admin/users?status=inactive'],
            ['label' => 'Administrateurs', 'value' => $statistics['administrators'], 'meta' => 'Accès complets', 'href' => 'admin/users?profile=admin'],
            ['label' => 'Droits attribués', 'value' => $grantedPermissions, 'meta' => 'Couples utilisateur / entité', 'href' => 'admin/permissions'],
        ]) ?>

        <div class="admin-dashboard-grid">
            <?php ob_start(); ?>
            <div class="admin-entity-list">
                <?php foreach ($entities as $entity): ?>
                    <div>
                        <span class="admin-module-chip"><?= View::e($entity->module) ?></span>
                        <span><strong><?= View::e($entity->name) ?></strong><small><?= View::e($entity->description) ?></small></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <?= Ui::section('Entités sécurisées', (string) ob_get_clean(), '', ['class' => 'admin-entities-section']) ?>

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
