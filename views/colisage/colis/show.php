<?php
/** @var array $colis */
/** @var array $marchandises */
/** @var array $tracking */
use App\Helpers\Csrf;
use App\Helpers\View;
use App\View\Components\Ui;
use App\View\Components\Form;

ob_start();
require __DIR__ . '/../_navigation.php';

$statusLabels = [
    'RECEPTIONNE'    => ['label' => 'Réceptionné',     'tone' => 'warning'],
    'EN_PREPARATION' => ['label' => 'En préparation',  'tone' => 'info'],
    'EN_TRANSIT'     => ['label' => 'En transit',      'tone' => 'purple'],
    'ARRIVE'         => ['label' => 'Arrivé',          'tone' => 'success'],
    'RETIRE'         => ['label' => 'Retiré',          'tone' => 'neutral'],
];
$st = $statusLabels[$colis['status']] ?? ['label' => $colis['status'], 'tone' => 'neutral'];
?>

<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader('Colisage — Fiche Colis', $colis['tracking_number'], [
            'actions' => '<div style="display:flex; gap:.5rem; align-items:center;">'
                . ($colis['status'] === 'ARRIVE' ? Ui::button('Procéder au retrait', 'colisage/colis/' . $colis['id'] . '/retrait', ['variant' => 'primary']) : '')
                . Ui::button('Liste', 'colisage/colis', ['variant' => 'ghost'])
                . '</div>'
        ]) ?>

        <div style="margin-top: 1.5rem; display: flex; gap: 10px; align-items: center; margin-bottom: 1.5rem;">
            Statut actuel : <?= Ui::badge($st['label'], $st['tone']) ?>
        </div>

        <div class="finea-grid" style="grid-template-columns: 1.2fr 0.8fr; gap:1.5rem; margin-bottom:1.5rem;">
            <!-- Informations colis -->
            <section class="finea-section-card">
                <div class="finea-section-heading">
                    <h2 class="finea-section-title">Informations Générales</h2>
                </div>
                <div class="rh-detail-grid">
                    <div>
                        <small>Expéditeur</small>
                        <strong><?= View::e($colis['sender_name'] ?? '—') ?></strong>
                        <span style="font-size: 0.8rem; color: var(--finea-muted);"><?= View::e($colis['sender_phone'] ?? '') ?></span>
                    </div>
                    <div>
                        <small>Destinataire</small>
                        <strong><?= View::e($colis['receiver_name'] ?? '—') ?></strong>
                        <span style="font-size: 0.8rem; color: var(--finea-muted);"><?= View::e($colis['receiver_phone'] ?? '') ?></span>
                    </div>
                    <div>
                        <small>Trajet</small>
                        <strong><?= View::e($colis['departure_agency'] ?? '—') ?> → <?= View::e($colis['arrival_agency'] ?? '—') ?></strong>
                    </div>
                    <div>
                        <small>Poids total</small>
                        <strong><?= number_format((float)$colis['total_weight'], 2) ?> kg</strong>
                    </div>
                    <div>
                        <small>Valeur déclarée</small>
                        <strong><?= number_format((float)$colis['declared_value'], 0, ',', ' ') ?> <?= View::e($colis['currency']) ?></strong>
                    </div>
                    <div>
                        <small>Prix facturé</small>
                        <strong><?= number_format((float)$colis['total_price'], 0, ',', ' ') ?> <?= View::e($colis['currency']) ?></strong>
                    </div>
                    <?php if ($colis['description']): ?>
                    <div class="rh-field-wide">
                        <small>Description</small>
                        <strong><?= View::e($colis['description']) ?></strong>
                    </div>
                    <?php endif; ?>

                    <?php if ($colis['status'] === 'RETIRE'): ?>
                    <div style="border-bottom-color: var(--finea-success);">
                        <small style="color: var(--finea-success);">Retiré par</small>
                        <strong><?= View::e($colis['retrieval_name'] ?? '—') ?></strong>
                        <span style="font-size: 0.8rem; color: var(--finea-muted);">CNI: <?= View::e($colis['retrieval_cni'] ?? '—') ?></span>
                    </div>
                    <div style="border-bottom-color: var(--finea-success);">
                        <small style="color: var(--finea-success);">Date retrait</small>
                        <strong><?= $colis['retrieved_at'] ? date('d/m/Y H:i', strtotime($colis['retrieved_at'])) : '—' ?></strong>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Marchandises -->
            <section class="finea-section-card">
                <div class="finea-section-heading">
                    <h2 class="finea-section-title">Marchandises (<?= count($marchandises) ?>)</h2>
                </div>
                <?php if (empty($marchandises)): ?>
                <?= Ui::emptyState('Aucun détail', 'Aucun détail de marchandise enregistré.') ?>
                <?php else: ?>
                <div class="finea-table-wrap">
                    <table class="finea-table">
                        <thead>
                            <tr><th>Description</th><th>Qté</th><th>Poids</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($marchandises as $m): ?>
                            <tr>
                                <td><strong><?= View::e($m['description']) ?></strong></td>
                                <td><?= $m['quantity'] ?></td>
                                <td><?= number_format((float)$m['unit_weight'], 2) ?> kg</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </section>
        </div>

        <!-- Tracking Timeline -->
        <section class="finea-section-card" style="margin-bottom:1.5rem;">
            <div class="finea-section-heading">
                <h2 class="finea-section-title">Historique de suivi (Tracking)</h2>
            </div>

            <?php if (!empty($tracking)): ?>
            <div class="finea-timeline">
                <?php foreach (array_reverse($tracking) as $i => $event): ?>
                <div class="finea-timeline-item">
                    <strong><?= View::e($event['step_name']) ?></strong>
                    <span>
                        <?= date('d/m/Y H:i', strtotime($event['recorded_at'])) ?>
                        <?= $event['recorded_by_name'] ? ' · ' . View::e($event['recorded_by_name']) : '' ?>
                        <?php if ($event['latitude'] && $event['longitude']): ?>
                        · GPS: <?= $event['latitude'] ?>, <?= $event['longitude'] ?>
                        <?php endif; ?>
                    </span>
                    <p>Statut associé : <?= Ui::badge($event['status'], 'info') ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <?= Ui::emptyState('Aucun historique', 'Aucun événement de suivi enregistré.') ?>
            <?php endif; ?>

            <!-- Ajouter un événement -->
            <?php if ($colis['status'] !== 'RETIRE'): ?>
            <details style="margin-top:2rem; padding-top: 1.5rem; border-top: 1px solid var(--finea-border);">
                <summary style="cursor:pointer; font-weight:700; color:var(--finea-navy);">+ Ajouter une étape de tracking</summary>
                <form method="POST" action="<?= View::url('colisage/colis/' . $colis['id'] . '/tracking') ?>" style="margin-top:1.5rem;">
                    <?= Csrf::input() ?>
                    <div class="rh-form-grid" style="margin-bottom: 1.5rem;">
                        <?= Form::input('step_name', 'Étape / Description *', '', ['placeholder' => 'Ex: Arrivée aéroport ABJ, Dédouanement...', 'required' => true]) ?>
                        <?= Form::select('status', 'Statut', [
                            ['value' => 'INFO', 'label' => 'Info'],
                            ['value' => 'EN_TRANSIT', 'label' => 'En transit'],
                            ['value' => 'ARRIVE', 'label' => 'Arrivé'],
                            ['value' => 'INCIDENT', 'label' => 'Incident'],
                        ], 'INFO') ?>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <?= Form::input('latitude', 'Latitude', '', ['placeholder' => '5.3544']) ?>
                            <?= Form::input('longitude', 'Longitude', '', ['placeholder' => '-4.0000']) ?>
                        </div>
                    </div>
                    <div class="rh-form-actions">
                        <?= Ui::button('Enregistrer l\'étape', null, ['type' => 'submit', 'variant' => 'primary']) ?>
                    </div>
                </form>
            </details>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
