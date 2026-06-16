<?php
/** @var array $clients */
use App\Helpers\View;
use App\View\Components\Ui;

ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Référentiel CRM',
            'Clients (Expéditeurs / Destinataires)',
            'Gérez vos clients et partenaires pour les expéditions.',
            Ui::button('Nouveau Client', ['href' => 'crm/clients/nouveau', 'variant' => 'primary'])
        ) ?>

        <section class="finea-table-wrap" style="margin-top: 24px;">
            <table class="finea-table">
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
                            <td colspan="7">
                                <?= Ui::emptyState('Aucun client trouvé', 'Commencez par ajouter un client ou un partenaire.') ?>
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
        </section>
    </div>
</div>
<?php $content = ob_get_clean(); require BASE_PATH . '/views/layouts/module.php'; ?>
