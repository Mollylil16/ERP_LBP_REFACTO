<?php

use App\Helpers\View;
use App\View\Components\EmployeeRequestSummary;
use App\View\Components\Form;
use App\View\Components\Ui;

$date = static fn(?string $value): string => $value ? date('d/m/Y H:i', strtotime($value)) : '—';

/** @var array<string,mixed> $request */
/** @var string $csrfToken */

ob_start();
?>
<div class="finea-shell employee-shell">
    <div class="finea-container">
        <?= Ui::pageHeader('Suivi de ma demande', $request['reason'], ['eyebrow' => $request['reference'], 'class' => 'employee-hero', 'actions' => Ui::badge($request['status'], 'info')]) ?>
        <div class="employee-two-columns">
            <section class="finea-section-card">
                <h2 class="finea-section-title">Détails</h2>
                <?= EmployeeRequestSummary::details($request) ?>
                <?php if (!empty($request['attachment_path'])): ?>
                    <a class="employee-attachment-link" href="<?= View::url('public/' . ltrim($request['attachment_path'], '/')) ?>" target="_blank" rel="noopener">Consulter le justificatif · <?= View::e($request['attachment_original_name'] ?: 'Pièce jointe') ?></a>
                <?php endif; ?>
                <?php if (in_array($request['status'], ['draft', 'submitted'], true)): ?>
                    <form method="post" action="<?= View::url('espace-employe/demandes/' . (int)$request['id'] . '/annuler') ?>"><?= Form::hidden('_csrf_token', $csrfToken) ?><?= Ui::button('Annuler cette demande') ?></form>
                <?php endif; ?>
            </section>
            <section class="finea-section-card">
                <h2 class="finea-section-title">Historique du processus</h2>
                <ol class="employee-timeline"><?php foreach ($request['events'] as $event): ?><li><strong><?= View::e($event['status']) ?></strong><span><?= View::e($event['comment']) ?></span><time><?= $date($event['created_at']) ?></time></li><?php endforeach; ?></ol>
            </section>
        </div>
    </div>
</div>
<?php $content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php'; ?>