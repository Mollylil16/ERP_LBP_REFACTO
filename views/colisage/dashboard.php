<?php

declare(strict_types=1);

use App\View\Components\Ui;
use App\View\Components\Dashboard;
use App\Helpers\View;

/** @var array<string, mixed> $dashboardModule */
$module = $dashboardModule;

?>
<div class="finea-shell colisage-dashboard">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Pilotage logistique et fret',
            'Le module colisage orchestre la réception en agence, le groupage des manifestes, le transport et les retraits de colis.',
            [
                'eyebrow' => 'Colisage & Logistique',
                'class' => 'rh-hero-white',
            ]
        ) ?>

        <!-- KPIs section -->
        <?= Dashboard::kpis($module['kpis']) ?>

        <!-- Quick actions and distribution -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-top: 2rem;">
            <div>
                <h3>Réseau des Agences Actives</h3>
                <p style="color: #64748b; font-size: 0.95rem; margin-top: 0.2rem;">Suivi de l'activité par point de vente / agence d'expédition.</p>
                <div class="finea-section-card" style="margin-top: 1rem; padding: 1.5rem;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="finea-section-card-nested" style="background: rgba(249, 115, 22, 0.05); padding: 1rem; border: 1px solid rgba(249, 115, 22, 0.1); border-radius: 8px;">
                            <strong>Europe</strong>
                            <p style="margin-top: 0.5rem; font-size: 0.9rem; color: #475569;">Agence France (Paris)</p>
                        </div>
                        <div class="finea-section-card-nested" style="background: rgba(249, 115, 22, 0.05); padding: 1rem; border: 1px solid rgba(249, 115, 22, 0.1); border-radius: 8px;">
                            <strong>Afrique de l'Ouest</strong>
                            <p style="margin-top: 0.5rem; font-size: 0.9rem; color: #475569;">Agence Sénégal (Dakar)</p>
                        </div>
                        <div class="finea-section-card-nested" style="background: rgba(249, 115, 22, 0.05); padding: 1rem; border: 1px solid rgba(249, 115, 22, 0.1); border-radius: 8px;">
                            <strong>Zone Aéroportuaire</strong>
                            <p style="margin-top: 0.5rem; font-size: 0.9rem; color: #475569;">Aéroport Port Bouët Fret</p>
                        </div>
                        <div class="finea-section-card-nested" style="background: rgba(249, 115, 22, 0.05); padding: 1rem; border: 1px solid rgba(249, 115, 22, 0.1); border-radius: 8px;">
                            <strong>Côte d'Ivoire (Abidjan)</strong>
                            <p style="margin-top: 0.5rem; font-size: 0.9rem; color: #475569;">Siege Abidjan, Abobo Dokui, Adjamé Pharmacie Latin</p>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <h3>Raccourcis Opérationnels</h3>
                <div style="display: flex; flex-direction: column; gap: 1rem; margin-top: 1rem;">
                    <?= Ui::button('Enregistrer un colis', [
                        'href' => 'colisage/parcels/nouveau',
                        'variant' => 'accent',
                        'style' => 'text-align: center; justify-content: center; display: flex; width: 100%;'
                    ]) ?>
                    <?= Ui::button('Planifier un groupage (manifeste)', [
                        'href' => 'colisage/groupage/nouveau',
                        'variant' => 'primary',
                        'style' => 'text-align: center; justify-content: center; display: flex; width: 100%;'
                    ]) ?>
                    <?= Ui::button('Suivi des manifestes', [
                        'href' => 'colisage/groupage',
                        'variant' => 'secondary',
                        'style' => 'text-align: center; justify-content: center; display: flex; width: 100%;'
                    ]) ?>
                </div>
            </div>
        </div>

        <!-- Recent activity section -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 3rem;">
            <div>
                <h3>Derniers Colis Enregistrés</h3>
                <div class="finea-section-card" style="margin-top: 1rem;">
                    <div class="finea-table-wrapper">
                        <table class="finea-table">
                            <thead>
                                <tr>
                                    <th>N° Tracking</th>
                                    <th>Expéditeur</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($module['recentParcels'])): ?>
                                    <tr><td colspan="3" style="text-align:center; padding:1.5rem; color:#64748b;">Aucun colis enregistré récemment.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($module['recentParcels'] as $p): ?>
                                        <tr>
                                            <td><strong><a href="<?= View::url('colisage/parcels/' . $p['id']) ?>"><?= View::e($p['numero_tracking']) ?></a></strong></td>
                                            <td><?= View::e($p['expediteur_name']) ?></td>
                                            <td>
                                                <?php
                                                $tone = match($p['statut']) {
                                                    'RETIRÉ', 'LIVRÉ' => 'success',
                                                    'RÉCEPTIONNÉ' => 'info',
                                                    'EN_PRÉPARATION' => 'warning',
                                                    'EN_TRANSIT' => 'primary',
                                                    default => 'neutral'
                                                };
                                                ?>
                                                <span class="finea-badge finea-badge--<?= $tone ?>"><?= View::e($p['statut']) ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div>
                <h3>Dernières Expéditions (Groupage)</h3>
                <div class="finea-section-card" style="margin-top: 1rem;">
                    <div class="finea-table-wrapper">
                        <table class="finea-table">
                            <thead>
                                <tr>
                                    <th>Référence</th>
                                    <th>Destination</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($module['recentExpeditions'])): ?>
                                    <tr><td colspan="3" style="text-align:center; padding:1.5rem; color:#64748b;">Aucun manifeste planifié.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($module['recentExpeditions'] as $e): ?>
                                        <tr>
                                            <td><strong><a href="<?= View::url('colisage/groupage/' . $e['id']) ?>"><?= View::e($e['reference']) ?></a></strong></td>
                                            <td><?= View::e($e['agence_arrivee_name']) ?></td>
                                            <td>
                                                <?php
                                                $tone = match($e['statut']) {
                                                    'ARRIVÉ' => 'success',
                                                    'EN_TRANSIT' => 'primary',
                                                    default => 'neutral'
                                                };
                                                ?>
                                                <span class="finea-badge finea-badge--<?= $tone ?>"><?= View::e($e['statut']) ?></span>
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
</div>
