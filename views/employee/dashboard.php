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

$submitBtnHtml = '<a class="finea-action-btn finea-action-btn--accent" href="' . View::url('espace-employe/demandes/nouvelle') . '" style="display:inline-flex; align-items:center; gap:8px; background:#f59e0b; color:#0f172a; font-weight:800; padding:10px 18px; border-radius:8px; text-decoration:none; box-shadow:0 4px 12px rgba(245, 158, 11, 0.3);">'
    . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>'
    . 'Soumettre une demande</a>';

$changePwdBtnHtml = Modal::render(
    'changePasswordModal',
    '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline-block; vertical-align:-2px; margin-right:6px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>Changer mon mot de passe',
    $passwordChangeFormHtml,
    '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline-block; vertical-align:-2px; margin-right:6px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>Changer mon mot de passe',
    ['variant' => 'secondary']
);

ob_start();
?>
<div class="finea-shell employee-shell">
    <div class="finea-container">
        <?= Ui::pageHeader('Mon espace personnel', $page->subtitle(), [
            'eyebrow' => 'Bonjour ' . View::e($page->displayName()),
            'class' => 'employee-hero',
            'actions' => $submitBtnHtml . $changePwdBtnHtml,
        ]) ?>

        <?= Dashboard::kpis([
            [
                'label' => 'Demandes ouvertes',
                'value' => $page->stats['openRequests'] ?? 0,
                'meta' => 'En cours de validation',
                'href' => 'espace-employe#demandes',
                'tone' => 'info',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>'
            ],
            [
                'label' => 'Congés disponibles',
                'value' => ($page->stats['leaveRemaining'] ?? 0) . ' j',
                'meta' => 'Solde estimé ' . date('Y'),
                'href' => 'espace-employe/demandes/nouvelle?type=leave',
                'tone' => 'success',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>'
            ],
            [
                'label' => 'Présence du mois',
                'value' => ($page->stats['presenceRate'] ?? 0) . '%',
                'meta' => count($page->attendance) . ' journée(s) suivie(s)',
                'href' => 'espace-employe#pointage',
                'tone' => 'purple',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2.2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>'
            ],
            [
                'label' => 'Explications attendues',
                'value' => $page->stats['pendingExplanations'] ?? 0,
                'meta' => 'Réponses à transmettre',
                'href' => 'espace-employe#explications',
                'tone' => 'warning',
                'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2.2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>'
            ],
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
