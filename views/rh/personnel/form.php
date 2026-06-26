<?php
use App\Helpers\Csrf;
use App\Helpers\View;
use App\Security\PermissionEntityRegistry;
use App\View\Components\Form;
use App\View\Components\Rh;
use App\View\Components\Ui;
use App\View\Pages\Rh\PersonnelFormPage;

/** @var PersonnelFormPage $page */

ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            $page->title,
            'Créez le dossier RH complet d\'un nouveau collaborateur : identité, affectation, contrat et pièces justificatives.',
            [
                'eyebrow' => 'Ressources humaines',
                'class' => 'rh-hero-green',
                'actions' => [
                    '<a href="' . View::url('rh/personnel') . '" class="rh-header-btn-outline"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" class="rh-btn-icon"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg> Liste du personnel</a>',
                    '<a href="' . View::url('rh/dashboard') . '" class="rh-header-btn-outline"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" class="rh-btn-icon"><rect x="3" y="3" width="7" height="9"></rect><rect x="14" y="3" width="7" height="5"></rect><rect x="14" y="12" width="7" height="9"></rect><rect x="3" y="16" width="7" height="5"></rect></svg> Tableau de bord RH</a>'
                ],
            ]
        ) ?>

        <?= Rh::restrictedData($page->restrictedTables) ?>

        <form method="post" action="<?= View::url(ltrim($page->action, '/')) ?>" class="rh-employee-form" enctype="multipart/form-data" data-rh-employee-form>
            <?= Csrf::input() ?>

            <!-- ETAPE 1 -->
            <div class="rh-form-step-card">
                <div class="rh-step-badge">ETAPE 1</div>
                <h3 class="rh-step-title">Identité du collaborateur</h3>
                <div class="rh-form-grid-3">
                    <?= Form::input('full_name', 'Nom complet', $page->value('full_name'), ['required' => true, 'placeholder' => 'Prénom(s) NOM', 'fieldClass' => 'rh-field-wide-2']) ?>
                    
                    <?php if ($page->employee['id'] ?? null): ?>
                        <?= Form::input('employee_number', 'Matricule', $page->value('employee_number'), ['placeholder' => 'Généré automatiquement si vide']) ?>
                    <?php else: ?>
                        <div class="finea-field">
                            <label>Matricule</label>
                            <div class="rh-matricule-autogen">
                                <strong>Auto-généré</strong>
                                <span>Selon le type de contrat et l'ordre d'intégration.</span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?= Form::selectSearch('gender', 'Sexe', [
                        ['value' => '', 'label' => 'Non renseigné'],
                        ['value' => 'male', 'label' => 'Masculin'],
                        ['value' => 'female', 'label' => 'Féminin'],
                        ['value' => 'other', 'label' => 'Autre'],
                    ], $page->value('gender')) ?>
                    
                    <?= Form::input('birth_date', 'Date de naissance', $page->value('birth_date'), ['type' => 'date']) ?>
                    
                    <?= Form::selectSearch('marital_status', 'Situation matrimoniale', [
                        ['value' => '', 'label' => 'Non renseignée'],
                        ['value' => 'celibataire', 'label' => 'Célibataire'],
                        ['value' => 'marie', 'label' => 'Marié(e)'],
                        ['value' => 'divorce', 'label' => 'Divorcé(e)'],
                        ['value' => 'veuf', 'label' => 'Veuf(ve)'],
                    ], $page->value('marital_status')) ?>
                    
                    <?= Form::input('cni_number', 'CNI', $page->value('cni_number'), ['placeholder' => 'Numéro CNI']) ?>
                    <?= Form::input('cnps_number', 'CNPS', $page->value('cnps_number'), ['placeholder' => 'Numéro CNPS']) ?>
                </div>
            </div>

            <!-- ETAPE 2 -->
            <div class="rh-form-step-card">
                <div class="rh-step-badge">ETAPE 2</div>
                <h3 class="rh-step-title">Affectation et contrat</h3>
                <div class="rh-form-grid-3">
                    <?php if (!isset($page->restrictedTables[PermissionEntityRegistry::RH_SERVICES])): ?>
                        <?= Form::selectSearch('service_id', 'Service', $page->services, $page->value('service_id')) ?>
                    <?php endif; ?>
                    <?php if (!isset($page->restrictedTables[PermissionEntityRegistry::RH_FUNCTIONS])): ?>
                        <?= Form::selectSearch('function_id', 'Fonction', $page->functions, $page->value('function_id')) ?>
                    <?php endif; ?>
                    <?php if (!isset($page->restrictedTables[PermissionEntityRegistry::RH_STATUSES])): ?>
                        <?= Form::selectSearch('status_id', 'Statut / type de contrat', $page->statuses, $page->value('status_id')) ?>
                    <?php endif; ?>
                    <?= Form::selectSearch('site', 'Site', $page->sites, $page->value('site')) ?>
                    <?= Form::input('hire_date', 'Date de recrutement', $page->value('hire_date'), ['type' => 'date']) ?>
                    <?= Form::input('start_date', 'Date de prise de service', $page->value('start_date'), ['type' => 'date']) ?>
                    <div class="finea-field">
                        <?= Form::input('contract_duration_months', 'Durée du contrat (mois)', $page->value('contract_duration_months'), ['type' => 'number', 'min' => 0, 'placeholder' => 'Ex : 12']) ?>
                        <small class="rh-field-desc">Laisser vide pour CDI / indéterminé.</small>
                    </div>
                </div>
            </div>

            <!-- ETAPE 3 -->
            <div class="rh-form-step-card">
                <div class="rh-step-badge">ETAPE 3</div>
                <h3 class="rh-step-title">Contacts et famille</h3>
                <div class="rh-form-grid-3">
                    <?= Form::input('phone', 'Téléphone', $page->value('phone'), ['placeholder' => '+225 XX XX XX XX']) ?>
                    <?= Form::input('email', 'E-mail professionnel', $page->value('email'), ['type' => 'email', 'placeholder' => 'prenom.nom@lbp.ci']) ?>
                    <?= Form::input('father_name', 'Nom du père', $page->value('father_name')) ?>
                    <?= Form::input('father_phone', 'Téléphone du père', $page->value('father_phone')) ?>
                    <?= Form::input('mother_name', 'Nom de la mère', $page->value('mother_name')) ?>
                    <?= Form::input('mother_phone', 'Téléphone de la mère', $page->value('mother_phone')) ?>
                    <?= Form::input('emergency_contact_name', "Contact d'urgence (nom)", $page->value('emergency_contact_name')) ?>
                    <?= Form::input('emergency_contact_phone', "Contact d'urgence (téléphone)", $page->value('emergency_contact_phone')) ?>
                    
                    <?= Form::selectSearch('has_children', 'A des enfants', [
                        ['value' => '0', 'label' => 'Non'],
                        ['value' => '1', 'label' => 'Oui'],
                    ], (int) $page->value('children_count') > 0 ? '1' : '0', [
                        'onchange' => "if(this.value === '0') { const inp = document.querySelector('[data-children-count]'); if(inp) { inp.value = '0'; inp.dispatchEvent(new Event('input')); } }"
                    ]) ?>
                    <?= Form::input('children_count', 'Nombre', $page->value('children_count'), ['type' => 'number', 'min' => 0, 'data-children-count' => true]) ?>
                </div>
                <div class="rh-child-documents" data-child-documents data-existing-children="<?= (int) $page->value('children_count', 0) ?>"></div>
            </div>

            <!-- ETAPE 4 -->
            <div class="rh-form-step-card">
                <div class="rh-step-badge">ETAPE 4 (OPTIONNELLE)</div>
                <h3 class="rh-step-title">Pièces justificatives</h3>
                <span class="rh-step-desc">Formats acceptés : PDF, DOC, DOCX, JPG, JPEG, PNG, WEBP. Toutes les pièces peuvent être ajoutées ultérieurement depuis le dossier RH.</span>
                
                <div class="rh-custom-uploads-grid">
                    <!-- Photo de profil -->
                    <label class="rh-custom-file-upload">
                        <input type="file" name="photo" accept="image/*" class="rh-file-input-hidden" onchange="this.nextElementSibling.nextElementSibling.lastElementChild.textContent = this.files[0] ? this.files[0].name : 'Aucun fichier choisi'">
                        <div class="rh-file-header">
                            <div class="rh-file-icon-box">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
                            </div>
                            <span class="rh-file-title">Photo de profil</span>
                        </div>
                        <div class="rh-file-select-bar">
                            <span class="rh-file-btn-trigger">Choisir un fichier</span>
                            <span class="rh-file-name-label">
                                <?= !empty($page->employee['photo_path']) ? basename((string) $page->employee['photo_path']) : 'Aucun fichier choisi' ?>
                            </span>
                        </div>
                    </label>

                    <!-- Extrait de naissance -->
                    <label class="rh-custom-file-upload">
                        <input type="file" name="birth_certificate" accept="image/*,.pdf" class="rh-file-input-hidden" onchange="this.nextElementSibling.nextElementSibling.lastElementChild.textContent = this.files[0] ? this.files[0].name : 'Aucun fichier choisi'">
                        <div class="rh-file-header">
                            <div class="rh-file-icon-box">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                            </div>
                            <span class="rh-file-title">Extrait de naissance</span>
                        </div>
                        <div class="rh-file-select-bar">
                            <span class="rh-file-btn-trigger">Choisir un fichier</span>
                            <span class="rh-file-name-label">
                                <?= !empty($page->employee['birth_certificate_path']) ? basename((string) $page->employee['birth_certificate_path']) : 'Aucun fichier choisi' ?>
                            </span>
                        </div>
                    </label>

                    <!-- Pièce d'identité / CNI -->
                    <label class="rh-custom-file-upload">
                        <input type="file" name="identity_document" accept="image/*,.pdf" class="rh-file-input-hidden" onchange="this.nextElementSibling.nextElementSibling.lastElementChild.textContent = this.files[0] ? this.files[0].name : 'Aucun fichier choisi'">
                        <div class="rh-file-header">
                            <div class="rh-file-icon-box">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="4" width="18" height="16" rx="2"></rect><circle cx="9" cy="10" r="2"></circle><line x1="15" y1="10" x2="19" y2="10"></line><line x1="15" y1="14" x2="19" y2="14"></line></svg>
                            </div>
                            <span class="rh-file-title">Pièce d'identité / CNI</span>
                        </div>
                        <div class="rh-file-select-bar">
                            <span class="rh-file-btn-trigger">Choisir un fichier</span>
                            <span class="rh-file-name-label">
                                <?= !empty($page->employee['identity_document_path']) ? basename((string) $page->employee['identity_document_path']) : 'Aucun fichier choisi' ?>
                            </span>
                        </div>
                    </label>

                    <!-- Dernier diplôme -->
                    <label class="rh-custom-file-upload">
                        <input type="file" name="diploma" accept="image/*,.pdf" class="rh-file-input-hidden" onchange="this.nextElementSibling.nextElementSibling.lastElementChild.textContent = this.files[0] ? this.files[0].name : 'Aucun fichier choisi'">
                        <div class="rh-file-header">
                            <div class="rh-file-icon-box">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c0 2 2 3 6 3s6-1 6-3v-5"></path></svg>
                            </div>
                            <span class="rh-file-title">Dernier diplôme</span>
                        </div>
                        <div class="rh-file-select-bar">
                            <span class="rh-file-btn-trigger">Choisir un fichier</span>
                            <span class="rh-file-name-label">
                                <?= !empty($page->employee['diploma_path']) ? basename((string) $page->employee['diploma_path']) : 'Aucun fichier choisi' ?>
                            </span>
                        </div>
                    </label>

                    <!-- Contrat d'embauche -->
                    <label class="rh-custom-file-upload">
                        <input type="file" name="employment_contract" accept="image/*,.pdf" class="rh-file-input-hidden" onchange="this.nextElementSibling.nextElementSibling.lastElementChild.textContent = this.files[0] ? this.files[0].name : 'Aucun fichier choisi'">
                        <div class="rh-file-header">
                            <div class="rh-file-icon-box">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                            </div>
                            <span class="rh-file-title">Contrat d'embauche</span>
                        </div>
                        <div class="rh-file-select-bar">
                            <span class="rh-file-btn-trigger">Choisir un fichier</span>
                            <span class="rh-file-name-label">
                                <?= !empty($page->employee['employment_contract_path']) ? basename((string) $page->employee['employment_contract_path']) : 'Aucun fichier choisi' ?>
                            </span>
                        </div>
                    </label>
                </div>
            </div>

            <div class="rh-form-actions-footer">
                <div class="rh-form-mandatory-note">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" class="rh-btn-icon"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                    Les champs marqués <span class="finea-required">*</span> sont obligatoires. Les autres peuvent être complétés depuis le dossier RH.
                </div>
                <div class="rh-form-actions-btns">
                    <a href="<?= View::url('rh/personnel') ?>" class="rh-form-btn-secondary">Annuler</a>
                    <button type="submit" class="rh-form-btn-submit">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" class="rh-btn-icon"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="19" y1="8" x2="19" y2="14"></line><line x1="16" y1="11" x2="22" y2="11"></line></svg>
                        <?= View::e($page->submitLabel) ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
?>
