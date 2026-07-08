<?php

declare(strict_types=1);

use App\Helpers\View;
use App\View\Components\Ui;

/** @var array<int, array<string, mixed>> $manifests */
/** @var array<int, array<string, mixed>> $parcels */

?>
<div class="finea-shell">
    <div class="finea-container">

        <?= Ui::pageHeader(
            'Gestion Documentaire & Impressions',
            'Édition des manifestes de fret, étiquettes colis et documents de transport LBP.',
            [
                'eyebrow' => 'Documents Logistiques',
                'class' => 'rh-hero-white'
            ]
        ) ?>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:2rem; margin-bottom:2rem;">
            
            <!-- Manifests Section -->
            <div class="finea-section-card">
                <h3 class="rh-step-title">Manifestes & Packing Lists</h3>
                <p style="color:var(--lbp-text-muted); font-size:0.9rem; margin-bottom:1rem;">Générez le manifeste de fret récapitulatif pour les autorités douanières et logistiques.</p>
                <div class="finea-table-wrapper">
                    <table class="finea-table">
                        <thead>
                            <tr style="background:#f8fafc;">
                                <th>Référence</th>
                                <th>Trajet</th>
                                <th style="text-align:center;">Colis</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($manifests)): ?>
                                <tr>
                                    <td colspan="4" style="text-align:center; padding:1.5rem; color:#94a3b8;">Aucun manifeste disponible.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($manifests as $m): ?>
                                    <tr>
                                        <td><strong><?= View::e($m['reference']) ?></strong></td>
                                        <td><small><?= View::e($m['agence_depart_name']) ?> ➔ <?= View::e($m['agence_arrivee_name']) ?></small></td>
                                        <td style="text-align:center;"><span class="finea-badge"><?= (int)$m['colis_count'] ?></span></td>
                                        <td style="text-align:right;">
                                            <a href="<?= View::url('colisage/groupage/' . $m['id']) ?>" class="finea-button finea-button--secondary finea-button-sm">
                                                Visualiser / Éditer
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Invoices & Labels Section -->
            <div class="finea-section-card">
                <h3 class="rh-step-title">Étiquettes & Factures Colis</h3>
                <p style="color:var(--lbp-text-muted); font-size:0.9rem; margin-bottom:1rem;">Imprimez les justificatifs individuels ou les étiquettes de tracking avec code-barres.</p>
                <div class="finea-table-wrapper">
                    <table class="finea-table">
                        <thead>
                            <tr style="background:#f8fafc;">
                                <th>N° Tracking</th>
                                <th>Client</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($parcels)): ?>
                                <tr>
                                    <td colspan="3" style="text-align:center; padding:1.5rem; color:#94a3b8;">Aucun colis enregistré.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($parcels as $p): ?>
                                    <tr>
                                        <td><strong><?= View::e($p['numero_tracking']) ?></strong></td>
                                        <td><?= View::e($p['expediteur_name']) ?></td>
                                        <td style="text-align:right; white-space:nowrap;">
                                            <a href="<?= View::url('colisage/parcels/' . $p['id'] . '/facture') ?>" target="_blank" class="finea-button finea-button--accent finea-button-sm" style="margin-right:0.25rem;">
                                                Facture
                                            </a>
                                            <button type="button" class="finea-button finea-button--secondary finea-button-sm" onclick="alert('Impression de l\'étiquette de tracking <?= View::e($p['numero_tracking']) ?>...');">
                                                Étiquette
                                            </button>
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
</div>
