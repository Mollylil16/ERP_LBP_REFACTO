<?php

use App\View\Components\EmployeeRequestForms;
use App\View\Components\Ui;
use App\View\Pages\Employee\RequestFormPage;

/** @var RequestFormPage $page */

ob_start();
?>
<div class="finea-shell employee-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Quelle démarche souhaitez-vous effectuer ?',
            'Choisissez une catégorie : le formulaire affiche uniquement les informations nécessaires.',
            ['eyebrow' => 'Self-service RH', 'class' => 'employee-hero']
        ) ?>
        <?= EmployeeRequestForms::render($page->csrfToken, $page->selectedType) ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
