<?php

use App\View\Components\Rh;
use App\View\Components\Ui;
use App\View\Pages\Rh\PersonnelRegisterPage;

/** @var PersonnelRegisterPage $page */
ob_start();
?>
<div class="finea-shell"><div class="finea-container">
    <?= Ui::pageHeader(
        'Entrees et sorties',
        'Journal consolide des integrations, sorties et reintegrations.',
        [
            'eyebrow' => 'Mouvements RH',
            'class' => 'rh-hero',
            'actions' => [Ui::button('Nouvelle entree', [
                'href' => 'rh/personnel/nouveau',
                'variant' => 'accent',
            ])],
        ]
    ) ?>
    <?= Rh::restrictedData($page->restrictedTables) ?>
    <?= Rh::card(
        Rh::table(
            $page->rows,
            $page->columns,
            ['empty' => "Aucun mouvement du personnel n'a encore ete enregistre."]
        ),
        ['class' => 'rh-recent-section']
    ) ?>
</div></div>
<?php $content = ob_get_clean(); require BASE_PATH . '/views/layouts/module.php';
