<?php
/** @var array $clients */
/** @var array $agencies */
use App\Helpers\Csrf;
use App\Helpers\View;
use App\View\Components\Ui;
use App\View\Components\Form;

ob_start();
require __DIR__ . '/../_navigation.php';

// Préparation des options pour les selects
$clientOptions = array_map(fn($c) => [
    'value' => $c['id'],
    'label' => $c['name'] . ($c['phone'] ? ' — ' . $c['phone'] : '')
], $clients);

$agencyOptions = array_map(fn($a) => [
    'value' => $a['id'],
    'label' => $a['name'] . ($a['country'] ? ' (' . $a['country'] . ')' : '')
], $agencies);

$currencyOptions = [
    ['value' => 'XOF', 'label' => 'FCFA (XOF)'],
    ['value' => 'EUR', 'label' => 'Euros (EUR)']
];
?>

<?= Ui::pageHeader('Colisage', 'Nouveau Colis', [
    'actions' => Ui::button('Retour à la liste', 'colisage/colis', [
        'variant' => 'ghost',
        'class' => 'btn-sm',
        'html' => true,
    ])
]) ?>

<form action="<?= View::url('colisage/colis') ?>" method="POST" id="form-colis">
    <?= Csrf::input() ?>

    <!-- Section : Clients -->
    <div class="finea-section-card" style="margin-bottom:1.5rem;">
        <div class="finea-section-heading">
            <h2 class="finea-section-title">Expéditeur & Destinataire</h2>
        </div>
        <div class="form-grid-2">
            <?= Form::selectSearch('sender_id', 'Expéditeur *', $clientOptions, '', [
                'required' => true,
                'hint' => 'Client inexistant ? Vous pouvez le créer dans le CRM.'
            ]) ?>
            <?= Form::selectSearch('receiver_id', 'Destinataire *', $clientOptions, '', ['required' => true]) ?>
        </div>
    </div>

    <!-- Section : Trajet -->
    <div class="finea-section-card" style="margin-bottom:1.5rem;">
        <div class="finea-section-heading">
            <h2 class="finea-section-title">Trajet</h2>
        </div>
        <div class="form-grid-2">
            <?= Form::select('departure_agency_id', 'Agence de départ *', $agencyOptions, '', ['required' => true]) ?>
            <?= Form::select('arrival_agency_id', 'Agence d\'arrivée *', $agencyOptions, '', ['required' => true]) ?>
        </div>
    </div>

    <!-- Section : Informations colis -->
    <div class="finea-section-card" style="margin-bottom:1.5rem;">
        <div class="finea-section-heading">
            <h2 class="finea-section-title">Informations colis</h2>
        </div>
        <div class="form-grid-3">
            <?= Form::input('total_weight', 'Poids total (kg)', '0.00', ['type' => 'number', 'step' => '0.01', 'min' => '0']) ?>
            <?= Form::input('declared_value', 'Valeur déclarée (douane)', '0.00', ['type' => 'number', 'step' => '0.01', 'min' => '0']) ?>
            <?= Form::input('total_price', 'Prix facturé', '0.00', ['type' => 'number', 'step' => '0.01', 'min' => '0']) ?>
            <?= Form::select('currency', 'Devise', $currencyOptions, 'XOF') ?>
            <div style="grid-column: span 2;">
                <?= Form::input('description', 'Description courte du colis', '', ['placeholder' => 'Ex: Vêtements, électronique, alimentaire...']) ?>
            </div>
        </div>
        <div style="margin-top: 1rem;">
            <?= Form::textarea('notes', 'Notes internes', '', ['rows' => 2, 'placeholder' => 'Remarques, instructions particulières...']) ?>
        </div>
    </div>

    <!-- Section : Marchandises -->
    <div class="finea-section-card" style="margin-bottom:1.5rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
            <h2 class="finea-section-title" style="margin:0;">Détail des marchandises</h2>
            <?= Ui::button('Ajouter une ligne', null, [
                'variant' => 'outline',
                'class' => 'btn-sm',
                'id' => 'btn-add-ligne'
            ]) ?>
        </div>
        <table class="data-table" id="table-marchandises">
            <thead>
                <tr>
                    <th>Description de la marchandise *</th>
                    <th style="width:120px;">Quantité</th>
                    <th style="width:140px;">Poids unitaire (kg)</th>
                    <th style="width:50px;"></th>
                </tr>
            </thead>
            <tbody id="tbody-marchandises">
                <tr class="ligne-marchandise">
                    <td><input type="text" name="marchandise_description[]" class="finea-input" placeholder="Ex: Chaussures de sport..." required></td>
                    <td><input type="number" name="marchandise_quantity[]" class="finea-input" value="1" min="1"></td>
                    <td><input type="number" name="marchandise_weight[]" class="finea-input" value="0" step="0.01" min="0"></td>
                    <td>
                        <button type="button" class="finea-action-btn finea-action-btn--ghost btn-delete-ligne" title="Supprimer" style="padding: 4px 8px;">
                            <span class="material-icons" style="font-size: 1.2rem;">delete_outline</span>
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="form-actions" style="margin-top: 2rem; display: flex; gap: 1rem;">
        <?= Ui::button('Créer le colis & générer le tracking', null, [
            'type' => 'submit',
            'variant' => 'primary',
            'class' => 'btn-lg'
        ]) ?>
        <?= Ui::button('Annuler', 'colisage/colis', [
            'variant' => 'ghost',
            'class' => 'btn-lg'
        ]) ?>
    </div>
</form>

<script>
document.getElementById('btn-add-ligne').addEventListener('click', function() {
    const tbody = document.getElementById('tbody-marchandises');
    const tmpl = tbody.querySelector('.ligne-marchandise').cloneNode(true);
    tmpl.querySelectorAll('input').forEach(i => {
        if (i.type === 'number') i.value = i.name.includes('quantity') ? '1' : '0';
        else i.value = '';
    });
    tbody.appendChild(tmpl);
});

document.getElementById('tbody-marchandises').addEventListener('click', function(e) {
    const btn = e.target.closest('.btn-delete-ligne');
    if (!btn) return;
    const rows = document.querySelectorAll('.ligne-marchandise');
    if (rows.length > 1) btn.closest('tr').remove();
});
</script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
