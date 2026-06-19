<?php

use App\View\Components\SiteAdmin;
use App\View\Pages\SiteAdmin\ConfigurationPage;

/** @var ConfigurationPage $page */

ob_start();
?>
<div class="finea-shell"><div class="finea-container"><?= SiteAdmin::configuration($page) ?></div></div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
