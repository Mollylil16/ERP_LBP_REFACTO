<?php

declare(strict_types=1);

use App\Helpers\View;
use App\View\Components\Ui;

/** @var array<int, array<string, mixed>> $expeditions */

?>
<div class="finea-shell">
    <div class="finea-container">
        
        <?= Ui::pageHeader(
            'Groupage & Manifestes',
            'Planification des voyages de groupage et affectation des colis aux conteneurs ou palettes.',
            [
                'eyebrow' => 'Logistique & Fret',
                'class' => 'rh-hero-white',
                'actions' => [
                    Ui::button('Planifier un voyage', [
                        'href' => 'colisage/groupage/nouveau',
                        'variant' => 'accent'
                    ])
                ]
            ]
        ) ?>

        <div class="finea-section-card" style="margin-top: 1.5rem;">
            <div class="finea-table-wrapper">
                <table class="finea-table">
                    <thead>
                        <tr>
                            <th>Référence</th>
                            <th>Type Transport</th>
                            <th>Agence Départ</th>
                            <th>Agence Arrivée</th>
                            <th>Départ Prévu</th>
                            <th>Arrivée Estimée</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($expeditions)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 2rem; color: #64748b;">
                                    <strong>Aucune expédition planifiée</strong><br>
                                    <small>Commencez par planifier un nouveau voyage de groupage.</small>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($expeditions as $e): ?>
                                <tr>
                                    <td><strong><?= View::e($e['reference']) ?></strong></td>
                                    <td>
                                        <?php
                                        $icon = match($e['type_transport']) {
                                            'AÉRIEN' => '✈️ Aérien',
                                            'MARITIME' => '🚢 Maritime',
                                            'TERRESTRE' => '🚛 Terrestre',
                                            default => $e['type_transport']
                                        };
                                        echo View::e($icon);
                                        ?>
                                    </td>
                                    <td><?= View::e($e['agence_depart_name']) ?></td>
                                    <td><?= View::e($e['agence_arrivee_name']) ?></td>
                                    <td><?= View::e($e['date_depart_prevue'] ?? 'Non planifiée') ?></td>
                                    <td><?= View::e($e['date_arrivee_estimee'] ?? 'Non planifiée') ?></td>
                                    <td>
                                        <?php
                                        $tone = match($e['statut']) {
                                            'ARRIVÉ' => 'success',
                                            'EN_TRANSIT' => 'primary',
                                            'BROUILLON' => 'warning',
                                            default => 'neutral'
                                        };
                                        ?>
                                        <span class="finea-badge finea-badge--<?= $tone ?>"><?= View::e($e['statut']) ?></span>
                                    </td>
                                    <td>
                                        <?= Ui::button('Gérer groupage', [
                                            'href' => 'colisage/groupage/' . $e['id'],
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

    </div>
</div>
