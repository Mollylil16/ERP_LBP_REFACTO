<?php

declare(strict_types=1);

use App\Helpers\View;
use App\View\Components\Form;
use App\View\Components\Ui;
use App\View\Components\Rh;

/** @var array<string, mixed> $parcelsData */
/** @var array<string, mixed> $filters */
/** @var array<int, array<string, mixed>> $sites */

$parcels = $parcelsData['items'] ?? [];
$pagination = $parcelsData['pagination'] ?? null;

?>
<div class="finea-shell">
    <div class="finea-container">

        <?= Ui::pageHeader(
            'Autres Envois (Express)',
            'Suivi, saisie et édition des factures pour les envois express (DHL & Colis Rapide).',
            [
                'eyebrow' => 'Flux Express Internationaux',
                'class' => 'rh-hero-white',
                'actions' => [
                    Ui::button('Nouvel envoi express', [
                        'href' => 'colisage/autres/nouveau',
                        'variant' => 'accent'
                    ])
                ]
            ]
        ) ?>

        <!-- Filters Form -->
        <form method="get" action="<?= View::url('colisage/autres') ?>" class="rh-personnel-filters">
            <div class="rh-personnel-filter-grid">
                <?= Form::input('q', [
                    'label' => 'Recherche',
                    'value' => (string) ($filters['q'] ?? ''),
                    'placeholder' => 'N° Tracking, expéditeur, destinataire'
                ]) ?>

                <?= Form::selectSearch('type_expediteur', 'Transporteur / Service', [
                    ['value' => '', 'label' => 'Tous les services'],
                    ['value' => 'dhl', 'label' => '📦 DHL Express'],
                    ['value' => 'colis_rapide_export', 'label' => '⚡ Colis Rapide Export'],
                    ['value' => 'colis_rapide_import', 'label' => '⚡ Colis Rapide Import']
                ], $filters['type_expediteur'] ?? '') ?>

                <?= Form::selectSearch('trajet', 'Trajet (Colis Rapide)', [
                    ['value' => '', 'label' => 'Tous les trajets'],
                    ['value' => 'CIV_SEN', 'label' => 'CIV ➔ SEN'],
                    ['value' => 'SEN_CIV', 'label' => 'SEN ➔ CIV'],
                    ['value' => 'CIV_FR', 'label' => 'CIV ➔ FR'],
                    ['value' => 'FR_CIV', 'label' => 'FR ➔ CIV'],
                    ['value' => 'SEN_FR', 'label' => 'SEN ➔ FR'],
                    ['value' => 'FR_SEN', 'label' => 'FR ➔ SEN']
                ], $filters['trajet'] ?? '') ?>

                <?= Form::selectSearch('statut', 'Statut', [
                    ['value' => '', 'label' => 'Tous les statuts'],
                    ['value' => 'RÉCEPTIONNÉ', 'label' => 'Réceptionné'],
                    ['value' => 'EN_TRANSIT', 'label' => 'En transit'],
                    ['value' => 'ARRIVÉ', 'label' => 'Arrivé'],
                    ['value' => 'RETIRÉ', 'label' => 'Retiré']
                ], $filters['statut'] ?? '') ?>
            </div>

            <div class="rh-personnel-filter-actions">
                <button type="submit" class="rh-filter-btn rh-filter-btn--primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="rh-btn-icon"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    Rechercher
                </button>
                <a href="<?= View::url('colisage/autres') ?>" class="rh-filter-btn rh-filter-btn--reset">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" class="rh-btn-icon"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"></path></svg>
                    Réinitialiser
                </a>
            </div>
        </form>

        <!-- Parcels Table -->
        <div class="finea-section-card" style="margin-top: 1.5rem;">
            <div class="finea-table-wrapper">
                <table class="finea-table">
                    <thead>
                        <tr>
                            <th>N° Tracking</th>
                            <th>Service</th>
                            <th>Trajet</th>
                            <th>Expéditeur</th>
                            <th>Destinataire</th>
                            <th>Poids total</th>
                            <th>Montant</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($parcels)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 2rem; color: #64748b;">
                                    <strong>Aucun envoi express trouvé</strong><br>
                                    <small>Aucune fiche ne correspond aux critères sélectionnés.</small>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($parcels as $p): ?>
                                <tr>
                                    <td><strong><?= View::e($p['numero_tracking']) ?></strong></td>
                                    <td>
                                        <?php
                                        $srv = match($p['type_expediteur']) {
                                            'dhl' => '📦 DHL Express',
                                            'colis_rapide_export' => '⚡ Colis Rapide Export',
                                            'colis_rapide_import' => '⚡ Colis Rapide Import',
                                            default => $p['type_expediteur']
                                        };
                                        echo View::e($srv);
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($p['trajet']): ?>
                                            <span class="finea-badge finea-badge--info" style="font-size:0.75rem; text-transform:none;">
                                                <?= str_replace('_', ' ➔ ', $p['trajet']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color:#94a3b8;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= View::e($p['expediteur_name']) ?></td>
                                    <td><?= View::e($p['destinataire_name']) ?></td>
                                    <td><?= View::e((string) $p['poids_total']) ?> kg</td>
                                    <td><strong><?= number_format((float)$p['montant_total'], 0, ',', '.') ?> <?= View::e($p['devise']) ?></strong></td>
                                    <td>
                                        <?php
                                        $tone = match($p['statut']) {
                                            'RETIRÉ', 'LIVRÉ' => 'success',
                                            'RÉCEPTIONNÉ' => 'info',
                                            'EN_TRANSIT' => 'primary',
                                            'ARRIVÉ' => 'accent',
                                            default => 'neutral'
                                        };
                                        ?>
                                        <span class="finea-badge finea-badge--<?= $tone ?>"><?= View::e($p['statut']) ?></span>
                                    </td>
                                    <td>
                                        <?= Ui::button('Voir détails', [
                                            'href' => 'colisage/parcels/' . $p['id'],
                                            'variant' => 'primary',
                                            'class' => 'finea-button-sm'
                                        ]) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($pagination): ?>
            <?= Rh::paginationLinks($pagination) ?>
        <?php endif; ?>

    </div>
</div>
