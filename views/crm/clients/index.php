<?php
/** @var array $clients */
use App\Helpers\View;
use App\View\Components\Ui;

ob_start();
require __DIR__ . '/../_navigation.php';
?>

<?= Ui::pageHeader('Référentiel CRM', 'Clients', [
    'actions' => Ui::button('Nouveau Client', 'crm/clients/nouveau', [
        'variant' => 'primary'
    ])
]) ?>

<div class="finea-section-card" style="margin-top: 24px;">
    <table class="data-table">
        <thead>
            <tr>
                <th>Nom</th>
                <th>Type</th>
                <th>Téléphone</th>
                <th>Email</th>
                <th>Ville</th>
                <th>Statut</th>
                <th>Date d'ajout</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($clients)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 2rem;">
                        <?= Ui::emptyState('Aucun client trouvé', 'Commencez par ajouter un client ou un partenaire.', 'people') ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($clients as $client): ?>
                <tr>
                    <td><strong><?= View::e($client['name']) ?></strong></td>
                    <td><?= Ui::badge(ucfirst($client['type'] ?? ''), 'info') ?></td>
                    <td><?= View::e($client['phone'] ?? '-') ?></td>
                    <td><?= View::e($client['email'] ?? '-') ?></td>
                    <td><?= View::e($client['city'] ?? '-') ?></td>
                    <td>
                        <?php if (($client['status'] ?? '') === 'active'): ?>
                            <?= Ui::badge('Actif', 'success') ?>
                        <?php else: ?>
                            <?= Ui::badge(ucfirst($client['status'] ?? ''), 'neutral') ?>
                        <?php endif; ?>
                    </td>
                    <td><?= date('d/m/Y', strtotime($client['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php 
$content = ob_get_clean(); 
require BASE_PATH . '/views/layouts/module.php'; 
?>
