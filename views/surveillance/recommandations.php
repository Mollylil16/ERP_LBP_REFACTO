<?php

declare(strict_types=1);

use App\Helpers\View;
use App\View\Components\Ui;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());
/** @var array $recommandations */

ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Recommandations IA en attente',
            'File de validation des décisions importantes préconisées par les modèles de machine learning.',
            [
                'eyebrow' => 'Surveillance DG',
                'actions' => Ui::button(View::html('<svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" stroke-width="2.5" fill="none" style="vertical-align:middle; margin-right:4px;"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg> Retour au Dashboard'), ['href' => 'surveillance', 'variant' => 'secondary', 'class' => 'finea-button-sm'])
            ]
        ) ?>

        <!-- Navigation par onglets -->
        <?= Surveillance::renderNavTabs('recommandations', count($recommandations)) ?>

        <style>
            .rec-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
            .rec-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #cbd5e1; padding-bottom: 0.75rem; margin-bottom: 1rem; }
            .rec-title { font-size: 1.1rem; font-weight: 700; color: #0f172a; }
            .rec-body { font-size: 0.95rem; line-height: 1.6; color: #334155; }
            .rec-actions { display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1rem; border-top: 1px solid #cbd5e1; padding-top: 1rem; }
        </style>

        <?php if (empty($recommandations)): ?>
            <?= Ui::emptyState('Aucune recommandation en attente', 'Toutes les suggestions de l\'IA ont été traitées ou rejetées par la Direction.') ?>
        <?php else: ?>
            <?php foreach ($recommandations as $r): ?>
                <div class="rec-card">
                    <div class="rec-header">
                        <div class="rec-title">
                            Compte : <strong><?= View::e($r['user_name']) ?></strong>
                        </div>
                        <div>
                            <?php 
                            $tone = $r['action_recommandee'] === 'suspendre_compte' ? 'danger' : 'warning';
                            $label = $r['action_recommandee'] === 'suspendre_compte' ? '🚫 Demande de Suspension' : '⚠️ Qualification de Fraude';
                            echo Ui::badge($label, $tone);
                            ?>
                            <span style="font-size:0.8rem; color:#64748b; margin-left:0.5rem;">Suggéré le <?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></span>
                        </div>
                    </div>
                    <div class="rec-body">
                        <p><strong>Justification des modèles ML :</strong></p>
                        <div style="background:#f8fafc; border-left:4px solid var(--module-accent); padding:1rem; border-radius:4px; font-style:italic;">
                            <?= View::e($r['explication']) ?>
                        </div>
                    </div>
                    <div class="rec-actions">
                        <!-- Action Approuver -->
                        <form method="post" action="<?= View::url('surveillance/recommandations/' . $r['id'] . '/approuver') ?>" style="display:inline;">
                            <input type="hidden" name="_csrf_token" value="<?= View::e(\App\Helpers\Csrf::token()) ?>">
                            <?= Ui::button('✔️ Appliquer la recommandation', ['type' => 'submit', 'variant' => 'primary', 'class' => 'finea-button-sm']) ?>
                        </form>
                        <!-- Action Rejeter -->
                        <form method="post" action="<?= View::url('surveillance/recommandations/' . $r['id'] . '/rejeter') ?>" style="display:inline;">
                            <input type="hidden" name="_csrf_token" value="<?= View::e(\App\Helpers\Csrf::token()) ?>">
                            <?= Ui::button('❌ Rejeter', ['type' => 'submit', 'variant' => 'secondary', 'class' => 'finea-button-sm']) ?>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
