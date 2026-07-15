<?php

declare(strict_types=1);

use App\Helpers\View;
use App\View\Components\Form;
use App\View\Components\Ui;
use App\View\Components\Colisage;

/** @var array<int, array<string, mixed>> $sites */
/** @var array<int, array<string, mixed>> $clients */
/** @var array<int, array<string, mixed>> $products */
/** @var float $eur_to_xof_rate */

$clientOpts = [['value' => '', 'label' => '-- Choisir un client existant --']];
foreach ($clients as $c) {
    $clientOpts[] = ['value' => (string) $c['id'], 'label' => $c['name'] . ' (' . $c['phone'] . ')'];
}

$siteOpts = [['value' => '', 'label' => '-- Sélectionner l\'agence --']];
foreach ($sites as $s) {
    $siteOpts[] = ['value' => (string) $s['id'], 'label' => $s['name']];
}

$prodOptions = [['value' => '', 'label' => '-- Sélectionner un produit --']];
foreach ($products as $p) {
    $prodOptions[] = [
        'value' => (string) $p['id'],
        'label' => $p['nom'] . ' (' . number_format((float) $p['prix_unitaire'], 0, ',', ' ') . ' XOF/' . $p['unite'] . ')'
    ];
}

?>
<div class="finea-shell">
    <div class="finea-container">

        <?= Ui::pageHeader(
            'Enregistrer un Envoi Express',
            'Saisie d\'une fiche de colisage pour DHL Express ou Colis Rapide inter-pays.',
            [
                'eyebrow' => 'Nouveau Colis Express — Facture',
                'class' => 'rh-hero-white',
                'actions' => [
                    Ui::button('Retour à la liste', ['href' => 'colisage/autres', 'variant' => 'secondary'])
                ],
            ]
        ) ?>

        <form method="post" action="<?= View::url('colisage/autres/enregistrer') ?>">

            <!-- ÉTAPE 1 : Expéditeur & Destinataire -->
            <div class="rh-form-step-card">
                <div class="rh-step-badge">ÉTAPE 1</div>
                <h3 class="rh-step-title">Expéditeur & Destinataire</h3>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:2rem;">
                    <!-- Expéditeur -->
                    <div>
                        <h4 style="margin-bottom:0.8rem; color:#1e40af;">EXPÉDITEUR</h4>
                        <?= Form::selectSearch('expediteur_id', $clientOpts, '', ['label' => 'Client existant']) ?>
                        <div style="margin-top:1rem; padding:1rem; background:rgba(0,0,0,0.015); border-radius:8px; border:1px solid rgba(0,0,0,0.05);">
                            <small style="color:#64748b;">Ou créer un nouvel expéditeur :</small>
                            <div class="rh-form-grid-3" style="margin-top:0.5rem;">
                                <?= Form::input('expediteur_name', ['label' => 'Nom complet', 'placeholder' => 'Ex: AICHA OUATTARA']) ?>
                                <?= Form::input('expediteur_phone', ['label' => 'Tél. Exp.', 'placeholder' => 'Ex: 0789665421']) ?>
                                <?= Form::input('expediteur_email', ['label' => 'E-mail']) ?>
                                <?= Form::input('expediteur_address', ['label' => 'Adresse']) ?>
                            </div>
                        </div>
                    </div>
                    <!-- Destinataire -->
                    <div>
                        <h4 style="margin-bottom:0.8rem; color:#1e40af;">DESTINATAIRE</h4>
                        <?= Form::selectSearch('destinataire_id', $clientOpts, '', ['label' => 'Client existant']) ?>
                        <div style="margin-top:1rem; padding:1rem; background:rgba(0,0,0,0.015); border-radius:8px; border:1px solid rgba(0,0,0,0.05);">
                            <small style="color:#64748b;">Ou créer un nouveau destinataire :</small>
                            <div class="rh-form-grid-3" style="margin-top:0.5rem;">
                                <?= Form::input('destinataire_name', ['label' => 'Nom complet', 'placeholder' => 'Ex: KOUAO YVES']) ?>
                                <?= Form::input('destinataire_phone', ['label' => 'Tél. Dest.', 'placeholder' => 'Ex: +33 178255886']) ?>
                                <?= Form::input('destinataire_email', ['label' => 'E-mail']) ?>
                                <?= Form::input('destinataire_address', ['label' => 'Adresse']) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ÉTAPE 2 : Service Express et Trajet -->
            <div class="rh-form-step-card">
                <div class="rh-step-badge">ÉTAPE 2</div>
                <h3 class="rh-step-title">Service Express & Trajet</h3>
                <div class="rh-form-grid-3">
                    <?= Form::select('type_expediteur', [
                        ['value' => 'dhl', 'label' => ' DHL Express'],
                        ['value' => 'colis_rapide_export', 'label' => ' Colis Rapide Export'],
                        ['value' => 'colis_rapide_import', 'label' => ' Colis Rapide Import'],
                    ], 'dhl', ['label' => 'Service de transport', 'required' => true, 'id' => 'service_selector']) ?>

                    <!-- Trajet selector (hidden by default unless Colis Rapide is selected) -->
                    <div id="trajet_container" style="display:none;">
                        <?= Form::select('trajet', [
                            ['value' => '', 'label' => '-- Sélectionner le trajet --'],
                            ['value' => 'CIV_SEN', 'label' => 'CIV ➔ SEN'],
                            ['value' => 'SEN_CIV', 'label' => 'SEN ➔ CIV'],
                            ['value' => 'CIV_FR', 'label' => 'CIV ➔ FR'],
                            ['value' => 'FR_CIV', 'label' => 'FR ➔ CIV'],
                            ['value' => 'SEN_FR', 'label' => 'SEN ➔ FR'],
                            ['value' => 'FR_SEN', 'label' => 'FR ➔ SEN'],
                        ], '', ['label' => 'Trajet inter-pays']) ?>
                    </div>

                    <?= Form::selectSearch('agence_depart_id', $siteOpts, '', ['label' => 'Agence de départ', 'required' => true]) ?>

                    <?= Form::selectSearch('agence_arrivee_id', $siteOpts, '', ['label' => 'DESTINATION (agence d\'arrivée)', 'required' => true]) ?>

                    <?= Form::input('nombre_colis', ['label' => 'Nombre total de colis', 'type' => 'number', 'min' => 1, 'value' => '1', 'required' => true]) ?>

                    <?= Form::input('poids_total', ['label' => 'Poids total (kg)', 'type' => 'number', 'step' => '0.01', 'required' => true]) ?>

                    <?= Form::select('devise', [
                        ['value' => 'XOF', 'label' => 'Franc CFA (XOF / FCFA)'],
                        ['value' => 'EUR', 'label' => 'Euro (EUR)'],
                        ['value' => 'USD', 'label' => 'US Dollar (USD)'],
                    ], 'XOF', ['label' => 'Devise']) ?>

                    <?= Form::input('valeur_declaree', ['label' => 'Valeur déclarée (assurance/douane)', 'type' => 'number', 'step' => '1']) ?>
                </div>
            </div>

            <!-- ÉTAPE 3 : Marchandises (Format tableau facture LBP avec composants) -->
            <div class="rh-form-step-card">
                <div class="rh-step-badge">ÉTAPE 3</div>
                <h3 class="rh-step-title">Détail des marchandises</h3>
                <p style="color:#64748b; font-size:0.9rem; margin-bottom:1rem;">Conforme au format facture LB-CI : N°, Nbre Colis, Description, Emballage, Qté Emb., Poids (kg), Prix/Kg, Total</p>
                <?= Colisage::marchandisesInputTable($prodOptions) ?>
            </div>

            <!-- Actions -->
            <div style="margin-top:2rem; display:flex; gap:1rem; justify-content:flex-end; padding-bottom:3rem;">
                <?= Ui::button('Annuler', ['href' => 'colisage/autres', 'variant' => 'secondary']) ?>
                <?= Ui::button('Enregistrer & Générer la facture', ['type' => 'submit', 'variant' => 'accent', 'style' => 'font-size:1rem; padding:0.8rem 2rem;']) ?>
            </div>

        </form>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const clientsData = <?= json_encode($clients) ?>;
    const productsData = <?= json_encode($products) ?>;
    const rows = document.querySelectorAll('.finea-table tbody tr');
    const sousTotalEl = document.getElementById('sous_total');
    const totalFcfaEl = document.getElementById('montant_total_fcfa');
    const totalEurEl = document.getElementById('montant_total_eur');
    const inputValeurDeclaree = document.querySelector('input[name="valeur_declaree"]');
    
    // Express Service Toggle Logic
    const serviceSelector = document.getElementById('service_selector');
    const trajetContainer = document.getElementById('trajet_container');
    const trajetSelect = document.querySelector('select[name="trajet"]');

    function toggleTrajet() {
        const val = serviceSelector.value;
        if (val === 'colis_rapide_export' || val === 'colis_rapide_import') {
            trajetContainer.style.display = 'block';
            trajetSelect.required = true;
        } else {
            trajetContainer.style.display = 'none';
            trajetSelect.required = false;
            trajetSelect.value = '';
        }
    }

    if (serviceSelector) {
        serviceSelector.addEventListener('change', toggleTrajet);
        toggleTrajet();
    }

    // 1. Client Auto-Fill Logic
    const expSelect = document.querySelector('select[name="expediteur_id"]');
    const destSelect = document.querySelector('select[name="destinataire_id"]');

    if (expSelect) {
        expSelect.addEventListener('change', function() {
            const client = clientsData.find(c => c.id == this.value);
            if (client) {
                document.querySelector('input[name="expediteur_name"]').value = client.name || '';
                document.querySelector('input[name="expediteur_phone"]').value = client.phone || '';
                document.querySelector('input[name="expediteur_email"]').value = client.email || '';
                document.querySelector('input[name="expediteur_address"]').value = client.address || '';
            }
        });
    }

    if (destSelect) {
        destSelect.addEventListener('change', function() {
            const client = clientsData.find(c => c.id == this.value);
            if (client) {
                document.querySelector('input[name="destinataire_name"]').value = client.name || '';
                document.querySelector('input[name="destinataire_phone"]').value = client.phone || '';
                document.querySelector('input[name="destinataire_email"]').value = client.email || '';
                document.querySelector('input[name="destinataire_address"]').value = client.address || '';
            }
        });
    }

    // 2. Product Price Auto-Fill and Total Calculations
    function calculateTotals() {
        let grandTotal = 0;

        rows.forEach(row => {
            const nbreColis = parseFloat(row.querySelector('input[name="m_nbre_colis[]"]').value) || 0;
            const weight = parseFloat(row.querySelector('input[name="m_weight[]"]').value) || 0;
            const prixKg = parseFloat(row.querySelector('input[name="m_prix_kg[]"]').value) || 0;
            
            const lineTotal = nbreColis * weight * prixKg;
            grandTotal += lineTotal;

            const totalSpan = row.querySelector('.ligne-total');
            if (totalSpan) {
                totalSpan.textContent = new Intl.NumberFormat('fr-FR').format(Math.round(lineTotal)) + ' FCFA';
            }
        });

        const formattedGrandTotal = new Intl.NumberFormat('fr-FR').format(Math.round(grandTotal)) + ' FCFA';
        if (sousTotalEl) sousTotalEl.textContent = formattedGrandTotal;
        if (totalFcfaEl) totalFcfaEl.textContent = formattedGrandTotal;

        // EUR Conversion
        const rate = <?= (float) $eur_to_xof_rate ?>;
        const grandTotalEur = grandTotal / rate;
        if (totalEurEl) {
            totalEurEl.textContent = '≈ ' + new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(grandTotalEur) + ' €';
        }

        // Keep valeur_declaree in sync with total for default payment/assurance value
        if (inputValeurDeclaree && grandTotal > 0 && (!inputValeurDeclaree.value || inputValeurDeclaree.value === '0' || inputValeurDeclaree.dataset.auto === 'true')) {
            inputValeurDeclaree.value = Math.round(grandTotal);
            inputValeurDeclaree.dataset.auto = 'true';
        }
    }

    // Attach event listeners to all input elements in table
    rows.forEach(row => {
        // Watch product dropdown to auto-set price/kg (can be multiple select name like m_product_id_0[])
        const prodSelect = row.querySelector('select[name^="m_product_id_"]');
        if (prodSelect) {
            prodSelect.addEventListener('change', function() {
                const selectedOptions = Array.from(this.selectedOptions).filter(opt => opt.value !== "");
                if (selectedOptions.length > 0) {
                    let firstPrice = null;
                    let validValues = [];
                    let hasPriceMismatch = false;

                    selectedOptions.forEach(opt => {
                        const product = productsData.find(p => p.id == opt.value);
                        if (product) {
                            const price = Math.round(parseFloat(product.prix_unitaire) || 0);
                            if (firstPrice === null) {
                                firstPrice = price;
                                validValues.push(opt.value);
                            } else if (firstPrice === price) {
                                validValues.push(opt.value);
                            } else {
                                hasPriceMismatch = true;
                            }
                        }
                    });

                    if (hasPriceMismatch) {
                        alert("Attention : Tous les produits sélectionnés sur une même ligne doivent avoir le même prix unitaire !");
                        // Deselect options that don't match the first price
                        Array.from(this.options).forEach(opt => {
                            if (opt.value && !validValues.includes(opt.value)) {
                                opt.selected = false;
                            }
                        });
                        // Re-trigger change to update UI select-search
                        this.dispatchEvent(new Event("change", { bubbles: true }));
                        return;
                    }

                    const priceInput = row.querySelector('input[name="m_prix_kg[]"]');
                    if (priceInput && firstPrice !== null) {
                        priceInput.value = firstPrice;
                        calculateTotals();
                    }
                } else {
                    const priceInput = row.querySelector('input[name="m_prix_kg[]"]');
                    if (priceInput) {
                        priceInput.value = '0.00';
                        calculateTotals();
                    }
                }
            });
        }

        const inputs = row.querySelectorAll('input');
        inputs.forEach(input => {
            input.addEventListener('input', calculateTotals);
        });
    });

    if (inputValeurDeclaree) {
        inputValeurDeclaree.addEventListener('input', function() {
            this.dataset.auto = 'false';
        });
    }

    // Double-submit prevention
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                if (submitBtn.dataset.submitted === 'true') {
                    e.preventDefault();
                    return;
                }
                submitBtn.dataset.submitted = 'true';
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span style="display:inline-flex;align-items:center;gap:0.5rem;"><svg width="16" height="16" viewBox="0 0 24 24" style="animation:spin 1s linear infinite;"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31 31"/></svg> Enregistrement en cours...</span>';
            }
        });
    }

    calculateTotals();
});
</script>
<style>@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }</style>
