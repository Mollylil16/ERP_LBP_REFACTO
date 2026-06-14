<?php
/** @var \App\Support\ViewBag $viewData */

use App\Helpers\Csrf;
use App\Helpers\View;
use App\Security\PermissionEntityRegistry;
use App\View\Components\Form;
use App\View\Components\Ui;

require BASE_PATH . '/views/rh/_navigation.php';

$employee = $viewData->array('employee');
$options = $viewData->array('options');
$restrictedTables = $viewData->array('restrictedTables');
$pageTitle = $viewData->string('pageTitle');
$formAction = $viewData->string('formAction');
$submitLabel = $viewData->string('submitLabel', 'Enregistrer');

$field = static fn(string $key, mixed $default = ''): mixed => $employee[$key] ?? $default;
$componentOptions = static fn(array $rows): array => array_map(static fn(array $row): array => [
    'value' => (string) ($row['id'] ?? ''),
    'label' => (string) ($row['name'] ?? ''),
], $rows);
$withEmpty = static fn(string $label, array $rows): array => array_merge([['value' => '', 'label' => $label]], $rows);

ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Dossier RH',
            $pageTitle,
            'Informations personnelles, administratives et organisationnelles.',
            Ui::button('Retour a la liste', 'rh/personnel', 'secondary'),
            ['class' => 'rh-hero']
        ) ?>

        <?php require BASE_PATH . '/views/rh/_restricted-data.php'; ?>

        <form method="post" action="<?= View::url(ltrim($formAction, '/')) ?>" class="rh-employee-form" enctype="multipart/form-data" data-rh-employee-form>
            <?= Csrf::input() ?>

            <?php ob_start(); ?>
            <div class="rh-form-grid">
                <?= Form::input('employee_number', 'Matricule', $field('employee_number'), ['placeholder' => 'Genere automatiquement si vide']) ?>
                <?= Form::input('full_name', 'Nom complet', $field('full_name'), ['required' => true, 'fieldClass' => 'rh-field-wide']) ?>
                <?= Form::selectSearch('gender', 'Sexe', [
                    ['value' => '', 'label' => 'Non renseigne'],
                    ['value' => 'male', 'label' => 'Masculin'],
                    ['value' => 'female', 'label' => 'Feminin'],
                    ['value' => 'other', 'label' => 'Autre'],
                ], $field('gender')) ?>
                <?= Form::input('birth_date', 'Date de naissance', $field('birth_date'), ['type' => 'date']) ?>
                <?= Form::input('birth_place', 'Lieu de naissance', $field('birth_place')) ?>
                <?= Form::input('phone', 'Telephone', $field('phone')) ?>
                <?= Form::input('email', 'E-mail', $field('email'), ['type' => 'email']) ?>
                <?= Form::input('marital_status', 'Situation matrimoniale', $field('marital_status')) ?>
                <?= Form::input('address', 'Adresse', $field('address'), ['fieldClass' => 'rh-field-wide']) ?>
            </div>
            <?= Ui::section('Identite et contact', ob_get_clean()) ?>

            <?php ob_start(); ?>
            <div class="rh-form-grid">
                <?php if (!isset($restrictedTables[PermissionEntityRegistry::RH_SERVICES])): ?>
                    <?= Form::selectSearch('service_id', 'Service', $withEmpty('Non renseigne', $componentOptions($options['services'] ?? [])), $field('service_id')) ?>
                <?php endif; ?>
                <?php if (!isset($restrictedTables[PermissionEntityRegistry::RH_FUNCTIONS])): ?>
                    <?= Form::selectSearch('function_id', 'Fonction', $withEmpty('Non renseignee', $componentOptions($options['functions'] ?? [])), $field('function_id')) ?>
                <?php endif; ?>
                <?php if (!isset($restrictedTables[PermissionEntityRegistry::RH_STATUSES])): ?>
                    <?= Form::selectSearch('status_id', 'Statut', $withEmpty('Non renseigne', $componentOptions($options['statuses'] ?? [])), $field('status_id')) ?>
                <?php endif; ?>
                <?php 
                    $siteOptions = array_map(static fn($row) => ['value' => $row['name'], 'label' => $row['name']], $options['sites'] ?? []);
                ?>
                <?= Form::selectSearch('site', 'Site', $withEmpty('Non renseigne', $siteOptions), $field('site')) ?>
                <?= Form::input('hire_date', 'Date de recrutement', $field('hire_date'), ['type' => 'date']) ?>
                <?= Form::input('start_date', 'Prise de service', $field('start_date'), ['type' => 'date']) ?>
                <?= Form::input('contract_duration_months', 'Duree du contrat (mois)', $field('contract_duration_months'), ['type' => 'number', 'min' => 0]) ?>
                <?= Form::input('cni_number', 'Numero CNI', $field('cni_number')) ?>
                <?= Form::input('cnps_number', 'Numero CNPS', $field('cnps_number')) ?>
            </div>
            <?= Ui::section('Affectation et contrat', ob_get_clean()) ?>

            <?php ob_start(); ?>
            <div class="rh-form-grid">
                <?= Form::input('father_name', 'Nom du pere', $field('father_name')) ?>
                <?= Form::input('father_phone', 'Telephone du pere', $field('father_phone')) ?>
                <?= Form::input('mother_name', 'Nom de la mere', $field('mother_name')) ?>
                <?= Form::input('mother_phone', 'Telephone de la mere', $field('mother_phone')) ?>
                <?= Form::input('emergency_contact_name', "Contact d'urgence", $field('emergency_contact_name')) ?>
                <?= Form::input('emergency_contact_phone', 'Telephone urgence', $field('emergency_contact_phone')) ?>
                <?= Form::input('children_count', "Nombre d'enfants", $field('children_count'), ['type' => 'number', 'min' => 0, 'data-children-count' => true]) ?>
            </div>
            <?= Ui::section('Famille et urgence', ob_get_clean()) ?>

            <?php ob_start(); ?>
            <div class="rh-section-heading">
                <div>
                    <p class="rh-eyebrow">Dossier numérique</p>
                    <h2 class="finea-section-title">Documents joints</h2>
                </div>
                <span>Le dossier reste modifiable et peut être complété à tout moment.</span>
            </div>
            <div class="rh-upload-grid">
                <?= Form::dropzone('photo', "Photo d'identité", [
                    'accept' => 'image/*',
                    'hint' => 'Glisser-déposer ou cliquer pour ajouter une photo.',
                    'preview' => !empty($employee['photo_path']) ? basename((string) $employee['photo_path']) : '',
                ]) ?>
                <?= Form::dropzone('identity_document', "Pièce d'identité", [
                    'accept' => 'image/*,.pdf',
                    'hint' => 'CNI, passeport ou attestation en PDF/image.',
                    'preview' => !empty($employee['identity_document_path']) ? basename((string) $employee['identity_document_path']) : '',
                ]) ?>
                <?= Form::dropzone('diploma', 'Diplôme / attestation', [
                    'accept' => 'image/*,.pdf',
                    'hint' => 'Document optionnel, complétable plus tard.',
                    'preview' => !empty($employee['diploma_path']) ? basename((string) $employee['diploma_path']) : '',
                ]) ?>
            </div>
            <div class="rh-child-documents" data-child-documents data-existing-children="<?= (int) $field('children_count', 0) ?>"></div>
            <section class="finea-section-card"><?= ob_get_clean() ?></section>

            <div class="rh-form-actions">
                <?= Ui::button('Annuler', 'rh/personnel', 'secondary') ?>
                <?= Ui::button($submitLabel, '', 'primary', 'submit') ?>
            </div>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
