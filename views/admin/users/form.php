<?php

use App\Helpers\Csrf;
use App\Helpers\View;
use App\View\Components\Form;
use App\View\Components\Ui;

require BASE_PATH . '/views/admin/_navigation.php';
$isEdit = $user !== null;
$currentModule = null;
$employeeOptions = array_map(static fn(array $row): array => [
    'value' => (string) ($row['id'] ?? ''),
    'label' => (string) (($row['full_name'] ?? '') . ' · ' . (($row['employee_number'] ?? '') ?: 'Sans matricule')),
], $employees ?? []);
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
            <?= Ui::button('Retour à la liste', ['href' => 'admin/users', 'variant' => 'secondary']) ?>
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
                        <?= Form::selectSearch('rh_employee_id', array_merge(
                            [['value' => '', 'label' => 'Sélectionner un collaborateur']],
                            $employeeOptions
                        ), '', [
                            'label' => 'Personnel',
                            'required' => true,
                            'data-rh-employee-select' => '1',
                        ]) ?>
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
                    <?= Form::input('password', [
                        'label' => $isEdit ? 'Nouveau mot de passe' : 'Mot de passe initial',
                        'type' => 'password',
                        'minlength' => 8,
                        'required' => !$isEdit,
                        'autocomplete' => 'new-password',
                        'hint' => $isEdit ? 'Laisser vide pour conserver le mot de passe actuel.' : '8 caractères minimum.',
                    ]) ?>
                    <label class="admin-switch admin-switch-card">
                        <?= Form::checkbox('is_admin', ['label' => 'Profil administrateur', 'checked' => (bool) ($user?->isAdmin ?? false), 'data-admin-profile' => '1']) ?>
                        <span><strong>Profil administrateur</strong><small>Donne tous les droits et ignore la matrice individuelle.</small></span>
                    </label>
                </div>
            </section>

            <?php if (!$isEdit && $employees !== []): ?>
                <section class="finea-section-card" data-initial-permissions>
                    <div class="admin-permission-toolbar">
                        <div><h2 class="finea-section-title">Permissions initiales</h2><p>Configurez les droits dès la création du compte.</p></div>
                        <div class="finea-actions">
                            <?= Ui::button('Tout retirer', ['variant' => 'secondary', 'type' => 'button', 'data-permissions-clear' => true]) ?>
                            <?= Ui::button('Lecture seule', ['variant' => 'secondary', 'type' => 'button', 'data-permissions-read' => true]) ?>
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
                                        <td><?= Form::checkbox("permissions[" . (int) $permission['entity_id'] . "][" . $action . "]", ["label" => "", "value" => "1", "data-action" => $action, "fieldClass" => "admin-checkbox-field"]) ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

            <div class="admin-form-actions">
                <?= Ui::button('Annuler', ['href' => 'admin/users', 'variant' => 'secondary']) ?>
                <?= Ui::button((string) $submitLabel, ['variant' => 'primary', 'type' => 'submit', 'disabled' => !$isEdit && $employees === []]) ?>
            </div>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
