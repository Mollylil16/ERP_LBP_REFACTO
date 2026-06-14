<?php

use App\Helpers\Csrf;
use App\Helpers\View;

require BASE_PATH . '/views/admin/_navigation.php';
$isEdit = $user !== null;
$currentModule = null;
ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <section class="finea-page-header admin-hero">
            <div>
                <p class="admin-eyebrow">Compte lié aux ressources humaines</p>
                <h1><?= View::e($pageTitle) ?></h1>
                <p>Le compte reprend obligatoirement l’identité et les coordonnées du dossier RH.</p>
            </div>
            <a class="finea-action-btn finea-action-btn--secondary" href="<?= View::url('admin/users') ?>">Retour à la liste</a>
        </section>

        <form class="admin-user-form" method="post" action="<?= View::url(ltrim($formAction, '/')) ?>">
            <?= Csrf::input() ?>

            <section class="finea-section-card">
                <h2 class="finea-section-title">Profil RH associé</h2>
                <?php if ($isEdit): ?>
                    <?php if ($employee): ?>
                        <div class="admin-rh-profile">
                            <div><small>Collaborateur</small><strong><?= View::e($employee['full_name']) ?></strong></div>
                            <div><small>Matricule</small><strong><?= View::e($employee['employee_number'] ?: 'Non renseigné') ?></strong></div>
                            <div><small>Email</small><strong><?= View::e($employee['email'] ?: 'Non renseigné') ?></strong></div>
                            <div><small>Téléphone</small><strong><?= View::e($employee['phone'] ?: 'Non renseigné') ?></strong></div>
                            <div><small>Service</small><strong><?= View::e($employee['service_name']) ?></strong></div>
                            <div><small>Fonction</small><strong><?= View::e($employee['function_name']) ?></strong></div>
                        </div>
                    <?php else: ?>
                        <div class="admin-legacy-notice">Compte système historique sans profil RH. Les nouveaux comptes ne peuvent pas être créés dans cette situation.</div>
                    <?php endif; ?>
                <?php else: ?>
                    <?php if ($employees === []): ?>
                        <div class="finea-empty-state">Aucun collaborateur actif sans compte n’est disponible. Créez ou complétez d’abord son dossier dans le module RH.</div>
                    <?php else: ?>
                        <div class="finea-field">
                            <label for="rh_employee_id">Personnel *</label>
                            <select class="finea-select" id="rh_employee_id" name="rh_employee_id" required data-rh-employee-select>
                                <option value="">Sélectionner un collaborateur</option>
                                <?php foreach ($employees as $row): ?>
                                    <option
                                        value="<?= (int) $row['id'] ?>"
                                        data-name="<?= View::e($row['full_name']) ?>"
                                        data-number="<?= View::e($row['employee_number'] ?: 'Non renseigné') ?>"
                                        data-email="<?= View::e($row['email'] ?: 'Email RH manquant') ?>"
                                        data-phone="<?= View::e($row['phone'] ?: 'Non renseigné') ?>"
                                        data-service="<?= View::e($row['service_name']) ?>"
                                        data-function="<?= View::e($row['function_name']) ?>"
                                    ><?= View::e($row['full_name'] . ' · ' . ($row['employee_number'] ?: 'Sans matricule')) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="admin-rh-profile is-preview" data-rh-preview hidden>
                            <div><small>Collaborateur</small><strong data-rh-field="name"></strong></div>
                            <div><small>Matricule</small><strong data-rh-field="number"></strong></div>
                            <div><small>Email de connexion</small><strong data-rh-field="email"></strong></div>
                            <div><small>Téléphone</small><strong data-rh-field="phone"></strong></div>
                            <div><small>Service</small><strong data-rh-field="service"></strong></div>
                            <div><small>Fonction</small><strong data-rh-field="function"></strong></div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>

            <section class="finea-section-card">
                <h2 class="finea-section-title">Paramètres du compte</h2>
                <div class="admin-form-grid">
                    <div class="finea-field">
                        <label for="password"><?= $isEdit ? 'Nouveau mot de passe' : 'Mot de passe initial *' ?></label>
                        <input class="finea-input" id="password" type="password" name="password" minlength="8" <?= $isEdit ? '' : 'required' ?> autocomplete="new-password">
                        <small><?= $isEdit ? 'Laisser vide pour conserver le mot de passe actuel.' : '8 caractères minimum.' ?></small>
                    </div>
                    <label class="admin-switch admin-switch-card">
                        <input type="checkbox" name="is_admin" value="1" <?= ($user?->isAdmin ?? false) ? 'checked' : '' ?> data-admin-profile>
                        <span><strong>Profil administrateur</strong><small>Donne tous les droits et ignore la matrice individuelle.</small></span>
                    </label>
                </div>
            </section>

            <?php if (!$isEdit && $employees !== []): ?>
                <section class="finea-section-card" data-initial-permissions>
                    <div class="admin-permission-toolbar">
                        <div><h2 class="finea-section-title">Permissions initiales</h2><p>Configurez les droits dès la création du compte.</p></div>
                        <div class="finea-actions">
                            <button type="button" class="finea-action-btn finea-action-btn--secondary" data-permissions-clear>Tout retirer</button>
                            <button type="button" class="finea-action-btn finea-action-btn--secondary" data-permissions-read>Lecture seule</button>
                        </div>
                    </div>
                    <div class="finea-table-wrap">
                        <table class="finea-table admin-permission-table">
                            <thead><tr><th>Module / entité</th><th>Lire</th><th>Créer</th><th>Modifier</th><th>Supprimer</th></tr></thead>
                            <tbody>
                            <?php foreach ($permissions as $permission): ?>
                                <?php if ($currentModule !== $permission['module']): $currentModule = $permission['module']; ?>
                                    <tr class="admin-module-row"><td colspan="5"><?= View::e($currentModule) ?></td></tr>
                                <?php endif; ?>
                                <tr data-permission-row>
                                    <td><strong><?= View::e($permission['name']) ?></strong><small><?= View::e($permission['description']) ?></small></td>
                                    <?php foreach (['view', 'create', 'update', 'delete'] as $action): ?>
                                        <td><label class="admin-checkbox"><input type="checkbox" name="permissions[<?= (int) $permission['entity_id'] ?>][<?= $action ?>]" value="1" data-action="<?= $action ?>"><span></span></label></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

            <div class="admin-form-actions">
                <a class="finea-action-btn finea-action-btn--secondary" href="<?= View::url('admin/users') ?>">Annuler</a>
                <button class="finea-action-btn finea-action-btn--primary" <?= !$isEdit && $employees === [] ? 'disabled' : '' ?>><?= View::e($submitLabel) ?></button>
            </div>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
