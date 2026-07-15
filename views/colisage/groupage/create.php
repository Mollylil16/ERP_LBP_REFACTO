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

// Default departure = now (rounded to next quarter-hour)
$now = new DateTimeImmutable('now', new DateTimeZone('Africa/Abidjan'));
$minute = (int) $now->format('i');
$roundUp = (15 - ($minute % 15)) % 15;
$defaultDepart = $now->modify("+{$roundUp} minutes")->format('Y-m-d\TH:i');

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
                    'required' => true,
                    'value' => $defaultDepart,
                ]) ?>

                <?= Form::input('date_arrivee_estimee', [
                    'label' => 'Date & Heure d\'arrivée estimée',
                    'type' => 'datetime-local',
                    'required' => true,
                    'hint' => 'Calculée automatiquement selon le type de transport.',
                ]) ?>

            </div>

            <div style="margin-top: 2rem; display:flex; gap:1rem; justify-content:flex-end;">
                <?= Ui::button('Annuler', ['href' => 'colisage/groupage', 'variant' => 'secondary']) ?>
                <?= Ui::button('Créer le Manifeste', ['type' => 'submit', 'variant' => 'accent']) ?>
            </div>

        </form>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var departInput   = document.querySelector('input[name="date_depart_prevue"]');
    var arriveeInput  = document.querySelector('input[name="date_arrivee_estimee"]');
    var transportSel  = document.querySelector('select[name="type_transport"]');

    if (!departInput || !arriveeInput || !transportSel) return;

    // Delay map in days per transport type
    var delayDays = {
        'AÉRIEN':    3,
        'MARITIME':  30,
        'TERRESTRE': 7
    };

    function computeArrivee() {
        var departValue = departInput.value;
        if (!departValue) return;

        var transport = transportSel.value || 'AÉRIEN';
        var days = delayDays[transport] || 3;

        var depart = new Date(departValue);
        if (isNaN(depart.getTime())) return;

        depart.setDate(depart.getDate() + days);

        // Format as yyyy-MM-ddTHH:mm
        var pad = function(n) { return n < 10 ? '0' + n : '' + n; };
        arriveeInput.value = depart.getFullYear() + '-'
            + pad(depart.getMonth() + 1) + '-'
            + pad(depart.getDate()) + 'T'
            + pad(depart.getHours()) + ':'
            + pad(depart.getMinutes());
    }

    departInput.addEventListener('change', computeArrivee);
    transportSel.addEventListener('change', computeArrivee);

    // Auto-compute on page load if departure is pre-filled
    computeArrivee();
});
</script>

