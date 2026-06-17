<?php
/** @var array $agencies */
use App\Helpers\Csrf;
use App\Helpers\View;
use App\View\Components\Ui;
use App\View\Components\Form;

ob_start();
require __DIR__ . '/../_navigation.php';

// Prepare agencies options
$agenciesOptions = [['value' => '', 'label' => '— Sélectionner —']];
foreach ($agencies as $a) {
    $agenciesOptions[] = ['value' => (string)$a['id'], 'label' => $a['name']];
}
?>

<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Logistique Interne — Crédits',
            'Nouveau transfert / Dette inter-agences',
            'Enregistrez une dette ou un transfert financier entre deux agences.',
            Ui::button('Retour', ['href' => 'logistique/credits', 'variant' => 'secondary']),
            ['class' => 'rh-hero']
        ) ?>

        <form action="<?= View::url('logistique/credits') ?>" method="POST" style="margin-top: 24px;">
            <?= Csrf::input() ?>

            <section class="finea-section-card">
                <h3 class="finea-section-title">Détails de la transaction</h3>
                <div class="rh-form-grid" style="grid-template-columns: repeat(2, 1fr); gap: 15px;">
                    <?= Form::select('from_agency_id', 'Agence Débitrice (Celle qui doit)', $agenciesOptions, '', [
                        'required' => true,
                        'autofocus' => true
                    ]) ?>

                    <?= Form::select('to_agency_id', 'Agence Créancière (Celle qui reçoit)', $agenciesOptions, '', [
                        'required' => true
                    ]) ?>

                    <?= Form::input('amount', 'Montant', '', [
                        'type' => 'number',
                        'step' => '0.01',
                        'min' => '0',
                        'required' => true
                    ]) ?>

                    <?= Form::select('currency', 'Devise', [
                        ['value' => 'XOF', 'label' => 'FCFA (XOF)'],
                        ['value' => 'EUR', 'label' => 'Euro (EUR)']
                    ], 'XOF', ['required' => true]) ?>

                    <div>
                        <?= Form::input('reference_colis', 'Référence Colis associé (Optionnel)', '', [
                            'placeholder' => 'Ex: LBP-2023-XXXX'
                        ]) ?>
                        <small style="font-size: 0.75rem; color: var(--finea-muted); display: block; margin-top: 4px;">Si cette dette est liée à l'expédition d'un colis précis.</small>
                    </div>

                    <?= Form::input('reason', 'Motif / Raison de la dette', '', [
                        'required' => true,
                        'placeholder' => 'Ex: Frais de douane avancés, transport local...'
                    ]) ?>
                </div>
            </section>

            <div class="rh-form-actions" style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
                <?= Ui::button('Annuler', ['href' => 'logistique/credits', 'variant' => 'secondary']) ?>
                <?= Ui::button('Enregistrer la transaction', ['variant' => 'primary', 'type' => 'submit']) ?>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
