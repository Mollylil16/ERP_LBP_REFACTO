<?php
use App\Helpers\View;
ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <section class="finea-page-header rh-hero">
            <div>
                <p class="rh-eyebrow">Module RH</p>
                <h1><?= View::e($pageTitle) ?></h1>
                <p>Le lien est maintenant branché. Cette page sert de socle propre pour connecter les écrans métiers définitifs sans SQL dans les vues.</p>
            </div>
            <span class="rh-module-token"><?= View::e($code) ?></span>
        </section>
        <section class="rh-feature-grid">
            <?php foreach ($cards as [$title, $description]): ?>
                <article class="finea-section-card">
                    <h2 class="finea-section-title"><?= View::e($title) ?></h2>
                    <p><?= View::e($description) ?></p>
                </article>
            <?php endforeach; ?>
        </section>
    </div>
</div>
<?php $content = ob_get_clean(); require BASE_PATH . '/views/layouts/module.php'; ?>
