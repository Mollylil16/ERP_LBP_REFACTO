<?php
/** @var array $facture */
use App\Helpers\Csrf;
use App\Helpers\View;
use App\View\Components\Ui;
use App\View\Components\Form;

ob_start();
require __DIR__ . '/../_navigation.php';

$reliquat = (float)$facture['reliquat'];
?>

<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Logistique Interne — Décaissement',
            'Demande de retrait Hub',
            'Facture ' . View::e($facture['invoice_number']) . ' — ' . View::e($facture['prestataire_name']),
            Ui::button('Retour à la facture', ['href' => 'logistique/factures/' . $facture['id'], 'variant' => 'secondary']),
            ['class' => 'rh-hero']
        ) ?>

        <div class="finea-grid" style="grid-template-columns: 1fr 2fr; gap: 24px; margin-top: 24px;">
            <section class="finea-section-card" style="margin: 0; background: #fff5f5; border: 1px solid #feb2b2;">
                <h3 class="finea-section-title" style="color: var(--finea-danger);">Situation Facture</h3>
                <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 15px;">
                    <div>
                        <span style="display: block; font-size: 0.85rem; color: var(--finea-muted);">Montant total</span>
                        <strong style="font-size: 1.1rem;"><?= number_format((float)$facture['amount'], 0, ',', ' ') ?> <?= View::e($facture['currency']) ?></strong>
                    </div>
                    <div>
                        <span style="display: block; font-size: 0.85rem; color: var(--finea-muted);">Déjà payé</span>
                        <strong style="font-size: 1.1rem; color: var(--finea-success);"><?= number_format((float)$facture['amount_paid'], 0, ',', ' ') ?> <?= View::e($facture['currency']) ?></strong>
                    </div>
                    <div style="border-top: 1px dashed #feb2b2; padding-top: 12px;">
                        <span style="display: block; font-size: 0.85rem; color: var(--finea-muted);">Reste à payer</span>
                        <strong style="font-size: 1.4rem; font-weight: 800; color: var(--finea-danger);">
                            <?= number_format($reliquat, 0, ',', ' ') ?> <?= View::e($facture['currency']) ?>
                        </strong>
                    </div>
                </div>
            </section>

            <form action="<?= View::url('logistique/retraits') ?>" method="POST" class="finea-section-card" style="margin: 0;">
                <?= Csrf::input() ?>
                <input type="hidden" name="facture_id" value="<?= $facture['id'] ?>">

                <h3 class="finea-section-title">Saisie du décaissement</h3>
                <p style="font-size: 0.85rem; color: var(--finea-muted); margin-bottom: 20px;">
                    Le montant demandé sera soumis à la validation de la caisse centrale. Le statut de la facture sera mis à jour automatiquement après approbation.
                </p>

                <div class="rh-form-grid" style="grid-template-columns: repeat(2, 1fr); gap: 15px;">
                    <?= Form::input('amount_paid', 'Montant demandé (' . View::e($facture['currency']) . ')', $reliquat, [
                        'type' => 'number',
                        'step' => '0.01',
                        'min' => '1',
                        'max' => $reliquat,
                        'required' => true,
                        'autofocus' => true,
                        'hint' => 'Ne peut dépasser le reste à payer.',
                        'style' => 'font-size: 1.2rem; font-weight: bold; color: var(--module-accent);'
                    ]) ?>

                    <?= Form::input('reference_transaction', 'Référence (Chèque, Virement...)', '', [
                        'placeholder' => 'Ex: CHQ-2023-XXXX'
                    ]) ?>

                    <div style="grid-column: span 2;">
                        <?= Form::textarea('notes', 'Justification / Notes', '', [
                            'rows' => 2,
                            'placeholder' => 'Ex: Paiement acompte 50%, Solde facture...'
                        ]) ?>
                    </div>
                </div>

                <div class="rh-form-actions" style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
                    <?= Ui::button('Annuler', ['href' => 'logistique/factures/' . $facture['id'], 'variant' => 'secondary']) ?>
                    <?= Ui::button('Soumettre la demande', ['variant' => 'primary', 'type' => 'submit']) ?>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
