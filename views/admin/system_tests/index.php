<?php

use App\View\Components\Admin;
use App\View\Pages\Admin\SystemTestsPage;

/** @var SystemTestsPage $page */

ob_start();
?>
<div class="finea-shell">
    <div class="finea-container admin-health-container">
        <?= Admin::systemTests($page) ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
