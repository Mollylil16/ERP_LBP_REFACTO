<?php

use App\Helpers\View;
use App\View\Components\Admin;
use App\View\Components\Form;
use App\View\Components\Ui;
use App\View\Pages\Admin\UserIndexPage;

/** @var UserIndexPage $page */

ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Utilisateurs',
            'Créer, retrouver et administrer les comptes de la plateforme.',
            [
                'eyebrow' => 'Comptes et profils',
                'class' => 'admin-hero',
                'actions' => [Ui::button('Nouvel utilisateur', [
                    'href' => 'admin/users/nouveau',
                    'variant' => 'accent',
                ])],
            ]
        ) ?>

        <form class="finea-filter-card" method="get" action="<?= View::url('admin/users') ?>">
            <div class="finea-filter-grid">
                <?= Form::input('q', [
                    'label' => 'Recherche',
                    'value' => $page->filters['q'] ?? '',
                    'placeholder' => 'Nom, email ou téléphone',
                ]) ?>
                <?= Form::selectSearch('status', [
                    ['value' => '', 'label' => 'Tous'],
                    ['value' => 'active', 'label' => 'Actif'],
                    ['value' => 'inactive', 'label' => 'Inactif'],
                    ['value' => 'blocked', 'label' => 'Bloqué'],
                ], $page->filters['status'] ?? '', ['label' => 'Statut']) ?>
                <?= Form::selectSearch('profile', [
                    ['value' => '', 'label' => 'Tous'],
                    ['value' => 'admin', 'label' => 'Administrateurs'],
                    ['value' => 'user', 'label' => 'Utilisateurs'],
                ], $page->filters['profile'] ?? '', ['label' => 'Profil']) ?>
                <div class="finea-actions">
                    <?= Ui::button('Filtrer', ['variant' => 'primary', 'type' => 'submit']) ?>
                    <?= Ui::button('Réinitialiser', ['href' => 'admin/users', 'variant' => 'secondary']) ?>
                </div>
            </div>
        </form>

        <?= Ui::section(
            $page->total . ' utilisateur' . ($page->total > 1 ? 's' : ''),
            Admin::userTable($page->users) . Admin::pagination($page->pagination),
            '',
            ['class' => 'admin-users-section']
        ) ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
