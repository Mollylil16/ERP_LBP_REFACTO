<?php
/** @var \App\Support\ViewBag $viewData */

use App\Helpers\View;
use App\View\Components\ContractCard;
use App\View\Components\Form;
use App\View\Components\Ui;

$contracts = isset($viewData) ? $viewData->array('data') : [];
$filters = isset($viewData) ? $viewData->array('filters') : [];
$total = isset($viewData) ? $viewData->int('total') : 0;
$page = isset($viewData) ? $viewData->int('page', 1) : 1;
$totalPages = isset($viewData) ? $viewData->int('totalPages', 1) : 1;

$queryForPage = static function (int $targetPage) use ($filters): string {
    return http_build_query(array_filter(
        $filters + ['page' => $targetPage],
        static fn(mixed $value): bool => $value !== '' && $value !== 0
    ));
};

ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Contrats',
            'Gestion des contrats & rémunérations',
            'Suivi du cycle de vie des contrats, de la rémunération de base et des indemnités fixes.',
            '<div class="finea-header-actions">'
                . '<span class="rh-pending-chip">' . $total . ' contrat' . ($total > 1 ? 's' : '') . '</span>'
                . Ui::button('Nouveau contrat', ['href' => 'rh/contrats/nouveau', 'variant' => 'secondary'])
                . '</div>',
            ['class' => 'rh-hero']
        ) ?>

        <section class="finea-filter-card rh-personnel-filters">
            <form method="get" action="<?= View::url('rh/contrats') ?>" class="rh-contract-filter-grid">
                <?= Form::input('search', [
                    'label' => 'Recherche',
                    'placeholder' => 'Nom ou matricule',
                    'value' => (string) ($filters['search'] ?? ''),
                    'fieldClass' => 'rh-filter-search',
                ]) ?>
                <?= Form::select('status', [
                    ['value' => '', 'label' => 'Tous les statuts'],
                    ['value' => 'active', 'label' => 'En cours'],
                    ['value' => 'terminated', 'label' => 'Terminé'],
                    ['value' => 'renewed', 'label' => 'Renouvelé'],
                ], $filters['status'] ?? '', ['label' => 'Statut']) ?>
                <div class="finea-actions rh-personnel-filter-actions">
                    <?= Ui::button('Filtrer', ['variant' => 'primary', 'type' => 'submit']) ?>
                    <?= Ui::button('Effacer', ['href' => 'rh/contrats', 'variant' => 'secondary']) ?>
                </div>
            </form>
        </section>

        <?php if ($contracts === []): ?>
            <section class="finea-section-card">
                <?= Ui::emptyState('Aucun contrat trouvé', 'Essayez de modifier vos filtres ou créez un nouveau contrat.') ?>
            </section>
        <?php else: ?>
            <section class="rh-contract-card-grid" aria-label="Contrats RH">
                <?php foreach ($contracts as $contract): ?>
                    <?= ContractCard::render($contract) ?>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <?php if ($totalPages > 1): ?>
            <nav class="rh-pagination" aria-label="Pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a class="<?= $i === $page ? 'is-active' : '' ?>"
                       href="<?= View::url('rh/contrats?' . $queryForPage($i)) ?>"
                       <?= $i === $page ? 'aria-current="page"' : '' ?>>
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    </div>
</div>
<?php $content = ob_get_clean(); require BASE_PATH . '/views/layouts/module.php';
