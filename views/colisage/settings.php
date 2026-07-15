<?php

declare(strict_types=1);

use App\Helpers\View;
use App\View\Components\Form;
use App\View\Components\Ui;
use App\View\Components\Colisage;

/** @var float $tauxChangeEur */
/** @var array<int, array<string, mixed>> $devisesRates */
/** @var array<string, mixed> $allSettings */

?>
<div class="finea-shell">
    <div class="finea-container">

        <?= Ui::pageHeader(
            'Paramétrage du module Colisage',
            'Configuration des taux de change, préférences logistiques et paramètres opérationnels.',
            [
                'eyebrow' => 'Configuration & Préférences',
                'class' => 'rh-hero-white',
            ]
        ) ?>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">

            <!-- Section 1: Taux de change -->
            <?php
            $ratesTable = Colisage::settingsRatesTable($devisesRates);

            $section1Content = '<form method="post" action="' . View::url('colisage/settings/enregistrer') . '" class="js-protect-form">'
                . '<input type="hidden" name="section" value="taux_change">'
                . '<div style="background: rgba(30,58,95,0.03); border-radius: 10px; padding: 1.5rem; margin-bottom: 1.5rem;">'
                . '<div style="display:flex; align-items:center; gap:1rem; margin-bottom:1rem;">'
                . '<span style="background:#1e3a5f; color:#fff; padding:0.4rem 0.8rem; border-radius:6px; font-weight:700; font-size:0.85rem;">EUR → XOF</span>'
                . '<span style="color:#64748b; font-size:0.85rem;">Parité de conversion</span>'
                . '</div>'
                . Form::input('taux_change_eur', [
                    'label' => 'Valeur de 1 € en Francs CFA (XOF)',
                    'type' => 'number',
                    'step' => '0.000001',
                    'min' => '0.01',
                    'value' => number_format($tauxChangeEur, 6, '.', ''),
                    'required' => true,
                ])
                . '<p style="margin-top:0.75rem; font-size:0.8rem; color:#94a3b8;">'
                . 'Parité officielle BCEAO : 655,957 FCFA. Dernière mise à jour : '
                . '<strong>' . View::e($allSettings['taux_change_eur_updated'] ?? date('d/m/Y')) . '</strong>'
                . '</p></div>'
                . $ratesTable
                . '<div style="display:flex; justify-content:flex-end;">'
                . Ui::button('Enregistrer le taux de change', [
                    'type' => 'submit',
                    'variant' => 'accent',
                    'data-label' => 'Enregistrer le taux',
                ])
                . '</div></form>';
            ?>
            <?= Ui::section('💱 Taux de Change', $section1Content, 'Ce taux est utilisé pour la conversion automatique des montants XOF ↔ EUR.', ['style' => 'border-top: 4px solid #f97316;']) ?>

            <!-- Section 2: Préférences opérationnelles -->
            <?php
            $section2Content = '<form method="post" action="' . View::url('colisage/settings/enregistrer') . '" class="js-protect-form">'
                . '<input type="hidden" name="section" value="preferences">'
                . '<div style="display:flex; flex-direction:column; gap:1.2rem;">'
                . Form::input('colisage_tracking_prefix', [
                    'label' => 'Préfixe des numéros de tracking',
                    'value' => View::e($allSettings['colisage_tracking_prefix'] ?? 'LB-CI'),
                    'placeholder' => 'Ex: LB-CI',
                ])
                . Form::input('colisage_default_devise', [
                    'label' => 'Devise par défaut',
                    'value' => View::e($allSettings['colisage_default_devise'] ?? 'XOF'),
                    'placeholder' => 'XOF, EUR, USD',
                ])
                . Form::input('colisage_sla_jours', [
                    'label' => 'SLA livraison (jours)',
                    'type' => 'number',
                    'min' => 1,
                    'value' => View::e($allSettings['colisage_sla_jours'] ?? '7'),
                    'placeholder' => 'Délai cible en jours',
                ])
                . Form::input('colisage_tel_service_client', [
                    'label' => 'Téléphone service client (affiché sur factures)',
                    'value' => View::e($allSettings['colisage_tel_service_client'] ?? '0503467979 / 0503497979'),
                    'placeholder' => 'Numéros de contact',
                ])
                . '</div>'
                . '<div style="margin-top:1.5rem; display:flex; justify-content:flex-end;">'
                . Ui::button('Enregistrer les préférences', [
                    'type' => 'submit',
                    'variant' => 'primary',
                    'data-label' => 'Enregistrer les préférences',
                ])
                . '</div></form>';
            ?>
            <?= Ui::section('⚙️ Préférences Opérationnelles', $section2Content, 'Paramètres généraux du module de colisage et de la logistique.', ['style' => 'border-top: 4px solid #1e3a5f;']) ?>

        </div>

        <!-- Section 3: Informations système -->
        <?= Colisage::moduleInfoCards() ?>

    </div>
</div>

<style>@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.js-protect-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const btn = form.querySelector('button[type="submit"]');
            if (btn) {
                if (btn.dataset.submitted === 'true') { e.preventDefault(); return; }
                btn.dataset.submitted = 'true';
                btn.disabled = true;
                btn.innerHTML = '<span style="display:inline-flex;align-items:center;gap:0.5rem;"><svg width="16" height="16" viewBox="0 0 24 24" style="animation:spin 1s linear infinite;"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31 31"/></svg> Enregistrement...</span>';
            }
        });
    });
});
</script>
