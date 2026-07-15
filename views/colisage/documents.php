<?php

declare(strict_types=1);

use App\Helpers\View;
use App\View\Components\Ui;
use App\View\Components\Colisage;

/** @var array<int, array<string, mixed>> $manifests */
/** @var array<int, array<string, mixed>> $parcels */

?>
<div class="finea-shell">
    <div class="finea-container">

         <?= Ui::pageHeader(
            'Gestion Documentaire & Impressions',
            'Édition des manifestes de fret, étiquettes colis et documents de transport LBP.',
            [
                'eyebrow' => 'Documents Logistiques',
                'class' => 'rh-hero-white'
            ]
        ) ?>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:2rem; margin-bottom:2rem;">
            <?= Colisage::manifestsTable($manifests) ?>
            <?= Colisage::parcelsDocTable($parcels) ?>
        </div>

    </div>
</div>
