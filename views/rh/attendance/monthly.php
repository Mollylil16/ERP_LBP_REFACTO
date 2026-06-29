<?php
use App\Helpers\View;
use App\View\Components\Ui;
use App\View\Pages\Rh\AttendanceMonthlyPage;

/** @var AttendanceMonthlyPage $page */

?>
<div class="finea-shell rh-attendance-monthly-page">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Pointage Individuel Mensuel',
            'Visualisez le calendrier jour par jour pour verifier l\'assiduite, les retards et les absences d\'un salarie.',
            [
                'eyebrow' => 'Temps & Activite',
                'class' => 'rh-hero',
                'actions' => [
                    Ui::button('Passer a la saisie journaliere', [
                        'href' => 'rh/pointage',
                        'variant' => 'accent',
                    ]),
                    Ui::button('Retour au Dashboard', [
                        'href' => 'rh/dashboard',
                        'variant' => 'secondary',
                    ])
                ],
            ]
        ) ?>

        <div style="display: grid; grid-template-columns: 1fr 3fr; gap: 24px; margin-top: 20px;">
            <aside class="finea-section-card">
                <div class="rh-section-heading">
                    <div>
                        <p class="rh-eyebrow">Selection</p>
                        <h2 class="finea-section-title">Critères de recherche</h2>
                    </div>
                </div>

                <form method="get" action="<?= View::url('rh/pointage') ?>" style="margin-top: 15px; display: grid; grid-template-columns: 1fr; gap: 15px;">
                    <input type="hidden" name="vue" value="mensuel">

                    <div class="form-group">
                        <label for="employee_id" class="finea-form-label" style="display: block; font-weight: 600; margin-bottom: 5px;">Collaborateur</label>
                        <select name="employee_id" id="employee_id" class="finea-form-control" style="width: 100%; padding: 8px; border: 1px solid var(--finea-border); border-radius: 4px;" onchange="this.form.submit()">
                            <?php foreach ($page->employees as $emp): ?>
                                <option value="<?= (int)$emp['id'] ?>" <?= (int)$emp['id'] === $page->employeeId ? 'selected' : '' ?>>
                                    <?= View::e((string)$emp['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="month" class="finea-form-label" style="display: block; font-weight: 600; margin-bottom: 5px;">Mois de l'annee</label>
                        <input type="month" name="month" id="month" value="<?= View::e($page->month) ?>" class="finea-form-control" style="width: 100%; padding: 8px; border: 1px solid var(--finea-border); border-radius: 4px;" onchange="this.form.submit()">
                    </div>

                    <div style="margin-top: 5px;">
                        <?= Ui::button('Actualiser', ['variant' => 'primary', 'type' => 'submit']) ?>
                    </div>
                </form>
            </aside>

            <section class="finea-section-card">
                <div class="rh-section-heading" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <div>
                        <p class="rh-eyebrow">Calendrier individuel</p>
                        <h2 class="finea-section-title">Releve mensuel de presence</h2>
                    </div>
                    <?= Ui::badge(count($page->records) . ' ligne(s) enregistree(s)', 'neutral') ?>
                </div>

                <div style="margin-top: 20px; overflow-x: auto;">
                    <table class="finea-table" style="width: 100%; border-collapse: collapse; text-align: left;">
                        <thead>
                            <tr style="background: var(--finea-background-light); border-bottom: 2px solid var(--finea-border);">
                                <th style="padding: 12px; width: 120px;">Date</th>
                                <th style="padding: 12px; width: 130px;">Statut</th>
                                <th style="padding: 12px; width: 100px;">Arrivee</th>
                                <th style="padding: 12px; width: 100px;">Depart</th>
                                <th style="padding: 12px; width: 100px;">Heures eff.</th>
                                <th style="padding: 12px; width: 100px;">Heures Sup</th>
                                <th style="padding: 12px;">Notes / Observations</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($page->records === []): ?>
                                <tr>
                                    <td colspan="7" style="padding: 30px; text-align: center; color: var(--finea-text-muted);">
                                        Aucun pointage n'a ete enregistre pour ce collaborateur durant ce mois.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($page->records as $row): ?>
                                    <tr style="border-bottom: 1px solid var(--finea-border); background: <?= $row['attendance_status'] === 'absent' ? 'rgba(220,53,69,0.03)' : ($row['attendance_status'] === 'conge' ? 'rgba(40,167,69,0.03)' : 'none') ?>;">
                                        <td style="padding: 12px;">
                                            <strong><?= date('d/m/Y', strtotime((string)$row['attendance_date'])) ?></strong>
                                        </td>
                                        <td style="padding: 12px;">
                                            <span class="finea-status-badge finea-status-badge--<?= $row['attendance_status'] === 'present' ? 'success' : ($row['attendance_status'] === 'absent' ? 'danger' : 'info') ?>">
                                                <?= $page->formatStatus((string)$row['attendance_status']) ?>
                                            </span>
                                        </td>
                                        <td style="padding: 12px;">
                                            <?= $row['check_in_time'] ? substr((string)$row['check_in_time'], 0, 5) : '--:--' ?>
                                        </td>
                                        <td style="padding: 12px;">
                                            <?= $row['check_out_time'] ? substr((string)$row['check_out_time'], 0, 5) : '--:--' ?>
                                        </td>
                                        <td style="padding: 12px;">
                                            <strong><?= (float)$row['worked_hours'] ?> h</strong>
                                        </td>
                                        <td style="padding: 12px; color: var(--finea-success);">
                                            <?= (float)$row['overtime_hours'] > 0 ? '+' . (float)$row['overtime_hours'] . ' h' : '--' ?>
                                        </td>
                                        <td style="padding: 12px; font-size: 13px; color: var(--finea-text-muted);">
                                            <?= View::e((string)$row['notes']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>
