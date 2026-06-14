<?php

/** @var \App\Models\User $user */

use App\Helpers\Csrf;
use App\Helpers\View;

require BASE_PATH . '/views/admin/_navigation.php';
$currentModule = null;

ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <section class="finea-page-header admin-hero">
            <div>
                <p class="admin-eyebrow">Habilitations individuelles</p>
                <h1>Droits de <?= View::e($user->fullName) ?></h1>
                <p>Attribuer les opérations autorisées, entité par entité.</p>
            </div>
            <a class="finea-action-btn finea-action-btn--secondary" href="<?= View::url('admin/users/' . (int) $user->id) ?>">Retour au profil</a>
        </section>

        <?php if ($user->isAdmin): ?>
            <section class="finea-section-card admin-full-access">Ce compte est administrateur et possède automatiquement tous les droits.</section>
        <?php else: ?>
            <form method="post" action="<?= View::url('admin/users/' . (int) $user->id . '/permissions') ?>" class="admin-permissions-form">
                <?= Csrf::input() ?>
                <section class="finea-section-card">
                    <div class="admin-permission-toolbar">
                        <div>
                            <h2 class="finea-section-title">Permissions CRUD</h2>
                            <p>La lecture peut être cochée automatiquement lorsqu’une action d’écriture est accordée.</p>
                        </div>
                        <div class="finea-actions">
                            <button type="button" class="finea-action-btn finea-action-btn--secondary" data-permissions-clear>Tout retirer</button>
                            <button type="button" class="finea-action-btn finea-action-btn--secondary" data-permissions-read>Lecture seule</button>
                            <button type="button" class="finea-action-btn finea-action-btn--secondary" data-permissions-all>Tout autoriser</button>
                        </div>
                    </div>
                    <div class="finea-table-wrap">
                        <table class="finea-table admin-permission-table">
                            <thead>
                                <tr>
                                    <th>Module / entité</th>
                                    <th>Lire</th>
                                    <th>Créer</th>
                                    <th>Modifier</th>
                                    <th>Supprimer</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($permissions as $permission): ?>
                                    <?php if ($currentModule !== $permission['module']): $currentModule = $permission['module']; ?>
                                        <tr class="admin-module-row">
                                            <td colspan="5"><?= View::e($currentModule) ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <tr data-permission-row>
                                        <td><strong><?= View::e($permission['name']) ?></strong><small><?= View::e($permission['description']) ?></small></td>
                                        <?php foreach (['view', 'create', 'update', 'delete'] as $action): ?>
                                            <td><label class="admin-checkbox"><input type="checkbox" name="permissions[<?= (int) $permission['entity_id'] ?>][<?= $action ?>]" value="1" data-action="<?= $action ?>" <?= $permission['can_' . $action] ? 'checked' : '' ?>><span></span></label></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
                <div class="admin-form-actions">
                    <a class="finea-action-btn finea-action-btn--secondary" href="<?= View::url('admin/users/' . (int) $user->id) ?>">Annuler</a>
                    <button class="finea-action-btn finea-action-btn--primary">Enregistrer les permissions</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
