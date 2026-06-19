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
            'Informations personnelles, administratives et organisationnelles.',
            [
                'eyebrow' => 'Dossier RH',
                'class' => 'rh-hero',
                'actions' => [Ui::button('Retour a la liste', 'rh/personnel', 'secondary')],
            ]
        ) ?>

        <?= Rh::restrictedData($page->restrictedTables) ?>

        <form method="post" action="<?= View::url(ltrim($page->action, '/')) ?>" class="rh-employee-form" enctype="multipart/form-data" data-rh-employee-form>
            <?= Csrf::input() ?>

            <?php ob_start(); ?>
            <div class="rh-form-grid">
                <?= Form::input('employee_number', 'Matricule', $page->value('employee_number'), ['placeholder' => 'Genere automatiquement si vide']) ?>
                <?= Form::input('full_name', 'Nom complet', $page->value('full_name'), ['required' => true, 'fieldClass' => 'rh-field-wide']) ?>
                <?= Form::selectSearch('gender', 'Sexe', [
                    ['value' => '', 'label' => 'Non renseigne'],
                    ['value' => 'male', 'label' => 'Masculin'],
                    ['value' => 'female', 'label' => 'Feminin'],
                    ['value' => 'other', 'label' => 'Autre'],
                ], $page->value('gender')) ?>
                <?= Form::input('birth_date', 'Date de naissance', $page->value('birth_date'), ['type' => 'date']) ?>
                <?= Form::input('birth_place', 'Lieu de naissance', $page->value('birth_place')) ?>
                <?= Form::input('phone', 'Telephone', $page->value('phone')) ?>
                <?= Form::input('email', 'E-mail', $page->value('email'), ['type' => 'email']) ?>
                <?= Form::input('marital_status', 'Situation matrimoniale', $page->value('marital_status')) ?>
                <?= Form::input('address', 'Adresse', $page->value('address'), ['fieldClass' => 'rh-field-wide']) ?>
            </div>
            <?= Ui::section('Identite et contact', ob_get_clean()) ?>

            <?php ob_start(); ?>
            <div class="rh-form-grid">
                <?php if (!isset($page->restrictedTables[PermissionEntityRegistry::RH_SERVICES])): ?>
                    <?= Form::selectSearch('service_id', 'Service', $page->services, $page->value('service_id')) ?>
                <?php endif; ?>
                <?php if (!isset($page->restrictedTables[PermissionEntityRegistry::RH_FUNCTIONS])): ?>
                    <?= Form::selectSearch('function_id', 'Fonction', $page->functions, $page->value('function_id')) ?>
                <?php endif; ?>
                <?php if (!isset($page->restrictedTables[PermissionEntityRegistry::RH_STATUSES])): ?>
                    <?= Form::selectSearch('status_id', 'Statut', $page->statuses, $page->value('status_id')) ?>
                <?php endif; ?>
                <?= Form::selectSearch('site', 'Site', $page->sites, $page->value('site')) ?>
                <?= Form::input('hire_date', 'Date de recrutement', $page->value('hire_date'), ['type' => 'date']) ?>
                <?= Form::input('start_date', 'Prise de service', $page->value('start_date'), ['type' => 'date']) ?>
                <?= Form::input('contract_duration_months', 'Duree du contrat (mois)', $page->value('contract_duration_months'), ['type' => 'number', 'min' => 0]) ?>
                <?= Form::input('cni_number', 'Numero CNI', $page->value('cni_number')) ?>
                <?= Form::input('cnps_number', 'Numero CNPS', $page->value('cnps_number')) ?>
            </div>
            <?= Ui::section('Affectation et contrat', ob_get_clean()) ?>

            <?php ob_start(); ?>
            <div class="rh-form-grid">
                <?= Form::input('father_name', 'Nom du pere', $page->value('father_name')) ?>
                <?= Form::input('father_phone', 'Telephone du pere', $page->value('father_phone')) ?>
                <?= Form::input('mother_name', 'Nom de la mere', $page->value('mother_name')) ?>
                <?= Form::input('mother_phone', 'Telephone de la mere', $page->value('mother_phone')) ?>
                <?= Form::input('emergency_contact_name', "Contact d'urgence", $page->value('emergency_contact_name')) ?>
                <?= Form::input('emergency_contact_phone', 'Telephone urgence', $page->value('emergency_contact_phone')) ?>
                <?= Form::input('children_count', "Nombre d'enfants", $page->value('children_count'), ['type' => 'number', 'min' => 0, 'data-children-count' => true]) ?>
            </div>
            <?= Ui::section('Famille et urgence', ob_get_clean()) ?>

            <?php ob_start(); ?>
            <div class="rh-upload-grid">
                <?= Form::dropzone('photo', "Photo d'identité", [
                    'accept' => 'image/*',
                    'hint' => 'Glisser-déposer ou cliquer pour ajouter une photo.',
                    'preview' => !empty($page->employee['photo_path']) ? basename((string) $page->employee['photo_path']) : '',
                ]) ?>
                <?= Form::dropzone('identity_document', "Pièce d'identité", [
                    'accept' => 'image/*,.pdf',
                    'hint' => 'CNI, passeport ou attestation en PDF/image.',
                    'preview' => !empty($page->employee['identity_document_path']) ? basename((string) $page->employee['identity_document_path']) : '',
                ]) ?>
                <?= Form::dropzone('diploma', 'Diplôme / attestation', [
                    'accept' => 'image/*,.pdf',
                    'hint' => 'Document optionnel, complétable plus tard.',
                    'preview' => !empty($page->employee['diploma_path']) ? basename((string) $page->employee['diploma_path']) : '',
                ]) ?>
            </div>
            <div class="rh-child-documents" data-child-documents data-existing-children="<?= (int) $page->value('children_count', 0) ?>"></div>
            <?= Rh::card((string) ob_get_clean(), [
                'eyebrow' => 'Dossier numérique',
                'title' => 'Documents joints',
                'meta' => 'Le dossier reste modifiable et peut être complété à tout moment.',
            ]) ?>

            <?= Rh::formActions([
                Ui::button('Annuler', 'rh/personnel', 'secondary'),
                Ui::button($page->submitLabel, '', 'primary', 'submit'),
            ]) ?>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
