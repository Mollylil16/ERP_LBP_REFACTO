<?php

use App\View\Components\Admin;
use App\View\Components\Ui;
use App\View\Pages\Admin\UserShowPage;

/** @var UserShowPage $page */

ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            $page->user->fullName,
            $page->user->email . ($page->user->phone ? ' · ' . $page->user->phone : ''),
            [
                'eyebrow' => 'Profil utilisateur',
                'class' => 'admin-hero',
                'actions' => [
                    Ui::button('Modifier', [
                        'href' => 'admin/users/' . (int) $page->user->id . '/modifier',
                        'variant' => 'secondary',
                    ]),
                    Ui::button('Gérer les droits', [
                        'href' => 'admin/users/' . (int) $page->user->id . '/permissions',
                        'variant' => 'accent',
                    ]),
                ],
            ]
        ) ?>

        <div class="admin-profile-grid">
            <?= Ui::section('Informations générales', Admin::detailList($page->details)) ?>
            <?= Ui::section(
                'Permissions effectives',
                Admin::permissionSummary($page->user, $page->grantedPermissions),
                $page->user->isAdmin ? 'Toutes' : count($page->grantedPermissions) . ' entité(s)'
            ) ?>
        </div>

        <?php if ($page->canChangeAccess): ?>
            <?= Admin::accessState($page) ?>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
