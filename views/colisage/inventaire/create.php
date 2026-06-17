<?php
/** @var array $agencies */
use App\Helpers\Csrf;
use App\Helpers\View;
use App\View\Components\Ui;
use App\View\Components\Form;

ob_start();
require __DIR__ . '/../_navigation.php';

$agencyOptions = array_map(fn($a) => [
    'value' => $a['id'],
    'label' => $a['name']
], $agencies);
?>

<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader('Colisage — Inventaire', 'Nouvelle Campagne d\'Inventaire', [
            'actions' => Ui::button('Retour', 'colisage/inventaire', [
                'variant' => 'ghost'
            ])
        ]) ?>

        <div style="display: flex; justify-content: center; margin-top: 1.5rem;">
            <section class="finea-section-card" style="width: 100%; max-width:600px;">
                <div class="finea-section-heading">
                    <h2 class="finea-section-title">Démarrer une campagne</h2>
                </div>
                <form action="<?= View::url('colisage/inventaire') ?>" method="POST">
                    <?= Csrf::input() ?>
                    <div style="margin-bottom: 1.5rem;">
                        <?= Form::select('agency_id', 'Agence à inventorier *', $agencyOptions, '', ['required' => true]) ?>
                    </div>
                    <p style="font-size:.85rem; color:var(--finea-muted); line-height: 1.5; margin-bottom: 1.5rem;">
                        Une campagne sera créée en statut "EN COURS". Vous pourrez ensuite scanner les colis un par un via leur numéro de tracking.
                    </p>
                    <div class="rh-form-actions">
                        <?= Ui::button('Démarrer l\'inventaire', null, [
                            'type' => 'submit',
                            'variant' => 'primary'
                        ]) ?>
                    </div>
                </form>
            </section>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
