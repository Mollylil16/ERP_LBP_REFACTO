<?php
use App\Helpers\Csrf;
use App\Helpers\View;
use App\View\Components\Ui;
use App\View\Components\Form;

ob_start();
require __DIR__ . '/../_navigation.php';
?>

<?= Ui::pageHeader('Référentiel CRM', 'Nouveau Client', [
    'actions' => Ui::button('Retour à la liste', 'crm/clients', [
        'variant' => 'ghost'
    ])
]) ?>

<div class="finea-section-card" style="margin-top: 24px;">
    <form action="<?= View::url('crm/clients') ?>" method="post">
        <?= Csrf::input() ?>
        <div class="form-grid-2">
            <?= Form::select('type', 'Type de client *', [
                ['value' => 'client', 'label' => 'Client Standard (Expéditeur)'],
                ['value' => 'partner', 'label' => 'Partenaire (Destinataire régulier)'],
                ['value' => 'prospect', 'label' => 'Prospect'],
            ], 'client', ['required' => true]) ?>

            <?= Form::input('name', 'Nom complet ou Raison Sociale *', '', [
                'required' => true,
                'placeholder' => 'Ex: Société ABC SARL'
            ]) ?>

            <?= Form::input('contact_name', 'Nom du contact', '', [
                'placeholder' => 'Ex: M. Kouamé'
            ]) ?>

            <?= Form::input('phone', 'Téléphone', '', [
                'type' => 'tel',
                'placeholder' => 'Ex: +225 07 XX XX XX'
            ]) ?>

            <?= Form::input('email', 'Email', '', [
                'type' => 'email',
                'placeholder' => 'Ex: contact@societe.ci'
            ]) ?>

            <?= Form::input('country', 'Pays', '', [
                'placeholder' => 'Ex: Côte d\'Ivoire'
            ]) ?>

            <?= Form::input('city', 'Ville', '', [
                'placeholder' => 'Ex: Abidjan'
            ]) ?>
        </div>

        <div class="form-actions" style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
            <?= Ui::button('Annuler', 'crm/clients', ['variant' => 'ghost']) ?>
            <?= Ui::button('Créer le client', null, ['variant' => 'primary', 'type' => 'submit']) ?>
        </div>
    </form>
</div>

<?php 
$content = ob_get_clean(); 
require BASE_PATH . '/views/layouts/module.php'; 
?>
