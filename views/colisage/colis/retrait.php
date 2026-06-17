<?php
/** @var array $colis */
use App\Helpers\Csrf;
use App\Helpers\View;
use App\View\Components\Ui;
use App\View\Components\Form;

ob_start();
require __DIR__ . '/../_navigation.php';
?>

<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader('Colisage — Remise au destinataire', 'Colis ' . $colis['tracking_number'], [
            'actions' => Ui::button('Retour', 'colisage/colis/' . $colis['id'], ['variant' => 'ghost'])
        ]) ?>

        <!-- Récapitulatif colis & Alerte -->
        <div class="finea-grid" style="grid-template-columns: 1fr 1fr; gap:1.5rem; margin-top: 1.5rem; margin-bottom:1.5rem;">
            <section class="finea-section-card">
                <div class="finea-section-heading">
                    <h2 class="finea-section-title">Colis à remettre</h2>
                </div>
                <div class="rh-detail-grid" style="grid-template-columns: 1fr;">
                    <div>
                        <small>Tracking</small>
                        <strong><?= View::e($colis['tracking_number']) ?></strong>
                    </div>
                    <div>
                        <small>Destinataire officiel</small>
                        <strong><?= View::e($colis['receiver_name'] ?? '—') ?></strong>
                        <span style="font-size: 0.8rem; color: var(--finea-muted);"><?= View::e($colis['receiver_phone'] ?? '') ?></span>
                    </div>
                    <div>
                        <small>Agence d'arrivée</small>
                        <strong><?= View::e($colis['arrival_agency'] ?? '—') ?></strong>
                    </div>
                </div>
            </section>

            <section class="finea-section-card" style="border-left: 4px solid var(--finea-gold); background: #fffdf5;">
                <div class="finea-section-heading">
                    <h2 class="finea-section-title" style="color:#92400e;">Vérification d'identité obligatoire</h2>
                </div>
                <p style="font-size:.9rem; color:#78350f; line-height: 1.6; margin: 0;">
                    Avant toute remise, vérifiez <strong>physiquement</strong> la pièce d'identité du récupérateur (CNI, Passeport).
                    Ces informations sont enregistrées et engagent la responsabilité de l'opérateur de saisie.
                </p>
            </section>
        </div>

        <!-- Formulaire retrait -->
        <section class="finea-section-card">
            <div class="finea-section-heading">
                <h2 class="finea-section-title">Informations du récupérateur</h2>
            </div>
            <form method="POST" action="<?= View::url('colisage/colis/' . $colis['id'] . '/retrait') ?>" id="form-retrait">
                <?= Csrf::input() ?>
                <div class="rh-form-grid">
                    <?= Form::input('retrieval_name', 'Nom complet du récupérateur *', '', ['placeholder' => 'Prénom Nom', 'required' => true, 'autofocus' => true]) ?>
                    <?= Form::input('retrieval_cni', 'N° de pièce d\'identité (CNI) *', '', ['placeholder' => 'CI-XXXX-XXXXXX', 'required' => true]) ?>
                    <?= Form::input('retrieval_phone', 'Téléphone du récupérateur', '', ['type' => 'tel', 'placeholder' => '+225 00 00 00 00']) ?>
                </div>
                <div class="rh-form-actions" style="margin-top: 1.5rem;">
                    <?= Ui::button('Annuler', 'colisage/colis/' . $colis['id'], ['variant' => 'ghost']) ?>
                    <?= Ui::button('Confirmer la remise & marquer RETIRÉ', null, [
                        'type' => 'submit',
                        'variant' => 'primary',
                        'onclick' => 'return confirm("Confirmer la remise du colis ? Cette action est irréversible.")'
                    ]) ?>
                </div>
            </form>
        </section>
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
