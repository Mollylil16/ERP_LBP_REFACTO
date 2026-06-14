<?php

use App\Helpers\Csrf;
use App\Helpers\View;
use App\Security\PermissionEntityRegistry;

require BASE_PATH . '/views/rh/_navigation.php';
$value = static fn(string $key): string => View::e((string) ($employee[$key] ?? ''));
$selectOptions = static function (array $rows, mixed $selected): void {
    foreach ($rows as $row) {
        $isSelected = (string) $selected === (string) $row['id'] ? 'selected' : '';
        echo '<option value="' . (int) $row['id'] . '" ' . $isSelected . '>' . View::e($row['name']) . '</option>';
    }
};

ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <section class="finea-page-header rh-hero">
            <div>
                <p class="rh-eyebrow">Dossier RH</p>
                <h1><?= View::e($pageTitle) ?></h1>
                <p>Informations personnelles, administratives et organisationnelles.</p>
            </div>
            <a class="finea-action-btn finea-action-btn--secondary" href="<?= View::url('rh/personnel') ?>">Retour a la liste</a>
        </section>

        <?php require BASE_PATH . '/views/rh/_restricted-data.php'; ?>
        <form method="post" action="<?= View::url(ltrim($formAction, '/')) ?>" class="rh-employee-form" enctype="multipart/form-data" data-rh-employee-form>
            <?= Csrf::input() ?>
            <section class="finea-section-card">
                <h2 class="finea-section-title">Identite et contact</h2>
                <div class="rh-form-grid">
                    <div class="finea-field"><label>Matricule</label><input class="finea-input" name="employee_number" value="<?= $value('employee_number') ?>" placeholder="Genere automatiquement si vide"></div>
                    <div class="finea-field rh-field-wide"><label>Nom complet *</label><input class="finea-input" required name="full_name" value="<?= $value('full_name') ?>"></div>
                    <div class="finea-field"><label>Sexe</label><select class="finea-select" name="gender" data-select-search><option value="">Non renseigne</option><option value="male" <?= ($employee['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Masculin</option><option value="female" <?= ($employee['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Feminin</option><option value="other" <?= ($employee['gender'] ?? '') === 'other' ? 'selected' : '' ?>>Autre</option></select></div>
                    <div class="finea-field"><label>Date de naissance</label><input class="finea-input" type="date" name="birth_date" value="<?= $value('birth_date') ?>"></div>
                    <div class="finea-field"><label>Lieu de naissance</label><input class="finea-input" name="birth_place" value="<?= $value('birth_place') ?>"></div>
                    <div class="finea-field"><label>Telephone</label><input class="finea-input" name="phone" value="<?= $value('phone') ?>"></div>
                    <div class="finea-field"><label>E-mail</label><input class="finea-input" type="email" name="email" value="<?= $value('email') ?>"></div>
                    <div class="finea-field"><label>Situation matrimoniale</label><input class="finea-input" name="marital_status" value="<?= $value('marital_status') ?>"></div>
                    <div class="finea-field rh-field-wide"><label>Adresse</label><input class="finea-input" name="address" value="<?= $value('address') ?>"></div>
                </div>
            </section>

            <section class="finea-section-card">
                <h2 class="finea-section-title">Affectation et contrat</h2>
                <div class="rh-form-grid">
                    <?php if (!isset($restrictedTables[PermissionEntityRegistry::RH_SERVICES])): ?><div class="finea-field"><label>Service</label><select class="finea-select" name="service_id" data-select-search><option value="">Non renseigne</option><?php $selectOptions($options['services'], $employee['service_id'] ?? null); ?></select></div><?php endif; ?>
                    <?php if (!isset($restrictedTables[PermissionEntityRegistry::RH_FUNCTIONS])): ?><div class="finea-field"><label>Fonction</label><select class="finea-select" name="function_id" data-select-search><option value="">Non renseignee</option><?php $selectOptions($options['functions'], $employee['function_id'] ?? null); ?></select></div><?php endif; ?>
                    <?php if (!isset($restrictedTables[PermissionEntityRegistry::RH_STATUSES])): ?><div class="finea-field"><label>Statut</label><select class="finea-select" name="status_id" data-select-search><option value="">Non renseigne</option><?php $selectOptions($options['statuses'], $employee['status_id'] ?? null); ?></select></div><?php endif; ?>
                    <div class="finea-field"><label>Site</label><input class="finea-input" name="site" value="<?= $value('site') ?>"></div>
                    <div class="finea-field"><label>Date de recrutement</label><input class="finea-input" type="date" name="hire_date" value="<?= $value('hire_date') ?>"></div>
                    <div class="finea-field"><label>Prise de service</label><input class="finea-input" type="date" name="start_date" value="<?= $value('start_date') ?>"></div>
                    <div class="finea-field"><label>Duree du contrat (mois)</label><input class="finea-input" min="0" type="number" name="contract_duration_months" value="<?= $value('contract_duration_months') ?>"></div>
                    <div class="finea-field"><label>Numero CNI</label><input class="finea-input" name="cni_number" value="<?= $value('cni_number') ?>"></div>
                    <div class="finea-field"><label>Numero CNPS</label><input class="finea-input" name="cnps_number" value="<?= $value('cnps_number') ?>"></div>
                </div>
            </section>

            <section class="finea-section-card">
                <h2 class="finea-section-title">Famille et urgence</h2>
                <div class="rh-form-grid">
                    <div class="finea-field"><label>Nom du pere</label><input class="finea-input" name="father_name" value="<?= $value('father_name') ?>"></div>
                    <div class="finea-field"><label>Telephone du pere</label><input class="finea-input" name="father_phone" value="<?= $value('father_phone') ?>"></div>
                    <div class="finea-field"><label>Nom de la mere</label><input class="finea-input" name="mother_name" value="<?= $value('mother_name') ?>"></div>
                    <div class="finea-field"><label>Telephone de la mere</label><input class="finea-input" name="mother_phone" value="<?= $value('mother_phone') ?>"></div>
                    <div class="finea-field"><label>Contact d'urgence</label><input class="finea-input" name="emergency_contact_name" value="<?= $value('emergency_contact_name') ?>"></div>
                    <div class="finea-field"><label>Telephone urgence</label><input class="finea-input" name="emergency_contact_phone" value="<?= $value('emergency_contact_phone') ?>"></div>
                    <div class="finea-field"><label>Nombre d'enfants</label><input class="finea-input" min="0" type="number" name="children_count" value="<?= $value('children_count') ?>" data-children-count></div>
                </div>
            </section>


            <section class="finea-section-card">
                <div class="rh-section-heading">
                    <div>
                        <p class="rh-eyebrow">Dossier numérique</p>
                        <h2 class="finea-section-title">Documents joints</h2>
                    </div>
                    <span>Le dossier reste modifiable et peut être complété à tout moment.</span>
                </div>
                <div class="rh-upload-grid">
                    <label class="rh-dropzone" data-dropzone>
                        <input type="file" name="photo" accept="image/*">
                        <strong>Photo d'identité</strong>
                        <span>Glisser-déposer ou cliquer pour ajouter une photo.</span>
                        <div class="rh-file-preview" data-file-preview><?= !empty($employee['photo_path']) ? View::e(basename((string)$employee['photo_path'])) : '' ?></div>
                    </label>
                    <label class="rh-dropzone" data-dropzone>
                        <input type="file" name="identity_document" accept="image/*,.pdf">
                        <strong>Pièce d'identité</strong>
                        <span>CNI, passeport ou attestation en PDF/image.</span>
                        <div class="rh-file-preview" data-file-preview><?= !empty($employee['identity_document_path']) ? View::e(basename((string)$employee['identity_document_path'])) : '' ?></div>
                    </label>
                    <label class="rh-dropzone" data-dropzone>
                        <input type="file" name="diploma" accept="image/*,.pdf">
                        <strong>Diplôme / attestation</strong>
                        <span>Document optionnel, complétable plus tard.</span>
                        <div class="rh-file-preview" data-file-preview><?= !empty($employee['diploma_path']) ? View::e(basename((string)$employee['diploma_path'])) : '' ?></div>
                    </label>
                </div>
                <div class="rh-child-documents" data-child-documents data-existing-children="<?= (int)($employee['children_count'] ?? 0) ?>"></div>
            </section>

            <div class="rh-form-actions">
                <a class="finea-action-btn finea-action-btn--secondary" href="<?= View::url('rh/personnel') ?>">Annuler</a>
                <button class="finea-action-btn finea-action-btn--primary"><?= View::e($submitLabel) ?></button>
            </div>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
