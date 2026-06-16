<?php
/** @var \App\Support\ViewBag $viewData */

use App\View\Components\ContractCard;
use App\View\Components\Ui;

$contract = isset($viewData) ? $viewData->array('contract') : [];
/** @var array<int,array<string,mixed>> $allowances */
$allowances = is_array($contract['allowances'] ?? null) ? $contract['allowances'] : [];
$totalAllowances = array_reduce($allowances, static fn(float $sum, array $row): float => $sum + (float) ($row['amount'] ?? 0), 0.0);

ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Détail du contrat',
            (string) ($contract['employee_name'] ?? 'Collaborateur'),
            'Type : ' . (string) ($contract['contract_type'] ?? 'Non renseigné'),
            '<div class="finea-header-actions">'
                . Ui::button('Retour', ['href' => 'rh/contrats', 'variant' => 'secondary'])
                . Ui::button('Modifier', ['href' => 'rh/contrats/' . (int) ($contract['id'] ?? 0) . '/modifier', 'variant' => 'accent'])
                . '</div>',
            ['class' => 'rh-hero']
        ) ?>

        <div class="rh-contract-detail-grid">
            <?= ContractCard::render($contract) ?>

            <section class="finea-section-card">
                <p class="rh-eyebrow">Rémunération</p>
                <h2 class="finea-section-title">Salaire mensuel</h2>
                <div class="rh-contract-pay-panel">
                    <small>Salaire de base</small>
                    <strong><?= number_format((float) ($contract['base_salary'] ?? 0), 0, ',', ' ') ?> FCFA</strong>
                    <span>Indemnités fixes : <?= number_format($totalAllowances, 0, ',', ' ') ?> FCFA</span>
                </div>
            </section>
        </div>

        <section class="finea-section-card rh-recent-section">
            <div class="rh-section-heading">
                <div>
                    <p class="rh-eyebrow">Avantages</p>
                    <h2 class="finea-section-title">Indemnités fixes</h2>
                </div>
                <?= Ui::badge(number_format($totalAllowances, 0, ',', ' ') . ' FCFA', 'info') ?>
            </div>
            <?php if ($allowances === []): ?>
                <?= Ui::emptyState('Aucune indemnité', "Ce contrat n'a aucune indemnité paramétrée.") ?>
            <?php else: ?>
                <div class="rh-allowance-summary-grid">
                    <?php foreach ($allowances as $allowance): ?>
                        <article>
                            <strong><?= htmlspecialchars((string) ($allowance['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                            <span><?= number_format((float) ($allowance['amount'] ?? 0), 0, ',', ' ') ?> FCFA</span>
                            <?= Ui::badge(!empty($allowance['is_taxable']) ? 'Imposable ITS' : 'Non imposable', !empty($allowance['is_taxable']) ? 'warning' : 'success') ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php $content = ob_get_clean(); require BASE_PATH . '/views/layouts/module.php';
