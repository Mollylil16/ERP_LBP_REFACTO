<?php

/** @var array $user */

ob_start();
?>

<div id="dashboard-map">Bienvenu sur votre tableau de bord <?= $user['name']; ?> </div>

<?php
$content = ob_get_clean();

require BASE_PATH . '/views/layouts/app.php';
