<?php
/** @var array $prestataires */
use App\Helpers\Csrf;
use App\Helpers\View;
use App\View\Components\Ui;
use App\View\Components\Form;

ob_start();
require __DIR__ . '/../_navigation.php';

// Prepare prestataires options
$prestatairesOptions = [['value' => '', 'label' => '— Sélectionner le prestataire —']];
foreach ($prestataires as $p) {
    $prestatairesOptions[] = [
        'value' => (string)$p['id'],
        'label' => $p['name'] . ' (' . $p['type'] . ')'
    ];
}
?>

<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Logistique Interne — Factures',
            'Nouvelle Facture',
            'Saisissez les détails de la facture reçue d\'un prestataire.',
            Ui::button('Retour', ['href' => 'logistique/factures', 'variant' => 'secondary']),
            ['class' => 'rh-hero']
        ) ?>

        <form action="<?= View::url('logistique/factures') ?>" method="POST" style="margin-top: 24px;">
            <?= Csrf::input() ?>

            <section class="finea-section-card">
                <h3 class="finea-section-title">Détails de la facture</h3>
                <div class="rh-form-grid" style="grid-template-columns: repeat(2, 1fr); gap: 15px;">
                    <div style="grid-column: span 2;">
                        <?= Form::select('prestataire_id', 'Prestataire', $prestatairesOptions, '', [
                            'required' => true,
                            'autofocus' => true
                        ]) ?>
                    </div>

                    <?= Form::input('invoice_number', 'Numéro de facture (référence)', '', [
                        'required' => true
                    ]) ?>

                    <?= Form::input('lta_number', 'N° LTA ou Dossier (Optionnel)', '', [
                        'placeholder' => 'Pour relier à un groupage'
                    ]) ?>

                    <?= Form::input('amount', 'Montant total', '', [
                        'type' => 'number',
                        'step' => '0.01',
                        'min' => '0',
                        'required' => true
                    ]) ?>

                    <?= Form::select('currency', 'Devise', [
                        ['value' => 'XOF', 'label' => 'FCFA (XOF)'],
                        ['value' => 'EUR', 'label' => 'Euro (EUR)'],
                        ['value' => 'USD', 'label' => 'Dollar (USD)']
                    ], 'XOF', ['required' => true]) ?>

                    <?= Form::input('issue_date', 'Date d\'émission', '', [
                        'type' => 'date'
                    ]) ?>

                    <?= Form::input('due_date', 'Date d\'échéance / Date limite', '', [
                        'type' => 'date'
                    ]) ?>

                    <div style="grid-column: span 2;">
                        <?= Form::textarea('notes', 'Notes / Description des prestations', '', [
                            'rows' => 3
                        ]) ?>
                    </div>
                </div>
            </section>

            <div class="rh-form-actions" style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
                <?= Ui::button('Annuler', ['href' => 'logistique/factures', 'variant' => 'secondary']) ?>
                <?= Ui::button('Enregistrer la facture', ['variant' => 'primary', 'type' => 'submit']) ?>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
