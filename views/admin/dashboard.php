<?php

declare(strict_types=1);

use App\View\Components\Dashboard;
use App\View\Components\Ui;

/** @var array{total?:int,active?:int,restricted?:int,administrators?:int} $statistics */
/** @var int $grantedPermissions */
/** @var array<int,object|array<string,mixed>> $entities */
$statistics = array_replace(['total' => 0, 'active' => 0, 'restricted' => 0, 'administrators' => 0], is_array($statistics ?? null) ? $statistics : []);
$grantedPermissions = (int) ($grantedPermissions ?? 0);
$entities = is_array($entities ?? null) ? $entities : [];

require BASE_PATH . '/views/admin/_navigation.php';
ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader('Piloter les comptes et les habilitations',
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
            <?= Ui::section('Entités sécurisées', Dashboard::entityList($entities), '', ['class' => 'admin-entities-section']) ?>
            <?= Dashboard::infoCard(
                'Bonnes pratiques',
                'Contrôle d’accès explicite',
                'Les administrateurs possèdent tous les droits. Les autres comptes reçoivent uniquement les permissions enregistrées dans leur profil.',
                [
                    ['label' => 'Gérer les utilisateurs', 'href' => 'admin/users'],
                    ['label' => 'Auditer la matrice', 'href' => 'admin/permissions'],
                ],
                ['class' => 'admin-security-card']
            ) ?>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
