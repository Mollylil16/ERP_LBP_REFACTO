<?php
/** @var \App\Support\ViewBag $viewData */

use App\Helpers\Auth;
use App\Helpers\View;
use App\Security\OperationPolicy;
use App\View\Components\EmployeeCard;
use App\View\Components\Form;
use App\View\Components\Ui;

require BASE_PATH . '/views/rh/_navigation.php';

$filters = isset($viewData) ? $viewData->array('filters') : [];
$pagination = isset($viewData) ? $viewData->array('pagination') : [];
$options = isset($viewData) ? $viewData->array('options') : [];
$restrictedTables = isset($viewData) ? $viewData->array('restrictedTables') : [];
/** @var array<int,array<string,mixed>> $items */
$items = is_array($pagination['items'] ?? null) ? $pagination['items'] : [];
$total = (int) ($pagination['total'] ?? 0);
$currentPage = max(1, (int) ($pagination['page'] ?? 1));
$totalPages = max(1, (int) ($pagination['totalPages'] ?? 1));

$optionRows = static function (string $key) use ($options): array {
    $rows = is_array($options[$key] ?? null) ? $options[$key] : [];

    return array_merge(
        [['value' => '', 'label' => 'Tous']],
        array_map(static fn(array $row): array => [
            'value' => (string) ($row['id'] ?? ''),
            'label' => (string) ($row['name'] ?? ''),
        ], $rows)
    );
};

$queryForPage = static function (int $page) use ($filters): string {
    return http_build_query(array_filter(
        $filters + ['page' => $page],
        static fn(mixed $value): bool => $value !== '' && $value !== 0
    ));
};

$canView = Auth::canOperation(OperationPolicy::RH_EMPLOYEE_VIEW);
$canUpdate = Auth::canOperation(OperationPolicy::RH_EMPLOYEE_UPDATE);
$canMutate = Auth::canOperation(OperationPolicy::RH_MUTATION_CREATE);

ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Annuaire RH',
            'Liste du personnel',
            'Rechercher, filtrer et ouvrir les dossiers individuels des collaborateurs.',
            '<div class="finea-header-actions">'
                . '<span class="rh-pending-chip">' . $total . ' résultat' . ($total > 1 ? 's' : '') . '</span>'
                . (Auth::canOperation(OperationPolicy::RH_EMPLOYEE_CREATE)
                    ? Ui::button('Intégrer un collaborateur', ['href' => 'rh/personnel/nouveau', 'variant' => 'accent'])
                    : '')
                . '</div>',
            ['class' => 'rh-hero']
        ) ?>

        <?php require BASE_PATH . '/views/rh/_restricted-data.php'; ?>

        <form method="get" action="<?= View::url('rh/personnel') ?>" class="finea-filter-card rh-personnel-filters">
            <div class="rh-personnel-filter-grid">
                <?= Form::input('q', [
                    'label' => 'Recherche',
                    'value' => (string) ($filters['q'] ?? ''),
                    'placeholder' => 'Nom, matricule ou e-mail',
                    'fieldClass' => 'rh-filter-search',
                ]) ?>
                <?= Form::selectSearch(
                    'service_id',
                    $optionRows('services'),
                    $filters['service_id'] ?? '',
                    ['label' => 'Service']
                ) ?>
                <?= Form::selectSearch(
                    'function_id',
                    $optionRows('functions'),
                    $filters['function_id'] ?? '',
                    ['label' => 'Fonction']
                ) ?>
                <?= Form::selectSearch(
                    'status_id',
                    $optionRows('statuses'),
                    $filters['status_id'] ?? '',
                    ['label' => 'Statut']
                ) ?>
                <?= Form::selectSearch('scope', [
                    ['value' => 'active', 'label' => 'En poste'],
                    ['value' => 'inactive', 'label' => 'Sorties'],
                    ['value' => 'all', 'label' => 'Tous'],
                ], $filters['scope'] ?? 'active', ['label' => 'Périmètre']) ?>
                <div class="finea-actions rh-personnel-filter-actions">
                    <?= Ui::button('Filtrer', ['variant' => 'primary', 'type' => 'submit']) ?>
                    <?= Ui::button('Réinitialiser', ['href' => 'rh/personnel', 'variant' => 'secondary']) ?>
                </div>
            </div>
        </form>

        <?php if ($items === []): ?>
            <section class="finea-section-card">
                <?= Ui::emptyState('Aucun collaborateur', 'Aucun dossier ne correspond aux critères sélectionnés.') ?>
            </section>
        <?php else: ?>
            <section class="rh-personnel-card-grid" aria-label="Collaborateurs">
                <?php foreach ($items as $employee): ?>
                    <?php
                    $employeeId = (int) ($employee['id'] ?? 0);
                    $actions = [];
                    if ($canView) {
                        $actions[] = ['label' => 'Voir le dossier', 'href' => 'rh/personnel/' . $employeeId, 'variant' => 'primary'];
                    }
                    if ($canUpdate) {
                        $actions[] = ['label' => 'Modifier', 'href' => 'rh/personnel/' . $employeeId . '/modifier'];
                    }
                    if ($canMutate) {
                        $actions[] = ['label' => 'Mutation', 'href' => 'rh/personnel/' . $employeeId . '/mutation', 'variant' => 'plain'];
                    }
                    ?>
                    <?= EmployeeCard::render($employee, $actions) ?>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <?php if ($totalPages > 1): ?>
            <nav class="rh-pagination" aria-label="Pagination">
                <?php for ($page = 1; $page <= $totalPages; $page++): ?>
                    <a class="<?= $page === $currentPage ? 'is-active' : '' ?>"
                       href="<?= View::url('rh/personnel?' . $queryForPage($page)) ?>"
                       <?= $page === $currentPage ? 'aria-current="page"' : '' ?>>
                        <?= $page ?>
                    </a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
