<?php

use App\Helpers\View;
use App\Helpers\Csrf;
use App\View\Components\Dashboard;
use App\View\Components\Employee;
use App\View\Components\EmployeeRequestList;
use App\View\Components\Ui;
use App\View\Components\Form;
use App\View\Components\Modal;
use App\View\Pages\Employee\DashboardPage;

/** @var DashboardPage $page */

$passwordChangeFormHtml = '<form method="post" action="' . View::url('espace-employe/changer-mot-de-passe') . '" style="display: grid; gap: 15px;">'
    . Csrf::input()
    . Form::input('current_password', ['label' => 'Mot de passe actuel', 'type' => 'password', 'required' => true])
    . Form::input('new_password', ['label' => 'Nouveau mot de passe', 'type' => 'password', 'required' => true])
    . Form::input('confirm_password', ['label' => 'Confirmer le nouveau mot de passe', 'type' => 'password', 'required' => true])
    . '<div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px;">'
    . '<button type="button" class="finea-btn" data-modal-close style="border: 1px solid var(--finea-border); background: transparent; color: var(--finea-text-muted); font-weight: 700; font-size: 0.85rem; padding: 10px 20px; border-radius: 8px;">Annuler</button>'
    . '<button type="submit" class="finea-btn" style="background: var(--module-accent); border-color: var(--module-accent); color: white; font-weight: 700; font-size: 0.85rem; padding: 10px 20px; border-radius: 8px;">Enregistrer</button>'
    . '</div>'
    . '</form>';

ob_start();
?>
<div class="finea-shell employee-shell">
    <div class="finea-container">
        <?= Ui::pageHeader('Mon espace personnel', $page->subtitle(), [
            'eyebrow' => 'Bonjour ' . $page->firstName(),
            'class' => 'employee-hero',
            'actions' => [
                Ui::button('Soumettre une demande', [
                    'href' => 'espace-employe/demandes/nouvelle',
                    'variant' => 'accent',
                ]),
                Modal::render(
                    'changePasswordModal',
                    'Changer mon mot de passe',
                    $passwordChangeFormHtml,
                    'Changer mon mot de passe',
                    ['variant' => 'secondary']
                )
            ],
        ]) ?>

        <?= Dashboard::kpis([
            ['label' => 'Demandes ouvertes', 'value' => $page->stats['openRequests'] ?? 0, 'meta' => 'En cours de validation', 'href' => 'espace-employe#demandes'],
            ['label' => 'Congés disponibles', 'value' => ($page->stats['leaveRemaining'] ?? 0) . ' j', 'meta' => 'Solde estimé ' . date('Y'), 'href' => 'espace-employe/demandes/nouvelle?type=leave'],
            ['label' => 'Présence du mois', 'value' => ($page->stats['presenceRate'] ?? 0) . '%', 'meta' => count($page->attendance) . ' journée(s) suivie(s)', 'href' => 'espace-employe#pointage'],
            ['label' => 'Explications attendues', 'value' => $page->stats['pendingExplanations'] ?? 0, 'meta' => 'Réponses à transmettre', 'href' => 'espace-employe#explications'],
        ]) ?>

        <?= Ui::section('Mes demandes', EmployeeRequestList::render($page->requests), '', ['id' => 'demandes']) ?>

        <div class="employee-two-columns">
            <?= Ui::section(
                'Mon pointage du mois',
                Employee::attendance($page->attendance)
                    . '<a class="employee-inline-action" href="'
                    . View::url('espace-employe/demandes/nouvelle?type=attendance_correction')
                    . '">Signaler une anomalie</a>',
                '',
                ['id' => 'pointage']
            ) ?>
            <?= Ui::section('Demandes d’explications', Employee::explanations($page), '', ['id' => 'explications']) ?>
        </div>

        <?= Ui::section('Mes documents', Employee::documents($page->documents), '', ['id' => 'documents']) ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
