<?php
/** @var array $facture */
/** @var array $retraits */
use App\Helpers\View;
use App\View\Components\Ui;

ob_start();
require __DIR__ . '/../_navigation.php';
?>

<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Logistique Interne — Facture Prestataire',
            View::e($facture['invoice_number']),
            'Consultez les détails financiers et l\'historique des paiements de cette facture.',
            $facture['status'] === 'EN_ATTENTE' 
                ? Ui::button('Demander un décaissement (Retrait)', ['href' => 'logistique/retraits/nouveau/' . $facture['id'], 'variant' => 'accent'])
                : '',
            ['class' => 'rh-hero']
        ) ?>

        <div style="display: flex; justify-content: flex-end; margin-top: 15px;">
            <?= Ui::button('Retour à la liste', ['href' => 'logistique/factures', 'variant' => 'secondary']) ?>
        </div>

        <div class="finea-grid" style="grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 24px;">
            <!-- Infos Facture -->
            <section class="finea-section-card" style="margin: 0;">
                <h3 class="finea-section-title">Détails de la Facture</h3>
                <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 15px;">
                    <div>
                        <span style="display: block; font-size: 0.85rem; color: var(--finea-muted);">Prestataire</span>
                        <strong><?= View::e($facture['prestataire_name']) ?></strong>
                    </div>
                    <div>
                        <span style="display: block; font-size: 0.85rem; color: var(--finea-muted);">Numéro LTA / Dossier</span>
                        <strong><?= View::e($facture['lta_number'] ?? '—') ?></strong>
                    </div>
                    <div>
                        <span style="display: block; font-size: 0.85rem; color: var(--finea-muted);">Date d'émission</span>
                        <strong><?= $facture['issue_date'] ? date('d/m/Y', strtotime($facture['issue_date'])) : '—' ?></strong>
                    </div>
                    <div>
                        <span style="display: block; font-size: 0.85rem; color: var(--finea-muted);">Date d'échéance</span>
                        <strong><?= $facture['due_date'] ? date('d/m/Y', strtotime($facture['due_date'])) : '—' ?></strong>
                    </div>
                    <div>
                        <span style="display: block; font-size: 0.85rem; color: var(--finea-muted);">Statut de Facture</span>
                        <?= $facture['status'] === 'EN_ATTENTE' ? Ui::badge('En attente', 'warning') : Ui::badge('Payée', 'success') ?>
                    </div>
                    <div>
                        <span style="display: block; font-size: 0.85rem; color: var(--finea-muted);">Notes</span>
                        <p style="margin: 4px 0 0 0; font-size: 0.9rem; line-height: 1.5;"><?= View::e($facture['notes'] ?? '—') ?></p>
                    </div>
                </div>
            </section>

            <!-- Sommaire Financier -->
            <section class="finea-section-card" style="margin: 0;">
                <h3 class="finea-section-title">Sommaire Financier</h3>
                <div style="display: flex; flex-direction: column; gap: 15px; margin-top: 15px;">
                    <div>
                        <span style="display: block; font-size: 0.85rem; color: var(--finea-muted);">Montant Total</span>
                        <span style="font-size: 1.25rem; font-weight: 800; color: var(--finea-navy);"><?= number_format((float)$facture['amount'], 0, ',', ' ') ?> <?= View::e($facture['currency']) ?></span>
                    </div>
                    <div>
                        <span style="display: block; font-size: 0.85rem; color: var(--finea-muted);">Montant déjà payé</span>
                        <span style="font-size: 1.25rem; font-weight: 800; color: var(--finea-success);"><?= number_format((float)$facture['amount_paid'], 0, ',', ' ') ?> <?= View::e($facture['currency']) ?></span>
                    </div>
                    <div style="border-top: 1px dashed var(--finea-border); padding-top: 15px;">
                        <span style="display: block; font-size: 0.85rem; color: var(--finea-muted);">Reste à Payer (Reliquat)</span>
                        <span style="font-size: 1.6rem; font-weight: 900; color: var(--finea-danger);">
                            <?= number_format((float)$facture['reliquat'], 0, ',', ' ') ?> <?= View::e($facture['currency']) ?>
                        </span>
                    </div>
                </div>
            </section>
        </div>

        <!-- Historique des retraits -->
        <section class="finea-table-wrap" style="margin-top: 24px;">
            <div class="finea-section-card-header" style="padding: 20px 24px 0 24px;">
                <h3 class="finea-section-title" style="margin: 0;">Historique des Retraits Hub (Décaissements)</h3>
            </div>
            <table class="finea-table" style="margin-top: 15px;">
                <thead>
                    <tr>
                        <th>Référence Transaction</th>
                        <th>Montant</th>
                        <th>Date / Enregistré par</th>
                        <th>Statut / Approbation</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($retraits)): ?>
                        <tr>
                            <td colspan="5">
                                <?= Ui::emptyState('Aucun retrait enregistré', 'Aucun décaissement n\'a encore été initié pour cette facture.') ?>
                            </td>
                        </tr>
                    <?php else: foreach ($retraits as $r): ?>
                        <tr>
                            <td><strong><?= View::e($r['reference_transaction'] ?? '—') ?></strong></td>
                            <td><strong style="color:var(--finea-success);"><?= number_format((float)$r['amount_paid'], 0, ',', ' ') ?> <?= View::e($r['currency']) ?></strong></td>
                            <td>
                                <?= date('d/m/Y H:i', strtotime($r['payment_date'])) ?><br>
                                <small style="color:var(--finea-muted);"><?= View::e($r['recorded_by_name'] ?? '—') ?></small>
                            </td>
                            <td>
                                <?php if ($r['status'] === 'EN_ATTENTE'): ?>
                                    <?= Ui::badge('En attente', 'warning') ?>
                                <?php elseif ($r['status'] === 'APPROUVE'): ?>
                                    <?= Ui::badge('Approuvé', 'success') ?>
                                    <small style="display:block; color:var(--finea-muted); margin-top:4px;">le <?= date('d/m/Y H:i', strtotime($r['approved_at'])) ?></small>
                                <?php elseif ($r['status'] === 'REFUSE'): ?>
                                    <?= Ui::badge('Refusé', 'danger') ?>
                                    <small style="display:block; color:var(--finea-danger); margin-top:4px;">Raison: <?= View::e($r['rejection_reason'] ?? '') ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= View::e($r['notes'] ?? '—') ?></td>
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
