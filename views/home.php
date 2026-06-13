<?php

use App\Helpers\View;

ob_start();
?>

<section class="hero">
    CONTENU DE LA PAGE D'ACCUEIL

    C'est ici qu'on aura la présentation de l'application, les fonctionnalités clés,
    et peut-être un appel à l'action pour s'inscrire ou se connecter.
</section>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/guest.php';
