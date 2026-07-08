<?php

declare(strict_types=1);

use App\Helpers\View;
use App\View\Components\Form;
use App\View\Components\Ui;

/** @var array<int, array<string, mixed>> $sites */

$siteOpts = [['value' => '', 'label' => '-- Sélectionner l\'agence --']];
foreach ($sites as $s) {
    $siteOpts[] = ['value' => (string) $s['id'], 'label' => $s['name']];
}

?>
<div class="finea-shell">
    <div class="finea-container">
        
        <?= Ui::pageHeader(
            'Planifier un Voyage de Groupage',
            'Enregistrement d\'un nouveau manifeste d\'expédition de fret.',
            [
                'eyebrow' => 'Nouveau Manifeste',
                'class' => 'rh-hero-white',
            ]
        ) ?>

        <form method="post" action="<?= View::url('colisage/groupage/enregistrer') ?>" class="finea-section-card" style="max-width: 800px; margin-top: 1.5rem;">
            
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">
                
                <?= Form::select('type_transport', [
                    ['value' => 'AÉRIEN', 'label' => '✈️ AÉRIEN (Fret aérien rapide)'],
                    ['value' => 'MARITIME', 'label' => '🚢 MARITIME (Fret maritime conteneur)'],
                    ['value' => 'TERRESTRE', 'label' => 'Terrestre (Route / Flotte livreurs)'],
                ], 'AÉRIEN', ['label' => 'Type de transport', 'required' => true]) ?>

                <div></div> <!-- Placeholder for layout alignment -->

                <?= Form::selectSearch('agence_depart_id', $siteOpts, '', ['label' => 'Agence de départ', 'required' => true]) ?>
                
                <?= Form::selectSearch('agence_arrivee_id', $siteOpts, '', ['label' => 'Agence de destination', 'required' => true]) ?>

                <?= Form::input('date_depart_prevue', [
                    'label' => 'Date & Heure de départ prévue',
                    'type' => 'datetime-local',
                    'required' => true
                ]) ?>

                <?= Form::input('date_arrivee_estimee', [
                    'label' => 'Date & Heure d\'arrivée estimée',
                    'type' => 'datetime-local',
                    'required' => true
                ]) ?>

            </div>

            <div style="margin-top: 2rem; display:flex; gap:1rem; justify-content:flex-end;">
                <?= Ui::button('Annuler', ['href' => 'colisage/groupage', 'variant' => 'secondary']) ?>
                <button type="submit" class="finea-button finea-button--accent">Créer le Manifeste</button>
            </div>

        </form>

    </div>
</div>
