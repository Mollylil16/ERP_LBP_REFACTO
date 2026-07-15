<?php

declare(strict_types=1);

use App\Helpers\View;
use App\View\Components\Ui;
use App\View\Components\Colisage;

/** @var array<int, array<string, mixed>> $expeditions */

?>
<div class="finea-shell">
    <div class="finea-container">
        
        <?= Ui::pageHeader(
            'Groupage & Manifestes',
            'Planification des voyages de groupage et affectation des colis aux conteneurs ou palettes.',
            [
                'eyebrow' => 'Logistique & Fret',
                'class' => 'rh-hero-white',
                'actions' => [
                    Ui::button('Planifier un voyage', [
                        'href' => 'colisage/groupage/nouveau',
                        'variant' => 'accent'
                    ])
                ]
            ]
        ) ?>

        <?= Colisage::groupageListTable($expeditions) ?>

    </div>
</div>
