<?php
/** @var array $prestataires */
/** @var array $filters */
use App\Helpers\View;
use App\View\Components\Ui;
use App\View\Components\Form;

ob_start();
require __DIR__ . '/../_navigation.php';
?>

<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Logistique Interne',
            'Prestataires & Partenaires',
            'Gérez les transporteurs, compagnies, douanes et prestataires logistiques.',
            Ui::button('Nouveau Prestataire', ['href' => 'logistique/prestataires/nouveau', 'variant' => 'accent']),
            ['class' => 'rh-hero']
        ) ?>

        <form method="GET" action="<?= View::url('logistique/prestataires') ?>" class="finea-section-card" style="margin-top: 24px;">
            <div class="rh-form-grid" style="grid-template-columns: 2fr 1.5fr auto; gap: 15px; align-items: flex-end;">
                <?= Form::input('search', 'Recherche (Nom)', $filters['search'] ?? '', [
                    'placeholder' => 'Chercher un prestataire...',
                    'class' => 'form-input'
                ]) ?>

                <?= Form::select('type', 'Type de partenaire', [
                    ['value' => '', 'label' => 'Tous les types'],
                    ['value' => 'DOUANE', 'label' => 'Douane'],
                    ['value' => 'COMPAGNIE_AERIENNE', 'label' => 'Compagnie Aérienne'],
                    ['value' => 'COMPAGNIE_MARITIME', 'label' => 'Compagnie Maritime'],
                    ['value' => 'TRANSPORT_TERRESTRE', 'label' => 'Transport Terrestre'],
                    ['value' => 'AUTRE', 'label' => 'Autre']
                ], $filters['type'] ?? '') ?>

                <?= Ui::button('Filtrer', ['type' => 'submit', 'variant' => 'secondary']) ?>
            </div>
        </form>

        <section class="finea-table-wrap" style="margin-top: 24px;">
            <table class="finea-table">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Type</th>
                        <th>Contact</th>
                        <th>Téléphone</th>
                        <th>Nb Factures</th>
                        <th>Encours (Impayés)</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($prestataires)): ?>
                        <tr>
                            <td colspan="7">
                                <?= Ui::emptyState('Aucun prestataire trouvé', 'Commencez par ajouter un nouveau prestataire logistique.') ?>
                            </td>
                        </tr>
                    <?php else: foreach ($prestataires as $p): ?>
                        <tr>
                            <td><strong><?= View::e($p['name']) ?></strong></td>
                            <td><?= Ui::badge(View::e($p['type']), 'info') ?></td>
                            <td>
                                <?= View::e($p['contact_name'] ?? '—') ?><br>
                                <small style="color:var(--finea-muted);"><?= View::e($p['email'] ?? '—') ?></small>
                            </td>
                            <td><?= View::e($p['phone'] ?? '—') ?></td>
                            <td><?= (int)$p['nb_factures'] ?></td>
                            <td>
                                <?php if ((float)$p['encours'] > 0): ?>
                                    <strong style="color:var(--finea-danger); font-weight:800;"><?= number_format((float)$p['encours'], 0, ',', ' ') ?> XOF</strong>
                                <?php else: ?>
                                    <span style="color:var(--finea-success); font-weight:800;">0 XOF</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $p['is_active'] ? Ui::badge('Actif', 'success') : Ui::badge('Inactif', 'neutral') ?>
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
