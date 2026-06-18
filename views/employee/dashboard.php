<?php

use App\Helpers\Csrf;
use App\Helpers\View;
use App\View\Components\Dashboard;
use App\View\Components\Ui;
use App\View\Components\EmployeeRequestList;
use App\View\Components\Form;
use App\View\Components\Modal;

$date = static fn(?string $value): string => $value ? date('d/m/Y', strtotime($value)) : '—';
ob_start();
?>
<div class="finea-shell employee-shell">
    <div class="finea-container">
        <?= Ui::pageHeader('Mon espace personnel',
            ($employee['function_name'] ?: 'Fonction non renseignée') . ' · ' . ($employee['service_name'] ?: 'Service non renseigné'),
            ['eyebrow' => 'Bonjour ' . explode(' ', $employee['full_name'])[0], 'actions' => Ui::button('Soumettre une demande', ['href' => 'espace-employe/demandes/nouvelle', 'variant' => 'accent']), 'class' => 'employee-hero']
        ) ?>

        <?= Dashboard::kpis([
            ['label' => 'Demandes ouvertes', 'value' => $stats['openRequests'], 'meta' => 'En cours de validation', 'href' => 'espace-employe#demandes'],
            ['label' => 'Congés disponibles', 'value' => $stats['leaveRemaining'] . ' j', 'meta' => 'Solde estimé ' . date('Y'), 'href' => 'espace-employe/demandes/nouvelle?type=leave'],
            ['label' => 'Présence du mois', 'value' => $stats['presenceRate'] . '%', 'meta' => count($attendance) . ' journée(s) suivie(s)', 'href' => 'espace-employe#pointage'],
            ['label' => 'Explications attendues', 'value' => $stats['pendingExplanations'], 'meta' => 'Réponses à transmettre', 'href' => 'espace-employe#explications'],
        ]) ?>

        <section class="finea-section-card" id="demandes">
            <div class="employee-heading"><div><p class="employee-eyebrow">Self-service RH</p><h2 class="finea-section-title">Mes demandes</h2></div><a href="<?= View::url('espace-employe/demandes/nouvelle') ?>">Nouvelle demande</a></div>
            <?= EmployeeRequestList::render($requests) ?>
        </section>

        <div class="employee-two-columns">
            <section class="finea-section-card" id="pointage">
                <div class="employee-heading"><div><p class="employee-eyebrow">Temps de travail</p><h2 class="finea-section-title">Mon pointage du mois</h2></div></div>
                <div class="employee-attendance-list">
                    <?php foreach ($attendance as $row): ?><article><time><?= $date($row['attendance_date']) ?></time><strong><?= View::e($row['attendance_status']) ?></strong><span><?= View::e(substr((string)$row['check_in_time'], 0, 5) ?: '—') ?> → <?= View::e(substr((string)$row['check_out_time'], 0, 5) ?: '—') ?></span><small><?= number_format((float)$row['worked_hours'], 1, ',', ' ') ?> h</small></article><?php endforeach; ?>
                    <?php if ($attendance === []): ?><div class="finea-empty-state">Aucun pointage disponible pour ce mois.</div><?php endif; ?>
                </div>
                <a class="employee-inline-action" href="<?= View::url('espace-employe/demandes/nouvelle?type=attendance_correction') ?>">Signaler une anomalie</a>
            </section>

            <section class="finea-section-card" id="explications">
                <div class="employee-heading"><div><p class="employee-eyebrow">Échanges RH</p><h2 class="finea-section-title">Demandes d’explications</h2></div></div>
                <div class="employee-explanation-list">
                <?php foreach ($explanations as $row): ?><article>
                    <header><strong><?= View::e($row['subject']) ?></strong><span class="employee-status status-<?= View::e($row['status']) ?>"><?= View::e($row['status']) ?></span></header>
                    <p><?= View::e($row['facts']) ?></p><small>Réponse attendue avant le <?= $date($row['response_due_date']) ?></small>
                    <?php if (in_array($row['status'], ['pending_response', 'complement_requested'], true)): ?>
                        <?php ob_start(); ?>
                        <form method="post" action="<?= View::url('espace-employe/explications/' . (int)$row['id'] . '/repondre') ?>">
                            <?= Form::hidden('_csrf_token', Csrf::token()) ?>
                            <?= Form::textarea('response', 'Votre réponse circonstanciée', '', ['rows' => 7, 'minlength' => 20, 'required' => true]) ?>
                            <?= Ui::button('Transmettre ma réponse', ['variant' => 'accent', 'type' => 'submit']) ?>
                        </form>
                        <?php $responseForm = (string) ob_get_clean(); ?>
                        <?= Modal::render('explanation-' . (int)$row['id'], 'Répondre à la demande d’explication', $responseForm, 'Répondre', ['eyebrow' => 'Droit de réponse']) ?>
                    <?php elseif ($row['employee_response']): ?><blockquote><?= View::e($row['employee_response']) ?></blockquote><?php endif; ?>
                </article><?php endforeach; ?>
                <?php if ($explanations === []): ?><div class="finea-empty-state">Aucune demande d’explication.</div><?php endif; ?>
                </div>
            </section>
        </div>

        <section class="finea-section-card" id="documents">
            <div class="employee-heading"><div><p class="employee-eyebrow">Dossier personnel</p><h2 class="finea-section-title">Mes documents</h2></div></div>
            <div class="employee-document-grid"><?php foreach ($documents as $document): ?><a href="<?= View::url('public/' . ltrim($document['stored_path'], '/')) ?>" target="_blank" rel="noopener"><strong><?= View::e($document['original_name']) ?></strong><small><?= View::e($document['document_type']) ?></small></a><?php endforeach; ?><?php if ($documents === []): ?><div class="finea-empty-state">Aucun document disponible.</div><?php endif; ?></div>
        </section>
    </div>
</div>
<?php $content = ob_get_clean(); require BASE_PATH . '/views/layouts/module.php'; ?>
