<?php

use App\Helpers\View;
use App\View\Components\EmployeeCard;
use App\View\Components\Form;
use App\View\Components\Rh;
use App\View\Components\Ui;
use App\View\Pages\Rh\PersonnelIndexPage;

/** @var PersonnelIndexPage $page */

ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <?= Rh::pageHeader(
            'Liste du personnel',
            'Rechercher, filtrer et ouvrir les dossiers individuels des collaborateurs.',
            [
                'eyebrow' => 'Annuaire RH',
                'actions' => [
                    Ui::badge(
                        $page->total . ' résultat' . ($page->total > 1 ? 's' : ''),
                        'neutral',
                        ['class' => 'rh-pending-chip', 'unstyled' => true]
                    ),
                    $page->canCreate
                        ? Ui::button('Intégrer un collaborateur', [
                            'href' => 'rh/personnel/nouveau',
                            'variant' => 'accent',
                        ])
                        : '',
                ],
            ]
        ) ?>

        <?= Rh::restrictedData($page->restrictedTables) ?>

        <form method="get" action="<?= View::url('rh/personnel') ?>" class="finea-filter-card rh-personnel-filters">
            <div class="rh-personnel-filter-grid">
                <?= Form::input('q', [
                    'label' => 'Recherche',
                    'value' => (string) ($page->filters['q'] ?? ''),
                    'placeholder' => 'Nom, matricule ou e-mail',
                    'fieldClass' => 'rh-filter-search',
                ]) ?>
                <?= Form::selectSearch(
                    'service_id',
                    $page->filterOptions['services'],
                    $page->filters['service_id'] ?? '',
                    ['label' => 'Service']
                ) ?>
                <?= Form::selectSearch(
                    'function_id',
                    $page->filterOptions['functions'],
                    $page->filters['function_id'] ?? '',
                    ['label' => 'Fonction']
                ) ?>
                <?= Form::selectSearch(
                    'status_id',
                    $page->filterOptions['statuses'],
                    $page->filters['status_id'] ?? '',
                    ['label' => 'Statut']
                ) ?>
                <?= Form::selectSearch('scope', [
                    ['value' => 'active', 'label' => 'En poste'],
                    ['value' => 'inactive', 'label' => 'Sorties'],
                    ['value' => 'all', 'label' => 'Tous'],
                ], $page->filters['scope'] ?? 'active', ['label' => 'Périmètre']) ?>
                <div class="finea-actions rh-personnel-filter-actions">
                    <?= Ui::button('Filtrer', ['variant' => 'primary', 'type' => 'submit']) ?>
                    <?= Ui::button('Réinitialiser', [
                        'href' => 'rh/personnel',
                        'variant' => 'secondary',
                    ]) ?>
                </div>
            </div>
        </form>

        <?php if ($page->employees === []): ?>
            <?= Rh::card(Ui::emptyState(
                'Aucun collaborateur',
                'Aucun dossier ne correspond aux critères sélectionnés.'
            )) ?>
        <?php else: ?>
            <section class="rh-personnel-card-grid" aria-label="Collaborateurs">
                <?php foreach ($page->employees as $item): ?>
                    <?= EmployeeCard::render($item['employee'], $item['actions']) ?>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <?= Rh::paginationLinks($page->pagination) ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
