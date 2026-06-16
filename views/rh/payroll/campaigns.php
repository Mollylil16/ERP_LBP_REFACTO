<?php
use App\Helpers\Csrf;
use App\Helpers\View;
use App\View\Components\Ui;
use App\View\Components\Form;

require BASE_PATH . '/views/rh/_navigation.php';

ob_start();
?>

<?= Ui::pageHeader(
    'Paie',
    'Campagnes de Paie',
    'Gestion des calculs de salaire mensuels et édition des bulletins.',
    Ui::button('Nouvelle campagne', ['variant' => 'accent', 'type' => 'button', 'onclick' => 'document.getElementById("newCampaignForm").style.display="flex"']),
    ['class' => 'rh-hero']
) ?>

<div id="newCampaignForm" class="finea-section-card" style="display: none; margin-bottom: 2rem; background: #f8fafc;">
    <h3 style="margin-top: 0;">Créer une nouvelle campagne</h3>
    <form action="<?= View::url('rh/paie/campagnes') ?>" method="post" style="display: flex; gap: 1rem; align-items: flex-end;">
        <?= Csrf::input() ?>
        <?= Form::input('month', [
            'label' => 'Mois',
            'type' => 'number',
            'value' => date('n'),
            'min' => '1',
            'max' => '12'
        ]) ?>
        <?= Form::input('year', [
            'label' => 'Année',
            'type' => 'number',
            'value' => date('Y'),
            'min' => '2020'
        ]) ?>
        <?= Ui::button('Créer', ['variant' => 'primary', 'type' => 'submit']) ?>
        <?= Ui::button('Annuler', ['variant' => 'secondary', 'type' => 'button', 'onclick' => 'document.getElementById("newCampaignForm").style.display="none"']) ?>
    </form>
</div>

<?php if (empty($campaigns)): ?>
    <?= Ui::emptyState('Aucune campagne de paie', 'Commencez par créer une campagne pour ce mois.', 'request_quote') ?>
<?php else: ?>
    <div class="finea-grid" style="grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1rem;">
        <?php foreach ($campaigns as $camp): 
            $monthName = date('F', mktime(0, 0, 0, $camp['month'], 10));
        ?>
        <div class="finea-card" style="padding: 1.5rem; border: 1px solid #e2e8f0; border-radius: 8px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 style="margin: 0; font-size: 1.25rem;">Paie - <?= htmlspecialchars($monthName . ' ' . $camp['year']) ?></h3>
                <span class="finea-badge finea-badge-<?= $camp['status'] === 'draft' ? 'warning' : 'success' ?>">
                    <?= htmlspecialchars(ucfirst($camp['status'])) ?>
                </span>
            </div>
            
            <div style="margin-bottom: 1.5rem; color: #475569; font-size: 0.875rem;">
                <p style="margin: 0.25rem 0;"><strong>Employés :</strong> <?= htmlspecialchars($camp['payslip_count']) ?> bulletins</p>
                <p style="margin: 0.25rem 0;"><strong>Masse salariale nette :</strong> <?= number_format($camp['total_net'], 0, ',', ' ') ?> FCFA</p>
            </div>

            <div style="display: flex; gap: 0.5rem;">
                <form action="<?= View::url('rh/paie/campagnes/' . $camp['id'] . '/generer') ?>" method="post" style="flex: 1;">
                    <?= Csrf::input() ?>
                    <?= Ui::button('Calculer', ['variant' => 'primary', 'type' => 'submit', 'class' => 'rh-action-full']) ?>
                </form>
                <?php if ($camp['payslip_count'] > 0): ?>
                    <?= Ui::button('Voir bulletins', ['href' => 'rh/paie/campagnes/' . (int) $camp['id'] . '/bulletins', 'variant' => 'secondary', 'class' => 'rh-action-full']) ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
