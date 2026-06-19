<?php

use App\View\Components\Admin;
use App\View\Components\Ui;
use App\View\Pages\Admin\PermissionMatrixPage;

/** @var PermissionMatrixPage $page */

ob_start();
?>
<div class="finea-shell">
    <div class="finea-container admin-matrix-container">
        <?= Ui::pageHeader(
            'Matrice des permissions',
            'Comparer rapidement les accès effectifs de tous les utilisateurs.',
            [
                'eyebrow' => 'Vue transverse',
                'class' => 'admin-hero',
                'actions' => [Ui::button('Gérer les utilisateurs', [
                    'href' => 'admin/users',
                    'variant' => 'accent',
                ])],
            ]
        ) ?>

        <?= Ui::section(
            'Droits par utilisateur et entité',
            Admin::permissionMatrix($page->entities, $page->users),
            '',
            ['class' => 'admin-matrix-card']
        ) ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
