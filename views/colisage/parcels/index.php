<?php

declare(strict_types=1);

use App\Helpers\View;
use App\View\Components\Ui;
use App\View\Components\Rh;
use App\View\Components\Colisage;

/** @var array<string, mixed> $parcelsData */
/** @var array<string, mixed> $filters */
/** @var array<int, array<string, mixed>> $sites */

$parcels = $parcelsData['items'] ?? [];
$pagination = $parcelsData['pagination'] ?? null;

?>
<div class="finea-shell">
    <div class="finea-container">
        
        <?= Ui::pageHeader(
            'Gestion des Colis',
            'Saisie, suivi et groupage des colis des clients.',
            [
                'eyebrow' => 'Opérations de Colisage',
                'class' => 'rh-hero-white',
                'actions' => [
                    Ui::button('Enregistrer un colis', [
                        'href' => 'colisage/parcels/nouveau',
                        'variant' => 'accent'
                    ])
                ]
            ]
        ) ?>

        <?= Colisage::parcelsFilterForm($filters) ?>

        <?= Colisage::parcelsListTable($parcels) ?>

        <?php if ($pagination): ?>
            <?= Rh::paginationLinks($pagination) ?>
        <?php endif; ?>

    </div>
</div>
