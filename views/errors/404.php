<?php

use App\Helpers\View;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());
ob_start();
?>

<section class="error-page">
    <div class="error-card">
        <span class="error-code">404</span>

        <h1>Page introuvable</h1>

        <p>
            La page demandée n’existe pas ou a été déplacée.
        </p>

        <a href="<?= View::url('') ?>" class="btn btn-primary">
            Retour à l’accueil
        </a>
    </div>
</section>

<?php
$content = ob_get_clean();

require BASE_PATH . '/views/layouts/guest.php';
