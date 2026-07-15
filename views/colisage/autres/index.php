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
            'Autres Envois (Express)',
            'Suivi, saisie et édition des factures pour les envois express (DHL & Colis Rapide).',
            [
                'eyebrow' => 'Flux Express Internationaux',
                'class' => 'rh-hero-white',
                'actions' => [
                    Ui::button('Nouvel envoi express', [
                        'href' => 'colisage/autres/nouveau',
                        'variant' => 'accent'
                    ])
                ]
            ]
        ) ?>

        <?= Colisage::autresFilterForm($filters) ?>

        <?= Colisage::autresListTable($parcels) ?>

        <?php if ($pagination): ?>
            <?= Rh::paginationLinks($pagination) ?>
        <?php endif; ?>

    </div>
</div>
