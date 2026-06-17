<?php
/** @var array $credits */
/** @var int $agencyId */

use App\Helpers\View;
use App\Helpers\Csrf;
use App\View\Components\Ui;

ob_start();
?>

<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Finance & Trésorerie',
            'Crédits Inter-Agences',
            'Suivi des prêts, transferts de fonds et compensations financières entre agences.',
            Ui::button('Nouveau transfert de crédit', ['href' => 'finance/credits/nouveau', 'variant' => 'accent']),
            ['class' => 'rh-hero']
        ) ?>

        <section class="finea-table-wrap" style="margin-top: 24px;">
            <table class="finea-table">
                <thead>
                    <tr>
                        <th>Date création</th>
                        <th>Agence Émettrice</th>
                        <th>Agence Destinataire</th>
                        <th>Montant</th>
                        <th>Motif / Justification</th>
                        <th>Statut</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($credits)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding:20px; color:var(--finea-muted);">Aucun crédit ou transfert inter-agence enregistré.</td>
                        </tr>
                    <?php else: foreach ($credits as $c): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></td>
                            <td>
                                <strong><?= View::e($c['from_agency_name']) ?></strong>
                                <?php if ((int)$c['from_agency_id'] === $agencyId): ?>
                                    <span style="color:var(--finea-danger); font-size:0.75rem;">(Émis)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= View::e($c['to_agency_name']) ?></strong>
                                <?php if ((int)$c['to_agency_id'] === $agencyId): ?>
                                    <span style="color:var(--finea-success); font-size:0.75rem;">(Reçu)</span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= number_format((float)$c['amount'], 2, ',', ' ') ?> XOF</strong></td>
                            <td><?= View::e($c['reason'] ?? '—') ?></td>
                            <td>
                                <?php if ($c['status'] === 'VALIDE'): ?>
                                    <?= Ui::badge('Apuré / Validé', 'success') ?><br>
                                    <small style="color:var(--finea-muted);">le <?= date('d/m/y', strtotime($c['settled_at'])) ?></small>
                                <?php else: ?>
                                    <?= Ui::badge('En attente', 'warning') ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($c['status'] === 'EN_ATTENTE' && (int)$c['to_agency_id'] === $agencyId): ?>
                                    <form method="POST" action="<?= View::url('finance/credits/' . $c['id'] . '/apurer') ?>" style="margin:0;">
                                        <?= Csrf::input() ?>
                                        <?= Ui::button('Confirmer la réception', [
                                            'type' => 'submit',
                                            'variant' => 'success',
                                            'size' => 'sm',
                                            'onclick' => "return confirm('Valider la réception de ces fonds dans votre caisse ?')"
                                        ]) ?>
                                    </form>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
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
