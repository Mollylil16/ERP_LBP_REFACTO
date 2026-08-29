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

        <?php
        $rolesList = '';
        if (!empty($page->user->roles)) {
            $avail = \App\Services\Admin\AdminService::AVAILABLE_ROLES;
            foreach ($page->user->roles as $r) {
                $lbl = $avail[$r] ?? $r;
                $rolesList .= '<span style="background:#0f172a; color:#fff; font-weight:700; padding:4px 12px; border-radius:16px; font-size:0.85rem; display:inline-block; margin-right:6px; margin-bottom:6px;">' . \App\Helpers\View::e($lbl) . '</span>';
            }
        } else {
            $rolesList = '<span style="color:#94a3b8; font-style:italic;">Aucun rôle fonctionnel spécifique attribué</span>';
        }
        ?>

        <div class="admin-profile-grid">
            <?= Ui::section('Informations générales', Admin::detailList($page->details) . '<div style="margin-top:1.25rem;"><strong>Rôles attribués :</strong><div style="margin-top:6px;">' . $rolesList . '</div></div>') ?>
            <?= Ui::section(
                'Permissions effectives',
                Admin::permissionSummary($page->user, $page->grantedPermissions),
                $page->user->isAdmin ? 'Toutes' : count($page->grantedPermissions) . ' entité(s)'
            ) ?>
        </div>

        <!-- Section Sécurité & Réinitialisation Mot de Passe -->
        <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:12px; padding:1.25rem 1.5rem; margin-top:1.5rem; display:flex; justify-content:space-between; align-items:center; box-shadow:0 2px 8px rgba(15,23,42,0.03);">
            <div>
                <strong style="color:#0f172a; font-size:1.05rem; display:block;">Réinitialisation d'Urgence du Mot de Passe</strong>
                <small style="color:#64748b;">Réinitialise instantanément le mot de passe de cet utilisateur à la valeur par défaut : <code style="background:#f1f5f9; padding:2px 6px; border-radius:4px; font-weight:bold; color:#2563eb;">lbp2026</code></small>
            </div>
            <form method="post" action="<?= View::url('admin/users/' . (int) $page->user->id . '/reset-password') ?>" onsubmit="return confirm('Êtes-vous sûr de vouloir réinitialiser le mot de passe de cet utilisateur à lbp2026 ?');">
                <?= \App\Helpers\Csrf::input() ?>
                <button type="submit" style="background:#0f172a; color:#ffffff; border:none; padding:0.6rem 1.2rem; border-radius:8px; font-weight:700; cursor:pointer; font-size:0.88rem; display:inline-flex; align-items:center; gap:6px;">
                    🔑 Réinitialiser (lbp2026)
                </button>
            </form>
        </div>

        <?php if ($page->canChangeAccess): ?>
            <div style="margin-top:1.5rem;">
                <?= Admin::accessState($page) ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
