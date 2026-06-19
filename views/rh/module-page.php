<?php
use App\Helpers\View;
use App\View\Components\Rh;
use App\View\Components\Ui;
use App\View\Pages\Rh\ModulePage;

/** @var ModulePage $page */

ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            $page->title,
            'Le lien est maintenant branché. Cette page sert de socle propre pour connecter les écrans métiers définitifs sans SQL dans les vues.',
            [
                'eyebrow' => 'Module RH',
                'class' => 'rh-hero',
                'actions' => [Ui::badge(
                    $page->code,
                    'neutral',
                    ['class' => 'rh-module-token', 'unstyled' => true]
                )],
            ]
        ) ?>
        <section class="rh-feature-grid">
            <?php foreach ($page->cards as [$title, $description]): ?>
                <?= Rh::card(
                    '<p>' . View::e($description) . '</p>',
                    ['tag' => 'article', 'title' => $title]
                ) ?>
            <?php endforeach; ?>
        </section>
    </div>
</div>
<?php $content = ob_get_clean(); require BASE_PATH . '/views/layouts/module.php'; ?>
