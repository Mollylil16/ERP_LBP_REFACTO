<?php
/** @var array $agencies */
/** @var int $currentAgencyId */

use App\Helpers\View;
use App\Helpers\Csrf;
use App\View\Components\Ui;
use App\View\Components\Form;

// Prepare agencies options (excluding current agency)
$agenciesOptions = [];
foreach ($agencies as $a) {
    if ((int)$a['id'] !== $currentAgencyId) {
        $agenciesOptions[] = ['value' => (string)$a['id'], 'label' => $a['name']];
    }
}

ob_start();
?>

<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Crédits Inter-Agences',
            'Nouveau transfert de crédit',
            'Saisir un transfert de crédit d’agence à agence. Ce montant sera déduit de votre caisse.',
            Ui::button('Retour', ['href' => 'finance/credits', 'variant' => 'secondary']),
            ['class' => 'rh-hero']
        ) ?>

        <form method="POST" action="<?= View::url('finance/credits') ?>" class="finea-section-card" style="margin-top: 24px; max-width: 600px;">
            <?= Csrf::input() ?>

            <div class="rh-form-grid" style="grid-template-columns: 1fr; gap: 20px;">
                <?= Form::select('to_agency_id', 'Agence destinataire', $agenciesOptions, '', [
                    'required' => true
                ]) ?>

                <?= Form::input('amount', 'Montant du crédit / transfert (XOF)', '', [
                    'type' => 'number',
                    'required' => true,
                    'min' => 1,
                    'step' => '0.01'
                ]) ?>

                <?= Form::textarea('reason', 'Motif / Justification', '', [
                    'required' => true,
                    'rows' => 3,
                    'placeholder' => 'Prêt de trésorerie pour couvrir des retraits, compensation de frais...'
                ]) ?>
            </div>

            <div class="rh-form-actions" style="margin-top: 30px; display: flex; justify-content: flex-end;">
                <?= Ui::button('Envoyer le crédit', [
                    'type' => 'submit',
                    'variant' => 'accent'
                ]) ?>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
