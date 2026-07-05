<?php

declare(strict_types=1);

use App\Helpers\View;
use App\View\Components\Ui;

/** @var array<int, array<string, mixed>> $tonnageData */
/** @var array<int, array<string, mixed>> $caData */
/** @var array<int, array<string, mixed>> $delaiData */
/** @var string $dateDebut */
/** @var string $dateFin */

?>
<div class="finea-shell">
    <div class="finea-container">

        <?= Ui::pageHeader(
            'Reporting & Analyses Opérationnelles',
            'Indicateurs clés de performance fret, volumes de groupage et statistiques financières.',
            [
                'eyebrow' => 'Décisionnel & Analytics',
                'class' => 'rh-hero-white'
            ]
        ) ?>

        <div style="background: white; border-radius: 8px; padding: 1rem; margin-bottom: 2rem; display: flex; gap: 1rem; align-items: flex-end; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <form method="get" action="" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
                <div>
                    <label style="display:block; font-size: 0.85rem; font-weight: 600; color: #475569; margin-bottom: 0.3rem;">Date de début</label>
                    <input type="date" name="date_debut" value="<?= View::e($dateDebut) ?>" style="padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px;" required>
                </div>
                <div>
                    <label style="display:block; font-size: 0.85rem; font-weight: 600; color: #475569; margin-bottom: 0.3rem;">Date de fin</label>
                    <input type="date" name="date_fin" value="<?= View::e($dateFin) ?>" style="padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px;" required>
                </div>
                <div>
                    <button type="submit" class="finea-button finea-button--accent">Appliquer le filtre</button>
                    <a href="?" class="finea-button finea-button--secondary" style="margin-left: 0.5rem;">Réinitialiser</a>
                </div>
            </form>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:2rem; margin-bottom:2rem;">
            
            <!-- Tonnage Section -->
            <div class="finea-section-card">
                <h3 class="rh-step-title" style="color:var(--lbp-blue-light);">Tonnage & Volumes par Trajet</h3>
                <p style="color:var(--lbp-text-muted); font-size:0.9rem; margin-bottom:1rem;">Visualisation du poids total expédié selon les trajets logistiques.</p>
                <div class="finea-table-wrapper">
                    <table class="finea-table">
                        <thead>
                            <tr style="background:#f8fafc;">
                                <th>Trajet</th>
                                <th style="text-align:center;">Nombre Colis</th>
                                <th style="text-align:right;">Poids Cumulé</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($tonnageData)): ?>
                                <tr>
                                    <td colspan="3" style="text-align:center; padding:1.5rem; color:#94a3b8;">Aucune donnée de volume.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($tonnageData as $t): ?>
                                    <tr>
                                        <td><strong><?= View::e($t['trajet'] ?: 'Non spécifié') ?></strong></td>
                                        <td style="text-align:center;"><span class="finea-badge"><?= (int)$t['total_colis'] ?></span></td>
                                        <td style="text-align:right; font-weight:600;"><?= number_format((float)$t['total_poids'], 2, ',', '.') ?> kg</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Revenue Share -->
            <div class="finea-section-card">
                <h3 class="rh-step-title" style="color:var(--lbp-gold);">Chiffre d'Affaires par Canal d'Envoi</h3>
                <p style="color:var(--lbp-text-muted); font-size:0.9rem; margin-bottom:1rem;">Répartition financière entre le fret de groupage classique et l'express.</p>
                <div class="finea-table-wrapper">
                    <table class="finea-table">
                        <thead>
                            <tr style="background:#f8fafc;">
                                <th>Mode / Canal</th>
                                <th style="text-align:right;">Total Collecté</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($caData)): ?>
                                <tr>
                                    <td colspan="2" style="text-align:center; padding:1.5rem; color:#94a3b8;">Aucun chiffre d'affaires enregistré.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($caData as $c): ?>
                                    <tr>
                                        <td>
                                            <?php
                                            $channel = match($c['type_expediteur']) {
                                                'export_aerien' => '✈️ Export Aérien',
                                                'export_maritime' => '🚢 Export Maritime',
                                                'import_aerien' => '✈️ Import Aérien',
                                                'import_maritime' => '🚢 Import Maritime',
                                                'colis_rapide_export' => '⚡ Colis Rapide Export',
                                                'colis_rapide_import' => '⚡ Colis Rapide Import',
                                                'dhl' => '📦 DHL Express',
                                                default => $c['type_expediteur']
                                            };
                                            echo View::e($channel);
                                            ?>
                                        </td>
                                        <td style="text-align:right; font-weight:700; color:var(--lbp-blue-deep);">
                                            <?= number_format((float)$c['total_ca'], 0, ',', '.') ?> <?= View::e($c['devise']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- Delays Section -->
        <div class="finea-section-card">
            <h3 class="rh-step-title">Délais Logistiques Moyens (Transit Time)</h3>
            <p style="color:var(--lbp-text-muted); font-size:0.9rem; margin-bottom:1rem;">Temps d'acheminement moyen mesuré entre la prise en charge et le retrait par le destinataire.</p>
            <div class="finea-table-wrapper">
                <table class="finea-table">
                    <thead>
                        <tr style="background:#f8fafc;">
                            <th>Axe / Corridor</th>
                            <th style="text-align:center;">Délai Moyen (Jours)</th>
                            <th>Qualité SLA</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($delaiData)): ?>
                            <tr>
                                <td colspan="3" style="text-align:center; padding:2rem; color:#94a3b8;">
                                    Données insuffisantes (les colis livrés doivent avoir une date de retrait renseignée).
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($delaiData as $d): ?>
                                <tr>
                                    <td><strong>Axe Inter-Agences #<?= (int)$d['agence_depart_id'] ?> ➔ #<?= (int)$d['agence_arrivee_id'] ?></strong></td>
                                    <td style="text-align:center; font-weight:700; color:var(--lbp-blue-light);"><?= number_format((float)$d['avg_days'], 1) ?> jours</td>
                                    <td>
                                        <?php
                                        $label = $d['avg_days'] <= 7 ? 'Excellent' : 'Normal';
                                        $tone = $d['avg_days'] <= 7 ? 'success' : 'warning';
                                        ?>
                                        <span class="finea-badge finea-badge--<?= $tone ?>"><?= $label ?></span>
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
