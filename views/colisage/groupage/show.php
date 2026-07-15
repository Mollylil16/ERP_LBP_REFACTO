<?php

declare(strict_types=1);

use App\Helpers\View;
use App\View\Components\Form;
use App\View\Components\Ui;
use App\View\Components\Colisage;

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
                    Ui::badge($exp['statut'], $badgeTone, ['class' => 'finea-badge--large']),
                    Ui::button('Retour à la liste', [
                        'href' => 'colisage/groupage',
                        'variant' => 'secondary'
                    ])
                ]
            ]
        ) ?>

        <div style="display:grid; grid-template-columns: 1fr; gap: 1.5rem;">
            
            <?= Colisage::groupageDetail($exp) ?>

            <!-- Add parcels (Only if BROUILLON) -->
            <?php if ($exp['statut'] === 'BROUILLON'): ?>
                <?php
                $addForm = '';
                if (empty($availableParcels)) {
                    $addForm = '<p style="color: #64748b; font-size: 0.95rem;">Aucun colis en agence n\'est actuellement en attente d\'expédition pour ce trajet.</p>';
                } else {
                    $addForm = '<form method="post" action="' . View::url('colisage/groupage/' . $exp['id'] . '/colis') . '" style="display:flex; align-items:flex-end; gap:1rem;" class="js-protect-form">'
                        . '<div style="flex-grow:1;">'
                        . Form::selectSearch('colis_id', $parcelOpts, '', ['label' => 'Colis disponible à l\'agence de départ (' . View::e($exp['agence_depart_name']) . ')'])
                        . '</div>'
                        . Ui::button('Affecter au groupage', ['type' => 'submit', 'variant' => 'primary', 'style' => 'height: 42px;', 'data-label' => 'Affecter au groupage'])
                        . '</form>';
                }
                ?>
                <?= Ui::section('Scanner & Charger des colis dans ce manifeste', $addForm) ?>
            <?php endif; ?>

            <?= Colisage::groupageParcelsTable($assignedParcels) ?>

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

