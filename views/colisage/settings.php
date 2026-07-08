<?php

declare(strict_types=1);

use App\Helpers\View;
use App\View\Components\Form;
use App\View\Components\Ui;

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
        <!-- Settings sections -->

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">

            <!-- Section 1: Taux de change -->
            <section class="finea-section-card" style="border-top: 4px solid #f97316;">
                <div class="finea-section-heading">
                    <h2 class="finea-section-title">💱 Taux de Change</h2>
                </div>
                <p style="color:#64748b; font-size:0.9rem; margin-bottom:1.5rem;">
                    Ce taux est utilisé pour la conversion automatique des montants XOF ↔ EUR dans les factures, le reporting et les calculs de groupage.
                </p>

                <form method="post" action="<?= View::url('colisage/settings/enregistrer') ?>" class="js-protect-form">
                    <input type="hidden" name="section" value="taux_change">
                    
                    <div style="background: rgba(30,58,95,0.03); border-radius: 10px; padding: 1.5rem; margin-bottom: 1.5rem;">
                        <div style="display:flex; align-items:center; gap:1rem; margin-bottom:1rem;">
                            <span style="background:#1e3a5f; color:#fff; padding:0.4rem 0.8rem; border-radius:6px; font-weight:700; font-size:0.85rem;">EUR → XOF</span>
                            <span style="color:#64748b; font-size:0.85rem;">Parité de conversion</span>
                        </div>
                        
                        <?= Form::input('taux_change_eur', [
                            'label' => 'Valeur de 1 € en Francs CFA (XOF)',
                            'type' => 'number',
                            'step' => '0.000001',
                            'min' => '0.01',
                            'value' => number_format($tauxChangeEur, 6, '.', ''),
                            'required' => true,
                        ]) ?>
                        
                        <p style="margin-top:0.75rem; font-size:0.8rem; color:#94a3b8;">
                            Parité officielle BCEAO : 655,957 FCFA. Dernière mise à jour : 
                            <strong><?= View::e($allSettings['taux_change_eur_updated'] ?? date('d/m/Y')) ?></strong>
                        </p>
                    </div>

                    <!-- Tableau des taux en base (lbp_devises_taux) -->
                    <?php if (!empty($devisesRates)): ?>
                    <div style="margin-bottom:1.5rem;">
                        <h4 style="font-size:0.9rem; color:#475569; margin-bottom:0.5rem;">Taux enregistrés (table devises)</h4>
                        <div class="finea-table-wrapper">
                            <table class="finea-table" style="font-size:0.85rem;">
                                <thead>
                                    <tr style="background:#f1f5f9;">
                                        <th>Source</th>
                                        <th>Cible</th>
                                        <th style="text-align:right;">Taux</th>
                                        <th>Mis à jour</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($devisesRates as $r): ?>
                                    <tr>
                                        <td><span class="finea-badge"><?= View::e($r['devise_source']) ?></span></td>
                                        <td><span class="finea-badge"><?= View::e($r['devise_cible']) ?></span></td>
                                        <td style="text-align:right; font-weight:600;"><?= View::e(number_format((float) $r['taux'], 6, ',', '.')) ?></td>
                                        <td style="color:#64748b; font-size:0.8rem;"><?= View::e($r['updated_at'] ?? '—') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div style="display:flex; justify-content:flex-end;">
                        <?= Ui::button('Enregistrer le taux de change', [
                            'type' => 'submit',
                            'variant' => 'accent',
                            'data-label' => 'Enregistrer le taux',
                        ]) ?>
                    </div>
                </form>
            </section>

            <!-- Section 2: Préférences opérationnelles -->
            <section class="finea-section-card" style="border-top: 4px solid #1e3a5f;">
                <div class="finea-section-heading">
                    <h2 class="finea-section-title">⚙️ Préférences Opérationnelles</h2>
                </div>
                <p style="color:#64748b; font-size:0.9rem; margin-bottom:1.5rem;">
                    Paramètres généraux du module de colisage et de la logistique.
                </p>

                <form method="post" action="<?= View::url('colisage/settings/enregistrer') ?>" class="js-protect-form">
                    <input type="hidden" name="section" value="preferences">

                    <div style="display:flex; flex-direction:column; gap:1.2rem;">
                        <?= Form::input('colisage_tracking_prefix', [
                            'label' => 'Préfixe des numéros de tracking',
                            'value' => View::e($allSettings['colisage_tracking_prefix'] ?? 'LB-CI'),
                            'placeholder' => 'Ex: LB-CI',
                        ]) ?>

                        <?= Form::input('colisage_default_devise', [
                            'label' => 'Devise par défaut',
                            'value' => View::e($allSettings['colisage_default_devise'] ?? 'XOF'),
                            'placeholder' => 'XOF, EUR, USD',
                        ]) ?>

                        <?= Form::input('colisage_sla_jours', [
                            'label' => 'SLA livraison (jours)',
                            'type' => 'number',
                            'min' => 1,
                            'value' => View::e($allSettings['colisage_sla_jours'] ?? '7'),
                            'placeholder' => 'Délai cible en jours',
                        ]) ?>

                        <?= Form::input('colisage_tel_service_client', [
                            'label' => 'Téléphone service client (affiché sur factures)',
                            'value' => View::e($allSettings['colisage_tel_service_client'] ?? '0503467979 / 0503497979'),
                            'placeholder' => 'Numéros de contact',
                        ]) ?>
                    </div>

                    <div style="margin-top:1.5rem; display:flex; justify-content:flex-end;">
                        <?= Ui::button('Enregistrer les préférences', [
                            'type' => 'submit',
                            'variant' => 'primary',
                            'data-label' => 'Enregistrer les préférences',
                        ]) ?>
                    </div>
                </form>
            </section>

        </div>

        <!-- Section 3: Informations système -->
        <section class="finea-section-card" style="margin-top:2rem;">
            <div class="finea-section-heading">
                <h2 class="finea-section-title">📋 Informations du module</h2>
            </div>
            <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:1.5rem;">
                <div style="background:rgba(30,58,95,0.03); padding:1rem; border-radius:8px; text-align:center;">
                    <p style="font-size:0.8rem; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">Version</p>
                    <p style="font-size:1.3rem; font-weight:700; color:#1e3a5f; margin-top:0.3rem;">2.0</p>
                </div>
                <div style="background:rgba(30,58,95,0.03); padding:1rem; border-radius:8px; text-align:center;">
                    <p style="font-size:0.8rem; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">Tables SQL</p>
                    <p style="font-size:1.3rem; font-weight:700; color:#1e3a5f; margin-top:0.3rem;">12</p>
                </div>
                <div style="background:rgba(30,58,95,0.03); padding:1rem; border-radius:8px; text-align:center;">
                    <p style="font-size:0.8rem; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">Routes</p>
                    <p style="font-size:1.3rem; font-weight:700; color:#1e3a5f; margin-top:0.3rem;">28</p>
                </div>
                <div style="background:rgba(30,58,95,0.03); padding:1rem; border-radius:8px; text-align:center;">
                    <p style="font-size:0.8rem; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">Dernière migration</p>
                    <p style="font-size:1.3rem; font-weight:700; color:#1e3a5f; margin-top:0.3rem;">05/07/2026</p>
                </div>
            </div>
        </section>

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
