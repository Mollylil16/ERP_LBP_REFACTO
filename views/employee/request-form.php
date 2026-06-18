<?php

use App\View\Components\EmployeeRequestForms;
use App\View\Components\Ui;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());
$selected = (string) ($_GET['type'] ?? '');
ob_start();
?>
<div class="finea-shell employee-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Quelle démarche souhaitez-vous effectuer ?',
            'Choisissez une catégorie : le formulaire affichera uniquement les informations réellement nécessaires.',
            ['eyebrow' => 'Self-service RH', 'class' => 'employee-hero']
        ) ?>
        <?= EmployeeRequestForms::render($csrfToken, $selected) ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
