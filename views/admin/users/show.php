<?php

use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\View;
use App\View\Components\Ui;

require BASE_PATH . '/views/admin/_navigation.php';
$granted = array_filter($permissions, static fn(array $permission): bool =>
    (bool) $permission['can_view'] || (bool) $permission['can_create'] || (bool) $permission['can_update'] || (bool) $permission['can_delete']
);
ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <section class="finea-page-header admin-hero">
            <div>
                <p class="admin-eyebrow">Profil utilisateur</p>
                <h1><?= View::e($user->fullName) ?></h1>
                <p><?= View::e($user->email) ?><?= $user->phone ? ' · ' . View::e($user->phone) : '' ?></p>
            </div>
            <div class="finea-header-actions">
                <a class="finea-action-btn finea-action-btn--secondary" href="<?= View::url('admin/users/' . (int) $user->id . '/modifier') ?>">Modifier</a>
                <a class="finea-action-btn finea-action-btn--accent" href="<?= View::url('admin/users/' . (int) $user->id . '/permissions') ?>">Gérer les droits</a>
            </div>
        </section>

        <div class="admin-profile-grid">
            <section class="finea-section-card">
                <h2 class="finea-section-title">Informations générales</h2>
                <dl class="admin-detail-list">
                    <div><dt>Identifiant</dt><dd>#<?= (int) $user->id ?></dd></div>
                    <div><dt>Profil RH</dt><dd><?= $employee ? View::e($employee['employee_number'] ?: $employee['full_name']) : 'Compte système' ?></dd></div>
                    <?php if ($employee): ?>
                        <div><dt>Service</dt><dd><?= View::e($employee['service_name']) ?></dd></div>
                        <div><dt>Fonction</dt><dd><?= View::e($employee['function_name']) ?></dd></div>
                    <?php endif; ?>
                    <div><dt>Profil</dt><dd><?= $user->isAdmin ? 'Administrateur' : 'Utilisateur' ?></dd></div>
                    <div><dt>Statut</dt><dd><?= View::e(ucfirst($user->status)) ?></dd></div>
                    <div><dt>Créé le</dt><dd><?= View::e($user->createdAt ? date('d/m/Y H:i', strtotime($user->createdAt)) : 'Non renseigné') ?></dd></div>
                    <div><dt>Dernière mise à jour</dt><dd><?= View::e($user->updatedAt ? date('d/m/Y H:i', strtotime($user->updatedAt)) : 'Aucune') ?></dd></div>
                </dl>
            </section>

            <section class="finea-section-card">
                <div class="admin-section-heading">
                    <h2 class="finea-section-title">Permissions effectives</h2>
                    <span><?= $user->isAdmin ? 'Toutes' : count($granted) . ' entité(s)' ?></span>
                </div>
                <?php if ($user->isAdmin): ?>
                    <div class="admin-full-access">Accès administrateur complet à toutes les entités.</div>
                <?php elseif ($granted === []): ?>
                    <div class="finea-empty-state">Aucune permission attribuée.</div>
                <?php else: ?>
                    <div class="admin-permission-summary">
                        <?php foreach ($granted as $permission): ?>
                            <div>
                                <strong><?= View::e($permission['module'] . ' · ' . $permission['name']) ?></strong>
                                <span>
                                    <?= $permission['can_view'] ? 'Lecture ' : '' ?>
                                    <?= $permission['can_create'] ? 'Création ' : '' ?>
                                    <?= $permission['can_update'] ? 'Modification ' : '' ?>
                                    <?= $permission['can_delete'] ? 'Suppression' : '' ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <?php if ((int) Auth::id() !== (int) $user->id): ?>
            <section class="finea-section-card admin-access-state">
                <div>
                    <h2 class="finea-section-title"><?= $user->status === 'active' ? 'Désactiver les accès' : 'Réactiver les accès' ?></h2>
                    <p><?= $user->status === 'active' ? 'Le compte sera conservé mais toute session et toute nouvelle connexion seront refusées.' : 'Le collaborateur pourra de nouveau se connecter avec ses permissions existantes.' ?></p>
                </div>
                <form method="post" action="<?= View::url('admin/users/' . (int) $user->id . ($user->status === 'active' ? '/desactiver' : '/activer')) ?>" data-confirm-access-state>
                    <?= Csrf::input() ?>
                    <?= Ui::button($user->status === 'active' ? 'Désactiver le compte' : 'Réactiver le compte', ['variant' => $user->status === 'active' ? 'danger' : 'primary', 'type' => 'submit']) ?>
                </form>
            </section>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
