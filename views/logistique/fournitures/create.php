<?php
/** @var array $agencies */
use App\Helpers\Csrf;
use App\Helpers\View;
use App\View\Components\Ui;
use App\View\Components\Form;

ob_start();
require __DIR__ . '/../_navigation.php';

// Prepare agencies options
$agenciesOptions = [['value' => '', 'label' => '— Sélectionner l\'agence —']];
foreach ($agencies as $a) {
    $agenciesOptions[] = ['value' => (string)$a['id'], 'label' => $a['name']];
}
?>

<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Logistique Interne — Fournitures',
            'Nouvelle demande de fournitures',
            'Saisissez les articles et consommables requis pour votre agence.',
            Ui::button('Retour', ['href' => 'logistique/fournitures', 'variant' => 'secondary']),
            ['class' => 'rh-hero']
        ) ?>

        <form action="<?= View::url('logistique/fournitures') ?>" method="POST" style="margin-top: 24px; max-width: 800px;">
            <?= Csrf::input() ?>

            <section class="finea-section-card">
                <h3 class="finea-section-title">Détails de la demande</h3>
                <div class="rh-form-grid" style="grid-template-columns: 1fr; gap: 15px;">
                    <?= Form::select('agency_id', 'Agence demanderesse', $agenciesOptions, '', [
                        'required' => true,
                        'autofocus' => true
                    ]) ?>

                    <div>
                        <?= Form::textarea('items_requested', 'Articles demandés', '', [
                            'rows' => 6,
                            'required' => true,
                            'placeholder' => "- 5 Rames de papier A4\n- 10 Rouleaux de scotch d'emballage\n- 2 Cartouches d'encre (HP 305)\n..."
                        ]) ?>
                        <small style="font-size: 0.75rem; color: var(--finea-muted); display: block; margin-top: 4px;">Détaillez les articles, quantités et références si possible.</small>
                    </div>
                </div>
            </section>

            <div class="rh-form-actions" style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
                <?= Ui::button('Annuler', ['href' => 'logistique/fournitures', 'variant' => 'secondary']) ?>
                <?= Ui::button('Soumettre la demande', ['variant' => 'primary', 'type' => 'submit']) ?>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
