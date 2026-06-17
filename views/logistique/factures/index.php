<?php
/** @var array $factures */
/** @var array $prestataires */
/** @var array $filters */
use App\Helpers\View;
use App\View\Components\Ui;
use App\View\Components\Form;

ob_start();
require __DIR__ . '/../_navigation.php';

// Prepare prestataires options
$prestatairesOptions = [['value' => '', 'label' => 'Tous']];
foreach ($prestataires as $p) {
    $prestatairesOptions[] = ['value' => (string)$p['id'], 'label' => $p['name']];
}
?>

<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Logistique Interne',
            'Factures Prestataires',
            'Saisie et suivi des factures des prestataires de transit et de transport.',
            Ui::button('Saisir une facture', ['href' => 'logistique/factures/nouvelle', 'variant' => 'accent']),
            ['class' => 'rh-hero']
        ) ?>

        <form method="GET" action="<?= View::url('logistique/factures') ?>" class="finea-section-card" style="margin-top: 24px;">
            <div class="rh-form-grid" style="grid-template-columns: 2fr 1.5fr auto; gap: 15px; align-items: flex-end;">
                <?= Form::select('prestataire_id', 'Prestataire', $prestatairesOptions, $filters['prestataire_id'] ?? '') ?>

                <?= Form::select('status', 'Statut', [
                    ['value' => '', 'label' => 'Tous les statuts'],
                    ['value' => 'EN_ATTENTE', 'label' => 'En attente (Impayé)'],
                    ['value' => 'PAYEE', 'label' => 'Payée / Soldée']
                ], $filters['status'] ?? '') ?>

                <?= Ui::button('Filtrer', ['type' => 'submit', 'variant' => 'secondary']) ?>
            </div>
        </form>

        <section class="finea-table-wrap" style="margin-top: 24px;">
            <table class="finea-table">
                <thead>
                    <tr>
                        <th>N° Facture</th>
                        <th>Prestataire</th>
                        <th>Date d'émission</th>
                        <th>Montant Total</th>
                        <th>Reste à payer</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($factures)): ?>
                        <tr>
                            <td colspan="7">
                                <?= Ui::emptyState('Aucune facture trouvée', 'Saisissez une facture pour commencer le suivi.') ?>
                            </td>
                        </tr>
                    <?php else: foreach ($factures as $f): ?>
                        <tr>
                            <td><strong><?= View::e($f['invoice_number']) ?></strong></td>
                            <td>
                                <?= View::e($f['prestataire_name']) ?><br>
                                <small style="color:var(--finea-muted);"><?= View::e($f['prestataire_type']) ?></small>
                            </td>
                            <td><?= $f['issue_date'] ? date('d/m/Y', strtotime($f['issue_date'])) : '—' ?></td>
                            <td><?= number_format((float)$f['amount'], 0, ',', ' ') ?> <?= View::e($f['currency']) ?></td>
                            <td>
                                <?php if ((float)$f['reliquat'] > 0): ?>
                                    <strong style="color:var(--finea-danger);"><?= number_format((float)$f['reliquat'], 0, ',', ' ') ?> <?= View::e($f['currency']) ?></strong>
                                <?php else: ?>
                                    <span style="color:var(--finea-success);">0 <?= View::e($f['currency']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $f['status'] === 'EN_ATTENTE' ? Ui::badge('En attente', 'warning') : Ui::badge('Payée', 'success') ?>
                            </td>
                            <td>
                                <?= Ui::button('Voir', ['href' => "logistique/factures/{$f['id']}", 'variant' => 'plain', 'size' => 'sm']) ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </section>
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
