<?php

use App\View\Components\Employee;
use App\View\Components\Ui;
use App\View\Pages\Employee\RequestShowPage;

/** @var RequestShowPage $page */

ob_start();
?>
<div class="finea-shell employee-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Suivi de ma demande',
            (string) ($page->request['reason'] ?? ''),
            [
                'eyebrow' => (string) ($page->request['reference'] ?? ''),
                'class' => 'employee-hero',
                'actions' => [Ui::badge((string) ($page->request['status'] ?? ''), 'info')],
            ]
        ) ?>
        <div class="employee-two-columns">
            <?= Ui::section('Détails', Employee::requestDetails($page)) ?>
            <?= Ui::section('Historique du processus', Employee::timeline((array) ($page->request['events'] ?? []))) ?>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
