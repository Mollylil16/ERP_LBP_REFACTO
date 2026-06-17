<?php
use App\Helpers\Csrf;
use App\Helpers\View;
use App\View\Components\Ui;
use App\View\Components\Form;

require BASE_PATH . '/views/rh/_navigation.php';

ob_start();
?>

<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Paie',
            'Campagnes de Paie',
            'Gestion des calculs de salaire mensuels et édition des bulletins.',
            Ui::button('Nouvelle campagne', ['variant' => 'accent', 'type' => 'button', 'onclick' => 'document.getElementById("newCampaignForm").style.display="block"']),
            ['class' => 'rh-hero']
        ) ?>

        <div id="newCampaignForm" class="finea-section-card" style="display: none; margin-top: 1.5rem; margin-bottom: 2rem; background: #f8fafc; border: 1px dashed var(--module-accent); padding: 25px;">
            <div class="finea-section-heading">
                <h3 class="finea-section-title" style="margin: 0;">Créer une nouvelle campagne</h3>
            </div>
            
            <form action="<?= View::url('rh/paie/campagnes') ?>" method="post" style="margin-top: 1rem;">
                <?= Csrf::input() ?>
                
                <div class="rh-form-grid" style="grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 1.5rem;">
                    <?= Form::select('month', 'Mois de paie *', [
                        ['value' => '1', 'label' => 'Janvier'],
                        ['value' => '2', 'label' => 'Février'],
                        ['value' => '3', 'label' => 'Mars'],
                        ['value' => '4', 'label' => 'Avril'],
                        ['value' => '5', 'label' => 'Mai'],
                        ['value' => '6', 'label' => 'Juin'],
                        ['value' => '7', 'label' => 'Juillet'],
                        ['value' => '8', 'label' => 'Août'],
                        ['value' => '9', 'label' => 'Septembre'],
                        ['value' => '10', 'label' => 'Octobre'],
                        ['value' => '11', 'label' => 'Novembre'],
                        ['value' => '12', 'label' => 'Décembre'],
                    ], date('n'), ['required' => true]) ?>

                    <?= Form::input('year', 'Année de paie *', date('Y'), [
                        'type' => 'number',
                        'min' => '2020',
                        'required' => true
                    ]) ?>
                </div>

                <div class="rh-form-actions" style="display: flex; justify-content: flex-end; gap: 10px;">
                    <?= Ui::button('Annuler', null, [
                        'variant' => 'secondary',
                        'type' => 'button',
                        'onclick' => 'document.getElementById("newCampaignForm").style.display="none"'
                    ]) ?>
                    <?= Ui::button('Créer la campagne', null, [
                        'variant' => 'primary',
                        'type' => 'submit'
                    ]) ?>
                </div>
            </form>
        </div>

        <div style="margin-top: 1.5rem;">
            <?php if (empty($campaigns)): ?>
                <?= Ui::emptyState('Aucune campagne de paie', 'Commencez par créer une campagne pour ce mois.', 'request_quote') ?>
            <?php else: ?>
                <div class="finea-grid" style="grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem;">
                    <?php foreach ($campaigns as $camp): 
                        $monthName = date('F', mktime(0, 0, 0, $camp['month'], 10));
                    ?>
                    <div class="finea-section-card" style="padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between;">
                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                <h3 style="margin: 0; font-size: 1.25rem; color: var(--finea-navy); font-weight: 800;">Paie - <?= htmlspecialchars($monthName . ' ' . $camp['year']) ?></h3>
                                <?php if ($camp['status'] === 'draft'): ?>
                                    <?= Ui::badge('Brouillon', 'warning') ?>
                                <?php else: ?>
                                    <?= Ui::badge(ucfirst($camp['status']), 'success') ?>
                                <?php endif; ?>
                            </div>
                            
                            <div style="margin-bottom: 1.5rem; color: var(--finea-muted); font-size: 0.9rem; line-height: 1.6;">
                                <p style="margin: 0.25rem 0; display: flex; justify-content: space-between;">
                                    <span>Bulletins générés :</span> 
                                    <strong><?= htmlspecialchars($camp['payslip_count']) ?></strong>
                                </p>
                                <p style="margin: 0.25rem 0; display: flex; justify-content: space-between;">
                                    <span>Masse salariale nette :</span> 
                                    <strong><?= number_format((float) ($camp['total_net'] ?? 0.0), 0, ',', ' ') ?> FCFA</strong>
                                </p>
                            </div>
                        </div>

                        <div style="display: flex; gap: 0.5rem; border-top: 1px solid var(--finea-border); padding-top: 15px;">
                            <form action="<?= View::url('rh/paie/campagnes/' . $camp['id'] . '/generer') ?>" method="post" style="flex: 1;">
                                <?= Csrf::input() ?>
                                <?= Ui::button('Calculer la paie', null, ['variant' => 'primary', 'type' => 'submit', 'class' => 'rh-action-full']) ?>
                            </form>
                            <?php if ($camp['payslip_count'] > 0): ?>
                                <?= Ui::button('Voir bulletins', 'rh/paie/campagnes/' . (int) $camp['id'] . '/bulletins', 'secondary', 'button', ['class' => 'rh-action-full']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
