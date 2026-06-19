<?php

use App\Helpers\Csrf;
use App\Helpers\View;
use App\View\Components\Admin;
use App\View\Components\Form;
use App\View\Components\Ui;
use App\View\Pages\Admin\UserFormPage;

/** @var UserFormPage $page */

ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            $page->title,
            'Le compte reprend obligatoirement l’identité et les coordonnées du dossier RH.',
            [
                'eyebrow' => 'Compte lié aux ressources humaines',
                'class' => 'admin-hero',
                'actions' => [Ui::button('Retour à la liste', [
                    'href' => 'admin/users',
                    'variant' => 'secondary',
                ])],
            ]
        ) ?>

        <form class="admin-user-form" method="post" action="<?= View::url(ltrim($page->action, '/')) ?>">
            <?= Csrf::input() ?>

            <?php ob_start(); ?>
            <?php if ($page->isEdit): ?>
                <?= $page->employee
                    ? Admin::employeeProfile($page->employee)
                    : '<div class="admin-legacy-notice">Compte système historique sans profil RH. Les nouveaux comptes exigent un dossier RH.</div>' ?>
            <?php elseif (!$page->canSubmit): ?>
                <?= Ui::emptyState(
                    'Aucun collaborateur disponible',
                    'Créez ou complétez d’abord son dossier dans le module RH.'
                ) ?>
            <?php else: ?>
                <?= Form::selectSearch('rh_employee_id', $page->employeeOptions, '', [
                    'label' => 'Personnel',
                    'required' => true,
                    'data-rh-employee-select' => '1',
                ]) ?>
                <?= Admin::employeePreview() ?>
            <?php endif; ?>
            <?= Ui::section('Profil RH associé', (string) ob_get_clean()) ?>

            <?php ob_start(); ?>
            <div class="admin-form-grid">
                <?= Form::input('password', [
                    'label' => $page->isEdit ? 'Nouveau mot de passe' : 'Mot de passe initial',
                    'type' => 'password',
                    'minlength' => 8,
                    'required' => !$page->isEdit,
                    'autocomplete' => 'new-password',
                    'hint' => $page->isEdit
                        ? 'Laisser vide pour conserver le mot de passe actuel.'
                        : '8 caractères minimum.',
                ]) ?>
                <div class="admin-switch admin-switch-card">
                    <?= Form::checkbox('is_admin', [
                        'label' => 'Profil administrateur',
                        'checked' => (bool) ($page->user?->isAdmin ?? false),
                        'data-admin-profile' => '1',
                    ]) ?>
                    <span><strong>Profil administrateur</strong><small>Donne tous les droits et ignore la matrice individuelle.</small></span>
                </div>
            </div>
            <?= Ui::section('Paramètres du compte', (string) ob_get_clean()) ?>

            <?php if (!$page->isEdit && $page->canSubmit): ?>
                <?= Ui::section(
                    'Permissions initiales',
                    Admin::permissionToolbar() . Admin::permissionTable($page->permissions),
                    '',
                    ['data-initial-permissions' => true]
                ) ?>
            <?php endif; ?>

            <div class="admin-form-actions">
                <?= Ui::button('Annuler', ['href' => 'admin/users', 'variant' => 'secondary']) ?>
                <?= Ui::button($page->submitLabel, [
                    'variant' => 'primary',
                    'type' => 'submit',
                    'disabled' => !$page->canSubmit,
                ]) ?>
            </div>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
