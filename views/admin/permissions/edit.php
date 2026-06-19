<?php

use App\Helpers\Csrf;
use App\Helpers\View;
use App\View\Components\Admin;
use App\View\Components\Ui;
use App\View\Pages\Admin\PermissionEditPage;

/** @var PermissionEditPage $page */

ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Droits de ' . $page->user->fullName,
            'Attribuer les opérations autorisées, entité par entité.',
            [
                'eyebrow' => 'Habilitations individuelles',
                'class' => 'admin-hero',
                'actions' => [Ui::button('Retour au profil', [
                    'href' => 'admin/users/' . (int) $page->user->id,
                    'variant' => 'secondary',
                ])],
            ]
        ) ?>

        <?php if ($page->user->isAdmin): ?>
            <?= Ui::section(
                'Accès administrateur',
                '<div class="admin-full-access">Ce compte possède automatiquement tous les droits.</div>'
            ) ?>
        <?php else: ?>
            <form method="post" action="<?= View::url('admin/users/' . (int) $page->user->id . '/permissions') ?>" class="admin-permissions-form">
                <?= Csrf::input() ?>
                <?= Ui::section(
                    'Permissions CRUD',
                    Admin::permissionToolbar(true) . Admin::permissionTable($page->permissions, true)
                ) ?>
                <div class="admin-form-actions">
                    <?= Ui::button('Annuler', [
                        'href' => 'admin/users/' . (int) $page->user->id,
                        'variant' => 'secondary',
                    ]) ?>
                    <?= Ui::button('Enregistrer les permissions', [
                        'variant' => 'primary',
                        'type' => 'submit',
                    ]) ?>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
