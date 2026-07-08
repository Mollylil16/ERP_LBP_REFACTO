<?php

declare(strict_types=1);

use App\Helpers\View;
use App\View\Components\Form;
use App\View\Components\Ui;

/** @var array<string, mixed> $exp */
/** @var array<int, array<string, mixed>> $availableParcels */

$badgeTone = match($exp['statut']) {
    'ARRIVÉ' => 'success',
    'EN_TRANSIT' => 'primary',
    'BROUILLON' => 'warning',
    default => 'neutral'
};

$assignedParcels = $exp['parcels'] ?? [];

$parcelOpts = [['value' => '', 'label' => '-- Sélectionner un colis à ajouter --']];
foreach ($availableParcels as $ap) {
    $parcelOpts[] = [
        'value' => (string) $ap['id'],
        'label' => $ap['numero_tracking'] . ' - ' . $ap['expediteur_name'] . ' (' . $ap['poids_total'] . ' kg)'
    ];
}

?>
<div class="finea-shell">
    <div class="finea-container">
        
        <?= Ui::pageHeader(
            'Manifeste ' . $exp['reference'],
            'Gestion du groupage et du voyage d\'expédition.',
            [
                'eyebrow' => 'Groupage Fret',
                'class' => 'rh-hero-white',
                'actions' => [
                    '<span class="finea-badge finea-badge--' . $badgeTone . '" style="padding: 0.5rem 1rem; font-size: 0.95rem; font-weight: 600;">' . View::e($exp['statut']) . '</span>',
                    Ui::button('Retour à la liste', [
                        'href' => 'colisage/groupage',
                        'variant' => 'secondary'
                    ])
                ]
            ]
        ) ?>

        <div style="display:grid; grid-template-columns: 1fr; gap: 1.5rem;">
            
            <!-- Detail Card -->
            <section class="finea-section-card">
                <div class="finea-section-heading">
                    <h2 class="finea-section-title">Informations du Voyage</h2>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div>
                        <p style="margin-bottom: 0.5rem;"><strong>Référence Voyage :</strong> <?= View::e($exp['reference']) ?></p>
                        <p style="margin-bottom: 0.5rem;"><strong>Type de transport :</strong> 
                            <?php
                            $icon = match($exp['type_transport']) {
                                'AÉRIEN' => '✈️ AÉRIEN',
                                'MARITIME' => '🚢 MARITIME',
                                'TERRESTRE' => '🚛 TERRESTRE',
                                default => $exp['type_transport']
                            };
                            echo View::e($icon);
                            ?>
                        </p>
                        <p style="margin-bottom: 0.5rem;"><strong>Agence de départ :</strong> <?= View::e($exp['agence_depart_name']) ?></p>
                        <p style="margin-bottom: 0.5rem;"><strong>Agence de destination :</strong> <?= View::e($exp['agence_arrivee_name']) ?></p>
                    </div>
                    <div>
                        <p style="margin-bottom: 0.5rem;"><strong>Date de départ prévue :</strong> <?= View::e($exp['date_depart_prevue']) ?></p>
                        <p style="margin-bottom: 0.5rem;"><strong>Date d\'arrivée estimée :</strong> <?= View::e($exp['date_arrivee_estimee']) ?></p>
                        <p style="margin-bottom: 0.5rem;"><strong>Date de création :</strong> <?= View::e($exp['created_at']) ?></p>
                        <p style="margin-bottom: 0.5rem;"><strong>Nombre de colis chargés :</strong> <?= count($assignedParcels) ?></p>
                    </div>
                </div>

                <!-- Action Workflow boutons -->
                <div style="margin-top: 1.5rem; border-top: 1px solid rgba(0,0,0,0.05); padding-top: 1.5rem; display:flex; justify-content:flex-end; gap:1rem;">
                <?php if ($exp['statut'] === 'BROUILLON'): ?>
                        <form method="post" action="<?= View::url('colisage/groupage/' . $exp['id'] . '/demarrer') ?>" class="js-protect-form">
                            <button type="submit" class="finea-button finea-button--accent" <?= empty($assignedParcels) ? 'disabled' : '' ?> data-label="✈️ Démarrer l'expédition (Départ du voyage)">
                                ✈️ Démarrer l'expédition (Départ du voyage)
                            </button>
                        </form>
                    <?php elseif ($exp['statut'] === 'EN_TRANSIT'): ?>
                        <form method="post" action="<?= View::url('colisage/groupage/' . $exp['id'] . '/arriver') ?>" class="js-protect-form">
                            <button type="submit" class="finea-button finea-button--success" data-label="🏁 Marquer comme Arrivé à Destination (Dégroupage)">
                                🏁 Marquer comme Arrivé à Destination (Dégroupage)
                            </button>
                        </form>
                    <?php else: ?>
                        <div style="color:#16a34a; font-weight:600; display:flex; align-items:center; gap:0.5rem;">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            Voyage Clôturé - Colis arrivés à bon port
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Add parcels (Only if BROUILLON) -->
            <?php if ($exp['statut'] === 'BROUILLON'): ?>
                <section class="finea-section-card">
                    <div class="finea-section-heading">
                        <h2 class="finea-section-title">Scanner & Charger des colis dans ce manifeste</h2>
                    </div>
                    <?php if (empty($availableParcels)): ?>
                        <p style="color: #64748b; font-size: 0.95rem;">Aucun colis en agence n'est actuellement en attente d'expédition pour ce trajet.</p>
                    <?php else: ?>
                        <form method="post" action="<?= View::url('colisage/groupage/' . $exp['id'] . '/colis') ?>" style="display:flex; align-items:flex-end; gap:1rem;" class="js-protect-form">
                            <div style="flex-grow:1;">
                                <?= Form::selectSearch('colis_id', $parcelOpts, '', ['label' => 'Colis disponible à l\'agence de départ (' . View::e($exp['agence_depart_name']) . ')']) ?>
                            </div>
                            <button type="submit" class="finea-button finea-button--primary" style="height: 42px;" data-label="Affecter au groupage">Affecter au groupage</button>
                        </form>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <!-- Assigned Parcels Table -->
            <section class="finea-section-card">
                <div class="finea-section-heading">
                    <h2 class="finea-section-title">Contenu du Manifeste (Colis groupés)</h2>
                </div>
                <div class="finea-table-wrapper">
                    <table class="finea-table">
                        <thead>
                            <tr>
                                <th>N° Tracking</th>
                                <th>Expéditeur</th>
                                <th>Destinataire</th>
                                <th>Poids</th>
                                <th>Valeur Déclarée</th>
                                <th>Statut Colis</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($assignedParcels)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 2rem; color: #64748b;">
                                        Aucun colis chargé dans ce manifeste.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($assignedParcels as $ap): ?>
                                    <tr>
                                        <td><strong><?= View::e($ap['numero_tracking']) ?></strong></td>
                                        <td><?= View::e($ap['expediteur_name']) ?></td>
                                        <td><?= View::e($ap['destinataire_name']) ?></td>
                                        <td><?= View::e((string) $ap['poids_total']) ?> kg</td>
                                        <td><?= View::e(number_format((float) $ap['valeur_declaree'], 0, ',', ' ')) ?> <?= View::e($ap['devise']) ?></td>
                                        <td>
                                            <?php
                                            $colisBadgeTone = match($ap['statut']) {
                                                'RETIRÉ', 'LIVRÉ' => 'success',
                                                'RÉCEPTIONNÉ' => 'info',
                                                'EN_PRÉPARATION' => 'warning',
                                                'EN_TRANSIT' => 'primary',
                                                default => 'neutral'
                                            };
                                            ?>
                                            <span class="finea-badge finea-badge--<?= $colisBadgeTone ?>"><?= View::e($ap['statut']) ?></span>
                                        </td>
                                        <td>
                                            <?= Ui::button('Voir colis', [
                                                'href' => 'colisage/parcels/' . $ap['id'],
                                                'variant' => 'secondary',
                                                'class' => 'finea-button-sm'
                                            ]) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        </div>
    </div>
</div>

<style>
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.js-protect-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const btn = form.querySelector('button[type="submit"]');
            if (btn) {
                if (btn.dataset.submitted === 'true') { e.preventDefault(); return; }
                btn.dataset.submitted = 'true';
                btn.disabled = true;
                btn.innerHTML = '<span style="display:inline-flex;align-items:center;gap:0.5rem;"><svg width="16" height="16" viewBox="0 0 24 24" style="animation:spin 1s linear infinite;"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31 31"/></svg> Traitement en cours...</span>';
            }
        });
    });
});
</script>

