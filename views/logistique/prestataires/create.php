<?php
use App\Helpers\Csrf;
use App\Helpers\View;
use App\View\Components\Ui;
use App\View\Components\Form;

ob_start();
require __DIR__ . '/../_navigation.php';
?>

<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Logistique Interne — Prestataires',
            'Nouveau Prestataire',
            'Ajoutez un nouveau partenaire logistique à votre carnet.',
            Ui::button('Retour', ['href' => 'logistique/prestataires', 'variant' => 'secondary']),
            ['class' => 'rh-hero']
        ) ?>

        <form action="<?= View::url('logistique/prestataires') ?>" method="POST" style="margin-top: 24px;">
            <?= Csrf::input() ?>
            
            <section class="finea-section-card">
                <h3 class="finea-section-title">Informations de la structure</h3>
                <div class="rh-form-grid" style="grid-template-columns: repeat(2, 1fr); gap: 15px;">
                    <div style="grid-column: span 2;">
                        <?= Form::input('name', 'Nom de l\'entreprise ou du partenaire', '', [
                            'required' => true,
                            'autofocus' => true
                        ]) ?>
                    </div>
                    <?= Form::select('type', 'Type de partenaire', [
                        ['value' => 'DOUANE', 'label' => 'Douane'],
                        ['value' => 'COMPAGNIE_AERIENNE', 'label' => 'Compagnie Aérienne'],
                        ['value' => 'COMPAGNIE_MARITIME', 'label' => 'Compagnie Maritime'],
                        ['value' => 'TRANSPORT_TERRESTRE', 'label' => 'Transport Terrestre'],
                        ['value' => 'AUTRE', 'label' => 'Autre']
                    ], 'DOUANE', ['required' => true]) ?>

                    <?= Form::input('country', 'Pays d\'opération', '', [
                        'placeholder' => 'Ex: Côte d\'Ivoire, France...'
                    ]) ?>
                </div>
            </section>

            <section class="finea-section-card" style="margin-top: 24px;">
                <h3 class="finea-section-title">Contact principal</h3>
                <div class="rh-form-grid" style="grid-template-columns: repeat(2, 1fr); gap: 15px;">
                    <?= Form::input('contact_name', 'Nom du contact', '') ?>
                    <?= Form::input('phone', 'Téléphone', '', ['type' => 'tel']) ?>
                    <?= Form::input('email', 'Email', '', ['type' => 'email']) ?>
                    <div style="grid-column: span 2;">
                        <?= Form::textarea('contact_info', 'Autres informations (Adresse, IFU...)', '', [
                            'rows' => 3
                        ]) ?>
                    </div>
                </div>
            </section>

            <div class="rh-form-actions" style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
                <?= Ui::button('Annuler', ['href' => 'logistique/prestataires', 'variant' => 'secondary']) ?>
                <?= Ui::button('Enregistrer le prestataire', ['variant' => 'primary', 'type' => 'submit']) ?>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
