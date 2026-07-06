<?php

declare(strict_types=1);

use App\Helpers\View;
use App\View\Components\Form;
use App\View\Components\Ui;

/** @var array<string, mixed> $colis */

$badgeTone = match($colis['statut']) {
    'RETIRÉ', 'LIVRÉ' => 'success',
    'RÉCEPTIONNÉ' => 'info',
    'EN_PRÉPARATION' => 'warning',
    'EN_TRANSIT' => 'primary',
    'ARRIVÉ' => 'accent',
    default => 'secondary'
};

// Montant total with conversion
$montantTotal = (float) ($colis['montant_total'] ?? $colis['valeur_declaree'] ?? 0);
$montantEur = (float) ($colis['montant_total_eur'] ?? 0);
$devise = $colis['devise'] ?? 'XOF';

// Trafic display
$traficLabel = $colis['trafic'] ?? match($colis['type_expediteur'] ?? '') {
    'export_aerien' => 'Groupage Aérien',
    'export_maritime' => 'Groupage Maritime',
    'import_aerien' => 'Import Aérien',
    'import_maritime' => 'Import Maritime',
    default => 'Groupage Aérien',
};

// Sous-total from marchandises
$sousTotal = 0.0;
if (!empty($colis['marchandises'])) {
    foreach ($colis['marchandises'] as $m) {
        $sousTotal += (float) ($m['total_ligne'] ?? 0);
    }
}

?>
<div class="finea-shell">
    <div class="finea-container">

        <?= Ui::pageHeader(
            'Détails Colis ' . View::e($colis['numero_tracking']),
            'Facture & Colisage — Imprimé spécifique LBP-CI.',
            [
                'eyebrow' => 'Suivi de Colis — Facture',
                'class' => 'rh-hero-white',
                'actions' => [
                    '<span class="finea-badge finea-badge--' . $badgeTone . '" style="padding:0.5rem 1rem; font-size:0.95rem; font-weight:600;">' . View::e($colis['statut']) . '</span>',
                    Ui::button('Retour à la liste', ['href' => 'colisage/parcels', 'variant' => 'secondary']),
                    Ui::button('Imprimer la facture', ['href' => 'colisage/parcels/' . $colis['id'] . '/facture', 'variant' => 'accent']),
                ],
            ]
        ) ?>

        <!-- Facture Header (LBP-CI format) -->
        <section class="finea-section-card" style="border-top:4px solid #1e3a5f;">
            <div style="padding:0.5rem 0; margin-bottom:1rem; background:rgba(30,58,95,0.03); border-radius:4px;">
                <p style="text-align:center; color:#1e3a5f; font-weight:700; font-size:0.85rem; text-transform:uppercase;">
                    IMPRIMÉ SPÉCIFIQUE — Facture & Colisage
                </p>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.5rem;">
                <div>
                    <p style="color:#64748b; font-size:0.85rem;">Agence : <strong style="color:#1e3a5f;">LBP Logistics — <?= View::e($colis['agence_depart_name'] ?? 'Siège Social') ?></strong></p>
                </div>
                <div style="text-align:right;">
                    <p style="color:#64748b; font-size:0.85rem;">SERVICE CLIENT : <strong>0503467979 / 0503497979</strong></p>
                </div>
            </div>

            <!-- Code Colis Banner -->
            <div style="background:#1e3a5f; color:#fff; padding:1rem 2rem; border-radius:6px; text-align:center; margin:1rem 0;">
                <h2 style="margin:0; font-size:1.4rem; letter-spacing:0.5px;">DÉTAILS COLIS&nbsp;&nbsp;<?= View::e($colis['numero_tracking']) ?></h2>
            </div>

            <div style="text-align:center; margin-bottom:1rem;">
                <p style="color:#64748b; font-size:0.9rem;">Nombre total de colis : <strong><?= View::e((string) ($colis['nombre_colis'] ?? 1)) ?></strong></p>
            </div>

            <!-- Info grid (like the facture) -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:2rem; padding:0.5rem 0;">
                <div>
                    <table style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td style="padding:0.4rem 0; font-weight:700; color:#1e3a5f; width:40%;">Code Colis :</td>
                            <td style="padding:0.4rem 0; color:#333;"><?= View::e($colis['numero_tracking']) ?></td>
                        </tr>
                        <tr>
                            <td style="padding:0.4rem 0; font-weight:700; color:#1e3a5f;">EXPÉDITEUR :</td>
                            <td style="padding:0.4rem 0; color:#333;"><?= View::e($colis['expediteur_name']) ?></td>
                        </tr>
                        <tr>
                            <td style="padding:0.4rem 0; font-weight:700; color:#1e3a5f;">TÉL EXP. :</td>
                            <td style="padding:0.4rem 0; color:#333;"><?= View::e($colis['expediteur_phone'] ?? '—') ?></td>
                        </tr>
                        <tr>
                            <td style="padding:0.4rem 0; font-weight:700; color:#1e3a5f;">TRAFIC :</td>
                            <td style="padding:0.4rem 0; color:#333;"><?= View::e($traficLabel) ?></td>
                        </tr>
                    </table>
                </div>
                <div>
                    <table style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td style="padding:0.4rem 0; font-weight:700; color:#1e3a5f; width:40%;">Date d'envoi :</td>
                            <td style="padding:0.4rem 0; color:#333;"><?= View::e(date('d/m/Y', strtotime($colis['created_at']))) ?></td>
                        </tr>
                        <tr>
                            <td style="padding:0.4rem 0; font-weight:700; color:#1e3a5f;">DESTINATION :</td>
                            <td style="padding:0.4rem 0; color:#333;"><?= View::e($colis['agence_arrivee_name'] ?? '—') ?></td>
                        </tr>
                        <tr>
                            <td style="padding:0.4rem 0; font-weight:700; color:#1e3a5f;">DESTINATAIRE :</td>
                            <td style="padding:0.4rem 0; color:#333;"><?= View::e($colis['destinataire_name']) ?></td>
                        </tr>
                        <tr>
                            <td style="padding:0.4rem 0; font-weight:700; color:#1e3a5f;">TÉL DEST. :</td>
                            <td style="padding:0.4rem 0; color:#333;"><?= View::e($colis['destinataire_phone'] ?? '—') ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <?php if (!empty($colis['expedition_id'])): ?>
                <p style="margin-top:1rem; font-size:0.9rem;">
                    <strong style="color:#1e3a5f;">Lié au Manifeste :</strong>
                    <a href="<?= View::url('colisage/groupage/' . $colis['expedition_id']) ?>" style="font-weight:600; color:#f97316; text-decoration:underline;">Voir le manifeste</a>
                </p>
            <?php endif; ?>
        </section>

        <!-- Marchandises Table (format facture LBP-CI) -->
        <section class="finea-section-card">
            <div class="finea-section-heading">
                <h2 class="finea-section-title">Marchandises répertoriées</h2>
            </div>
            <div class="finea-table-wrapper">
                <table class="finea-table">
                    <thead>
                        <tr style="background:#1e3a5f; color:#fff;">
                            <th style="width:5%;">N°</th>
                            <th style="width:8%;">Nbre Colis</th>
                            <th>Description</th>
                            <th style="width:12%;">Emballage</th>
                            <th style="width:8%;">Qté Emb.</th>
                            <th style="width:10%;">Poids (kg)</th>
                            <th style="width:10%;">Prix / Kg</th>
                            <th style="width:12%;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($colis['marchandises'])): ?>
                            <tr><td colspan="8" style="text-align:center; padding:1.5rem;">Aucune marchandise répertoriée.</td></tr>
                        <?php else: ?>
                            <?php $idx = 0; foreach ($colis['marchandises'] as $m): $idx++; ?>
                                <tr>
                                    <td style="text-align:center; font-weight:600;"><?= $idx ?></td>
                                    <td style="text-align:center;"><?= View::e((string) ($m['nbre_colis'] ?? 1)) ?></td>
                                    <td><?= View::e($m['description']) ?></td>
                                    <td><?= View::e($m['emballage'] ?? '—') ?></td>
                                    <td style="text-align:center;"><?= View::e((string) ($m['qte_emballage'] ?? 1)) ?></td>
                                    <td style="text-align:right;"><?= View::e(number_format((float) $m['poids_unitaire'], 2, ',', ' ')) ?></td>
                                    <td style="text-align:right;"><?= View::e(number_format((float) ($m['prix_kg'] ?? 0), 0, ',', ' ')) ?></td>
                                    <td style="text-align:right; font-weight:600;"><?= number_format((float) ($m['total_ligne'] ?? 0), 0, ',', '.') ?> FCFA</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="7" style="text-align:right; font-weight:600;">SOUS-TOTAL</td>
                            <td style="text-align:right; font-weight:600;"><?= number_format($sousTotal, 0, ',', '.') ?> FCFA</td>
                        </tr>
                        <tr style="background:#1e3a5f; color:#fff;">
                            <td colspan="7" style="text-align:right; font-weight:700; font-size:1.1rem;">MONTANT TOTAL</td>
                            <td style="text-align:right; font-weight:700; font-size:1.1rem;">
                                <?= number_format($montantTotal, 0, ',', '.') ?> FCFA<br>
                                <small>≈ <?= number_format($montantEur, 2, ',', '.') ?> €</small>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <!-- Signature Boxes (like the real facture) -->
        <section class="finea-section-card">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:3rem;">
                <div style="border:1px solid #ccc; border-radius:6px; padding:1.5rem; min-height:100px;">
                    <p style="font-weight:600; font-size:0.85rem; color:#1e3a5f;">CLIENT (date et visa)</p>
                </div>
                <div style="border:1px solid #ccc; border-radius:6px; padding:1.5rem; min-height:100px;">
                    <p style="font-weight:600; font-size:0.85rem; color:#1e3a5f;">SOCIÉTÉ (date et visa)</p>
                </div>
            </div>
        </section>

        <!-- Status action (retrait / livraison) -->
        <?php if ($colis['statut'] !== 'RETIRÉ' && $colis['statut'] !== 'LIVRÉ'): ?>
            <section class="finea-section-card" style="border-left:4px solid #f97316;">
                <div class="finea-section-heading">
                    <h2 class="finea-section-title">Signaler le retrait du colis (Livraison finale)</h2>
                </div>
                <p style="color:#64748b; font-size:0.9rem; margin-bottom:1rem;">
                    ⚠️ Vérification obligatoire de la CNI du récupérateur (Responsabilité Juridique)
                </p>
                <form method="post" action="<?= View::url('colisage/parcels/' . $colis['id'] . '/retirer') ?>" id="form-retrait">
                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem;">
                        <?= Form::input('recup_nom', ['label' => 'Nom du récupérateur', 'required' => true, 'placeholder' => 'Nom complet']) ?>
                        <?= Form::input('recup_cni', ['label' => 'N° pièce d\'identité (CNI)', 'required' => true, 'placeholder' => 'Ex: CNI998877']) ?>
                        <?= Form::input('recup_telephone', ['label' => 'Téléphone récupérateur', 'required' => true, 'placeholder' => 'Ex: 05050505']) ?>
                    </div>
                    <div style="margin-top:1.5rem; display:flex; justify-content:flex-end;">
                        <button type="submit" class="finea-button finea-button--accent" id="btn-retrait">Confirmer la livraison (Signature juridique)</button>
                    </div>
                </form>
                <script>
                (function() {
                    const form = document.getElementById('form-retrait');
                    if (form) {
                        form.addEventListener('submit', function(e) {
                            const btn = document.getElementById('btn-retrait');
                            if (btn) {
                                if (btn.dataset.submitted === 'true') { e.preventDefault(); return; }
                                btn.dataset.submitted = 'true';
                                btn.disabled = true;
                                btn.innerHTML = '<span style="display:inline-flex;align-items:center;gap:0.5rem;"><svg width="16" height="16" viewBox="0 0 24 24" style="animation:spin 1s linear infinite;"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31 31"/></svg> Traitement en cours...</span>';
                            }
                        });
                    }
                })();
                </script>
                <style>@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }</style>
            </section>
        <?php else: ?>
            <div class="finea-section-card" style="background:rgba(34,197,94,0.06); border:1px solid rgba(34,197,94,0.15);">
                <h3 style="color:#15803d; display:flex; align-items:center; gap:0.5rem;">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    Colis Retiré / Livré au destinataire
                </h3>
                <div style="margin-top:1rem; display:grid; grid-template-columns:1fr 1fr; gap:2rem; font-size:0.95rem;">
                    <div>
                        <p style="margin-bottom:0.3rem;"><strong>Récupérateur :</strong> <?= View::e($colis['recup_nom'] ?? '') ?></p>
                        <p style="margin-bottom:0.3rem;"><strong>N° d'identité (CNI) :</strong> <?= View::e($colis['recup_cni'] ?? '') ?></p>
                    </div>
                    <div>
                        <p style="margin-bottom:0.3rem;"><strong>Téléphone :</strong> <?= View::e($colis['recup_telephone'] ?? '') ?></p>
                        <p style="margin-bottom:0.3rem;"><strong>Date & Heure :</strong> <?= View::e($colis['recup_date_heure'] ?? '') ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Footer (like real facture) -->
        <div style="text-align:center; padding:2rem 0 3rem; color:#64748b; font-size:0.85rem;">
            <div style="display:flex; justify-content:space-between; margin-bottom:1.5rem; font-size:0.8rem; border-bottom:1px solid #e2e8f0; padding-bottom:0.5rem; color:#64748b;">
                <?php
                $operatorName = \App\Helpers\Auth::user() ? \App\Helpers\Auth::user()->fullName : 'Service Transit';
                ?>
                <div>Édité par <strong><?= View::e($operatorName) ?></strong> le <?= date('d/m/Y', strtotime($colis['created_at'])) ?> à <?= date('H:i', strtotime($colis['created_at'])) ?></div>
                <div>Réf. FCO-<?= View::e(date('my', strtotime($colis['created_at']))) ?>-<?= View::e(substr($colis['numero_tracking'], -3)) ?></div>
            </div>
            <p style="font-weight:700; color:#1e3a5f;">ADRESSE : PARIS 17 CHEMIN DES VIGNES 93000 BOBIGNY</p>
            <p>Tél : +33 7 75 73 27 97 / +33 7 51 19 83 82 / +33 7 45 93 56 92</p>
            <div style="display:flex; justify-content:center; gap:4rem; margin-top:0.5rem;">
                <div><strong>ABIDJAN</strong><br>Lun–Ven : 08h–17h | Sam–Dim : 08h–14h30</div>
                <div><strong>PARIS</strong><br>Lun–Sam : 10h30–18h | Dim : 10h–14h</div>
            </div>
            <p style="margin-top:1rem; font-size:0.8rem;">
                <strong>www.labelleporte.net</strong> | contact@labelleporte.net | +2252721580978 | +2250101222195
            </p>
        </div>

    </div>
</div>
