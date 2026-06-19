<?php

use App\View\Components\Admin;
use App\View\Components\Dashboard;
use App\View\Components\Ui;
use App\View\Pages\Admin\DashboardPage;

/** @var DashboardPage $page */

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
                'actions' => [
                    Ui::button('Voir la matrice', ['href' => 'admin/permissions', 'variant' => 'secondary']),
                    Ui::button('Nouvel utilisateur', ['href' => 'admin/users/nouveau', 'variant' => 'accent']),
                ],
            ]
        ) ?>

        <?= Dashboard::kpis([
            ['label' => 'Utilisateurs', 'value' => $page->statistics['total'] ?? 0, 'meta' => 'Comptes enregistrés', 'href' => 'admin/users'],
            ['label' => 'Comptes actifs', 'value' => $page->statistics['active'] ?? 0, 'meta' => 'Accès à la plateforme', 'href' => 'admin/users?status=active'],
            ['label' => 'Accès restreints', 'value' => $page->statistics['restricted'] ?? 0, 'meta' => 'Inactifs ou bloqués', 'href' => 'admin/users?status=inactive'],
            ['label' => 'Administrateurs', 'value' => $page->statistics['administrators'] ?? 0, 'meta' => 'Accès complets', 'href' => 'admin/users?profile=admin'],
            ['label' => 'Droits attribués', 'value' => $page->grantedPermissions, 'meta' => 'Couples utilisateur / entité', 'href' => 'admin/permissions'],
        ]) ?>

        <div class="admin-dashboard-grid">
            <?= Ui::section(
                'Entités sécurisées',
                Admin::entityList($page->entities),
                '',
                ['class' => 'admin-entities-section']
            ) ?>
            <?= Admin::securityCard() ?>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
