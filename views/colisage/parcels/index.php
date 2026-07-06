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
            'Gestion des Colis',
            'Saisie, suivi et groupage des colis des clients.',
            [
                'eyebrow' => 'Opérations de Colisage',
                'class' => 'rh-hero-white',
                'actions' => [
                    Ui::button('Enregistrer un colis', [
                        'href' => 'colisage/parcels/nouveau',
                        'variant' => 'accent'
                    ])
                ]
            ]
        ) ?>

        <!-- Filters Form -->
        <form method="get" action="<?= View::url('colisage/parcels') ?>" class="rh-personnel-filters">
            <div class="rh-personnel-filter-grid">
                <?= Form::input('q', [
                    'label' => 'Recherche',
                    'value' => (string) ($filters['q'] ?? ''),
                    'placeholder' => 'N° Tracking, expéditeur, destinataire'
                ]) ?>

                <?= Form::selectSearch('statut', 'Statut', [
                    ['value' => '', 'label' => 'Tous les statuts'],
                    ['value' => 'RÉCEPTIONNÉ', 'label' => 'Réceptionné'],
                    ['value' => 'EN_PRÉPARATION', 'label' => 'En préparation'],
                    ['value' => 'EN_TRANSIT', 'label' => 'En transit'],
                    ['value' => 'ARRIVÉ', 'label' => 'Arrivé'],
                    ['value' => 'LIVRÉ', 'label' => 'Livré'],
                    ['value' => 'RETIRÉ', 'label' => 'Retiré']
                ], $filters['statut'] ?? '') ?>

                <?= Form::selectSearch('type_expediteur', 'Catégorie Fret', [
                    ['value' => '', 'label' => 'Toutes les catégories'],
                    ['value' => 'export_aerien', 'label' => '✈️ Export Aérien'],
                    ['value' => 'export_maritime', 'label' => '🚢 Export Maritime'],
                    ['value' => 'import_aerien', 'label' => '✈️ Import Aérien'],
                    ['value' => 'import_maritime', 'label' => '🚢 Import Maritime']
                ], $filters['type_expediteur'] ?? '') ?>
            </div>

            <div class="rh-personnel-filter-actions">
                <button type="submit" class="rh-filter-btn rh-filter-btn--primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="rh-btn-icon"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    Rechercher
                </button>
                <a href="<?= View::url('colisage/parcels') ?>" class="rh-filter-btn rh-filter-btn--reset">
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
                            <th>Expéditeur</th>
                            <th>Destinataire</th>
                            <th>Catégorie</th>
                            <th>Poids</th>
                            <th>Valeur Décl.</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($parcels)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 2rem; color: #64748b;">
                                    <strong>Aucun colis trouvé</strong><br>
                                    <small>Aucune fiche ne correspond aux critères sélectionnés.</small>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($parcels as $p): ?>
                                <tr>
                                    <td><strong><?= View::e($p['numero_tracking']) ?></strong></td>
                                    <td><?= View::e($p['expediteur_name']) ?></td>
                                    <td><?= View::e($p['destinataire_name']) ?></td>
                                    <td>
                                        <small>
                                            <?php
                                            $catLabel = match($p['type_expediteur']) {
                                                'export_aerien' => '✈️ Export Aérien',
                                                'export_maritime' => '🚢 Export Maritime',
                                                'import_aerien' => '✈️ Import Aérien',
                                                'import_maritime' => '🚢 Import Maritime',
                                                default => $p['type_expediteur']
                                            };
                                            echo View::e($catLabel);
                                            ?>
                                        </small>
                                    </td>
                                    <td><?= View::e((string) $p['poids_total']) ?> kg</td>
                                    <td><?= View::e(number_format((float) $p['valeur_declaree'], 0, ',', ' ')) ?> <?= View::e($p['devise']) ?></td>
                                    <td>
                                        <?php
                                        $tone = match($p['statut']) {
                                            'RETIRÉ', 'LIVRÉ' => 'success',
                                            'RÉCEPTIONNÉ' => 'info',
                                            'EN_PRÉPARATION' => 'warning',
                                            'EN_TRANSIT' => 'primary',
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
