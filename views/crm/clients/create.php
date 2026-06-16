<?php
use App\Helpers\Csrf;
use App\Helpers\View;
use App\View\Components\Ui;
use App\View\Components\Form;

ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Référentiel CRM',
            'Nouveau Client',
            'Création d\'un expéditeur ou destinataire.',
            Ui::button('Retour à la liste', ['href' => 'crm/clients', 'variant' => 'secondary'])
        ) ?>

        <section class="finea-section-card">
            <form action="<?= View::url('crm/clients') ?>" method="post">
                <?= Csrf::input() ?>
                <div class="rh-form-grid">
                    <?= Form::select('type', [
                        'client' => 'Client Standard (Expéditeur)',
                        'partner' => 'Partenaire (Destinataire régulier)',
                        'prospect' => 'Prospect',
                    ], [], ['label' => 'Type de client *', 'required' => true]) ?>

                    <?= Form::input('name', [
                        'label' => 'Nom complet ou Raison Sociale *',
                        'required' => true,
                        'placeholder' => 'Ex: Société ABC SARL'
                    ]) ?>

                    <?= Form::input('contact_name', [
                        'label' => 'Nom du contact',
                        'placeholder' => 'Ex: M. Kouamé'
                    ]) ?>

                    <?= Form::input('phone', [
                        'label' => 'Téléphone',
                        'type' => 'tel',
                        'placeholder' => 'Ex: +225 07 XX XX XX'
                    ]) ?>

                    <?= Form::input('email', [
                        'label' => 'Email',
                        'type' => 'email',
                        'placeholder' => 'Ex: contact@societe.ci'
                    ]) ?>

                    <?= Form::input('country', [
                        'label' => 'Pays',
                        'placeholder' => 'Ex: Côte d\'Ivoire'
                    ]) ?>

                    <?= Form::input('city', [
                        'label' => 'Ville',
                        'placeholder' => 'Ex: Abidjan'
                    ]) ?>
                </div>

                <div class="rh-form-actions" style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
                    <?= Ui::button('Annuler', ['href' => 'crm/clients', 'variant' => 'secondary']) ?>
                    <?= Ui::button('Créer le client', ['variant' => 'primary', 'type' => 'submit']) ?>
                </div>
            </form>
        </section>
    </div>
</div>
<?php $content = ob_get_clean(); require BASE_PATH . '/views/layouts/module.php'; ?>
