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
        <?= Ui::pageHeader(
            'Colisage — Inventaire',
            'Nouvelle Campagne d\'Inventaire',
            'Démarrez un recensement physique des colis de l\'entrepôt.',
            Ui::button('Retour à la liste', 'colisage/inventaire', 'secondary')
        ) ?>

        <div style="display: flex; justify-content: center; margin-top: 2rem;">
            <section class="finea-section-card" style="width: 100%; max-width:600px; box-shadow: 0 20px 40px rgba(29, 43, 87, 0.08); border-radius: 20px; padding: 30px;">
                <div class="finea-section-heading" style="margin-bottom: 20px;">
                    <div>
                        <p class="rh-eyebrow" style="color: var(--finea-gold);">Logistique entrepôt</p>
                        <h2 class="finea-section-title" style="margin: 0; font-size: 1.3rem;">Démarrer une campagne</h2>
                    </div>
                </div>
                
                <form action="<?= View::url('colisage/inventaire') ?>" method="POST" class="rh-compact-form">
                    <?= Csrf::input() ?>
                    
                    <div style="margin-bottom: 1.5rem;">
                        <?= Form::selectSearch('agency_id', 'Agence à inventorier *', $agencyOptions, '', [
                            'required' => true,
                            'placeholder' => 'Sélectionner l\'agence concernée'
                        ]) ?>
                    </div>
                    
                    <div style="background: #f8fafc; border: 1px solid var(--finea-border); border-radius: 12px; padding: 15px; margin-bottom: 2rem; display: flex; gap: 10px; align-items: flex-start;">
                        <span class="material-icons" style="color: var(--finea-navy); font-size: 1.3rem; margin-top: 2px;">info_outline</span>
                        <p style="font-size:.85rem; color: var(--finea-muted); line-height: 1.5; margin: 0;">
                            Une campagne sera créée avec le statut <strong>"En cours"</strong>. Vous pourrez ensuite procéder au scan individuel des colis via leur numéro de tracking pour détecter les écarts et colis manquants.
                        </p>
                    </div>

                    <div class="rh-form-actions" style="border-top: 1px solid var(--finea-border); padding-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
                        <?= Ui::button('Annuler', 'colisage/inventaire', 'secondary') ?>
                        <?= Ui::button('Démarrer la campagne', '', 'primary', 'submit') ?>
                    </div>
                </form>
            </section>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
