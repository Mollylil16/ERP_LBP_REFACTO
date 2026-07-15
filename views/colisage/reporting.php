<?php

declare(strict_types=1);

use App\Helpers\View;
use App\View\Components\Ui;
use App\View\Components\Colisage;

/** @var array<int, array<string, mixed>> $tonnageData */
/** @var array<int, array<string, mixed>> $caData */
/** @var array<int, array<string, mixed>> $delaiData */
/** @var string $dateDebut */
/** @var string $dateFin */

?>
<div class="finea-shell">
    <div class="finea-container">

        <?= Ui::pageHeader(
            'Reporting & Analyses Opérationnelles',
            'Indicateurs clés de performance fret, volumes de groupage et statistiques financières.',
            [
                'eyebrow' => 'Décisionnel & Analytics',
                'class' => 'rh-hero-white'
            ]
        ) ?>

        <?= Colisage::dateFilter($dateDebut, $dateFin) ?>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:2rem; margin-bottom:2rem;">
            <?= Colisage::tonnageTable($tonnageData) ?>
            <?= Colisage::revenueTable($caData) ?>
        </div>

        <?= Colisage::delaysTable($delaiData) ?>

    </div>
</div>
