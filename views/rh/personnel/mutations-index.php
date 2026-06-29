<?php

use App\View\Components\Rh;
use App\View\Components\Ui;
use App\View\Pages\Rh\PersonnelRegisterPage;

/** @var PersonnelRegisterPage $page */
?>
<div class="finea-shell"><div class="finea-container">
    <?= Ui::pageHeader(
        'Registre des mutations',
        'Toutes les affectations, changements de fonction, statut ou site.',
        [
            'eyebrow' => 'Mobilite interne',
            'class' => 'rh-hero',
            'actions' => [Ui::button('Choisir un collaborateur', [
                'href' => 'rh/personnel',
                'variant' => 'secondary',
            ])],
        ]
    ) ?>
    <?= Rh::restrictedData($page->restrictedTables) ?>
    <?= Rh::card(
        Rh::table(
            $page->rows,
            $page->columns,
            ['empty' => "Aucune mutation n'a encore ete enregistree."]
        ),
        ['class' => 'rh-recent-section']
    ) ?>
</div></div>
