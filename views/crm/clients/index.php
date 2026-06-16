<?php
/** @var array $clients */
ob_start();
?>
<div class="finea-page-header">
    <div>
        <p class="finea-eyebrow">Référentiel CRM</p>
        <h2>Clients (Expéditeurs / Destinataires)</h2>
        <p class="finea-subtitle">Gérez vos clients et partenaires pour les expéditions.</p>
    </div>
    <div class="finea-header-actions">
        <a href="<?= \App\Helpers\View::url('crm/clients/nouveau') ?>" class="finea-action-btn finea-action-btn--primary">
            Nouveau Client
        </a>
    </div>
</div>

<div class="finea-card">
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
            <?php foreach ($clients as $client): ?>
            <tr>
                <td><strong><?= htmlspecialchars($client['name'], ENT_QUOTES) ?></strong></td>
                <td><span class="finea-badge"><?= htmlspecialchars($client['type']) ?></span></td>
                <td><?= htmlspecialchars($client['phone'] ?? '-') ?></td>
                <td><?= htmlspecialchars($client['email'] ?? '-') ?></td>
                <td><?= htmlspecialchars($client['city'] ?? '-') ?></td>
                <td>
                    <?php if($client['status'] === 'active'): ?>
                        <span class="finea-badge finea-badge-success">Actif</span>
                    <?php else: ?>
                        <span class="finea-badge"><?= htmlspecialchars($client['status']) ?></span>
                    <?php endif; ?>
                </td>
                <td><?= date('d/m/Y', strtotime($client['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($clients)): ?>
            <tr>
                <td colspan="7" class="finea-empty-table">Aucun client trouvé.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/app.php';
