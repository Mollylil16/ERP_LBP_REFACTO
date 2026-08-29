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
                    : '<div class="admin-legacy-notice">Compte créé directement en mode administration (sans dossier RH rattaché).</div>' ?>
            <?php else: ?>
                <?php
                $empOpts = array_merge(
                    [['value' => '0', 'label' => '⚡ Création directe d’urgence (Sans dossier RH préalable)']],
                    array_filter($page->employeeOptions, fn($o) => !empty($o['value']))
                );
                ?>
                <?= Form::select('rh_employee_id', $empOpts, '0', [
                    'label' => 'Dossier RH associé (Optionnel)',
                    'id' => 'js-rh-employee-select',
                    'hint' => 'Sélectionnez un profil RH existant ou choisissez "Création directe" pour saisir l’identité immédiatement.',
                ]) ?>
                
                <div id="js-direct-user-fields" style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:12px; margin-top:12px;">
                    <?= Form::input('full_name', ['label' => 'Nom & Prénoms *', 'placeholder' => 'ex: Kouassi Yao Emmanuel']) ?>
                    <?= Form::input('email', ['label' => 'Adresse Email *', 'type' => 'email', 'placeholder' => 'ex: yao.kouassi@labelleporte.ci']) ?>
                    <?= Form::input('phone', ['label' => 'Téléphone', 'placeholder' => 'ex: +225 0700000000']) ?>
                </div>

                <?= Admin::employeePreview() ?>
            <?php endif; ?>
            <?= Ui::section('Identité & Profil RH', (string) ob_get_clean()) ?>

            <?php ob_start(); ?>
            <?php
            $agencesDb = \App\Models\Database::getConnection()->query("SELECT id, name FROM company_sites WHERE is_active = 1 ORDER BY name ASC")->fetchAll() ?: [];
            $agenceOpts = [['value' => '', 'label' => '-- Sans agence spécifique (Toutes agences / Siège) --']];
            foreach ($agencesDb as $ag) {
                $agenceOpts[] = [
                    'value' => (string) $ag['id'],
                    'label' => 'Agence ' . $ag['name']
                ];
            }
            ?>
            <div class="admin-form-grid">
                <?= Form::input('password', [
                    'label' => $page->isEdit ? 'Nouveau mot de passe (optionnel)' : 'Mot de passe initial *',
                    'type' => 'password',
                    'minlength' => 7,
                    'required' => !$page->isEdit,
                    'autocomplete' => 'new-password',
                    'hint' => $page->isEdit
                        ? 'Laisser vide pour conserver le mot de passe actuel.'
                        : '7 caractères minimum (ex: lbp2026).',
                ]) ?>
                <?= Form::select('agence_id', $agenceOpts, (string) ($page->user?->agenceId ?? ''), ['label' => 'Agence de rattachement LBP']) ?>
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

            <?= Ui::section(
                'Rôles Fonctionnels & Accès applicatifs (Moyen N°1)',
                '<p style="color:#64748b; margin-top:-0.5rem; margin-bottom:1rem; font-size:0.9rem;">Cochez les rôles applicatifs pour accorder les fonctionnalités métiers. Casser les rôles va <strong>pré-cocher automatiquement</strong> les permissions CRUD associées ci-dessous.</p>'
                . Admin::rolesCheckboxGrid($page->availableRoles, $page->assignedRoles)
            ) ?>

            <?= Ui::section(
                'Permissions Habilitations (Ajustement CRUD par entité)',
                Admin::permissionToolbar(true) . Admin::permissionTable($page->permissions, $page->isEdit),
                '',
                ['data-initial-permissions' => true]
            ) ?>

            <div class="admin-form-actions">
                <?= Ui::button('Annuler', ['href' => 'admin/users', 'variant' => 'secondary']) ?>
                <?= Ui::button($page->submitLabel, [
                    'variant' => 'primary',
                    'type' => 'submit',
                ]) ?>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Gestion affichage Champs Directs vs Dossier RH
    var rhSelect = document.getElementById('js-rh-employee-select');
    var directFields = document.getElementById('js-direct-user-fields');
    if (rhSelect && directFields) {
        function toggleFields() {
            if (rhSelect.value === '0' || rhSelect.value === '') {
                directFields.style.display = 'grid';
            } else {
                directFields.style.display = 'none';
            }
        }
        rhSelect.addEventListener('change', toggleFields);
        toggleFields();
    }

    // 2. Pré-cochage intelligent Rôles -> CRUD Permissions
    var roleCheckboxes = document.querySelectorAll('input[name="roles[]"]');
    roleCheckboxes.forEach(function(cb) {
        cb.addEventListener('change', function() {
            if (!this.checked) return;
            var role = this.value;
            // Cocher automatiquement les permissions lire/créer/modifier/supprimer pertinentes
            var permRows = document.querySelectorAll('.admin-permission-table tr[data-permission-row]');
            permRows.forEach(function(row) {
                var checkboxes = row.querySelectorAll('input[type="checkbox"]');
                var text = row.textContent.toLowerCase();
                
                // Mappings intelligents selon le rôle
                if (role === 'caissiere_principale' || role === 'caissiere') {
                    if (text.includes('facture') || text.includes('caisse') || text.includes('paiement')) {
                        checkboxes.forEach(function(c) { c.checked = true; });
                    }
                } else if (role === 'chef_agence' || role === 'superviseur_general' || role === 'superviseur_regional') {
                    if (text.includes('colis') || text.includes('facture') || text.includes('caisse') || text.includes('client') || text.includes('logistique')) {
                        checkboxes.forEach(function(c) { c.checked = true; });
                    }
                } else if (role === 'comptable') {
                    if (text.includes('facture') || text.includes('caisse') || text.includes('comptab') || text.includes('dépense')) {
                        checkboxes.forEach(function(c) { c.checked = true; });
                    }
                } else if (role === 'agent_enregistrement') {
                    if (text.includes('colis') || text.includes('client')) {
                        checkboxes.forEach(function(c) { 
                            if (c.getAttribute('data-action') === 'view' || c.getAttribute('data-action') === 'create') {
                                c.checked = true; 
                            }
                        });
                    }
                }
            });
        });
    });
});
</script>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
