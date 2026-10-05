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

        <?php
        /*
         * Deux compteurs suffisent a dire la taille de la maison. Les trois
         * autres ne faisaient agir personne : ils cedent la place, plus bas,
         * a ce qui attend une decision.
         */
        ?>
        <?= Dashboard::kpis([
            ['label' => 'Comptes actifs', 'value' => $page->statistics['active'] ?? 0, 'meta' => ($page->statistics['total'] ?? 0) . ' enregistrés en tout', 'href' => 'admin/users?status=active'],
            ['label' => 'Droits attribués', 'value' => $page->grantedPermissions, 'meta' => 'Couples utilisateur / entité', 'href' => 'admin/permissions'],
        ]) ?>

        <?= Admin::fileDAttente($page->attente) ?>

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
