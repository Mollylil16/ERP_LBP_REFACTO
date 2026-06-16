<?php
use App\Helpers\Csrf;
use App\Helpers\View;
use App\View\Components\Ui;

require BASE_PATH . '/views/rh/_navigation.php';

ob_start();
?>

<?= Ui::pageHeader(
    'Congés',
    'Congés & Absences',
    $isHR ? 'Gestion des demandes de congés de tous les employés.' : 'Suivi de vos demandes et de votre solde de congés.',
    Ui::button('Nouvelle demande', ['href' => 'rh/conges/nouveau', 'variant' => 'accent']),
    ['class' => 'rh-hero']
) ?>

<?php if (!$isHR && $balance !== null): ?>
<div class="finea-section-card" style="margin-bottom: 2rem; background: #f0fdf4; border-color: #bbf7d0;">
    <div style="display: flex; align-items: center; gap: 1rem;">
        <i class="finea-icon" style="font-size: 2.5rem; color: #16a34a;">beach_access</i>
        <div>
            <h3 style="margin: 0; color: #166534;">Votre Solde de Congés</h3>
            <p style="margin: 0.25rem 0 0 0; font-size: 1.5rem; font-weight: bold; color: #15803d;">
                <?= number_format($balance, 2, ',', ' ') ?> jours
            </p>
            <p style="margin: 0; font-size: 0.875rem; color: #166534;">Calculé dynamiquement sur la base de 2.2 jours / mois de travail.</p>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="finea-section-card">
    <?php if (empty($requests)): ?>
        <?= Ui::emptyState('Aucune demande', 'Il n\'y a aucune demande de congé à afficher.', 'event_busy') ?>
    <?php else: ?>
        <table class="finea-table">
            <thead>
                <tr>
                    <?php if ($isHR): ?>
                    <th>Employé</th>
                    <?php endif; ?>
                    <th>Type</th>
                    <th>Du</th>
                    <th>Au</th>
                    <th>Jours</th>
                    <th>Statut</th>
                    <th>Soumis le</th>
                    <?php if ($isHR): ?>
                    <th>Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requests as $row): 
                    $statusColor = match($row['status']) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'error',
                        'cancelled' => 'secondary',
                        default => 'secondary'
                    };
                    $statusLabel = match($row['status']) {
                        'pending' => 'En attente',
                        'approved' => 'Approuvé',
                        'rejected' => 'Refusé',
                        'cancelled' => 'Annulé',
                        default => $row['status']
                    };
                ?>
                <tr>
                    <?php if ($isHR): ?>
                    <td>
                        <strong><?= htmlspecialchars($row['full_name']) ?></strong><br>
                        <small style="color: #64748b;"><?= htmlspecialchars($row['employee_number']) ?></small>
                    </td>
                    <?php endif; ?>
                    <td>
                        <?= htmlspecialchars($row['type_name']) ?><br>
                        <?php if ($row['deduct_from_balance']): ?>
                            <small style="color: #64748b;">Déduit du solde</small>
                        <?php else: ?>
                            <small style="color: #16a34a;">Ne déduit pas du solde</small>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($row['start_date']) ?></td>
                    <td><?= htmlspecialchars($row['end_date']) ?></td>
                    <td><?= htmlspecialchars($row['duration_days']) ?></td>
                    <td>
                        <span class="finea-badge finea-badge-<?= $statusColor ?>">
                            <?= htmlspecialchars($statusLabel) ?>
                        </span>
                    </td>
                    <td><?= date('Y-m-d', strtotime($row['created_at'])) ?></td>
                    <?php if ($isHR): ?>
                    <td>
                        <?php if ($row['status'] === 'pending'): ?>
                        <div style="display: flex; gap: 0.5rem;">
                            <form action="<?= View::url('rh/conges/' . $row['id'] . '/valider') ?>" method="post" style="display:inline;">
                                <?= Csrf::input() ?>
                                <?= Ui::button('Approuver', ['variant' => 'accent', 'type' => 'submit', 'title' => 'Approuver']) ?>
                            </form>
                            <form action="<?= View::url('rh/conges/' . $row['id'] . '/refuser') ?>" method="post" style="display:inline;">
                                <?= Csrf::input() ?>
                                <?= Ui::button('Refuser', ['variant' => 'danger', 'type' => 'submit', 'title' => 'Refuser']) ?>
                            </form>
                        </div>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
