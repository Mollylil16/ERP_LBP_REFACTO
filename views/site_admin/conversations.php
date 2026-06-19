<?php

use App\View\Components\SiteAdminMessages;
use App\View\Pages\SiteAdmin\ConversationsPage;

/** @var ConversationsPage $page */

ob_start();
?>
<div class="finea-shell"><div class="finea-container"><?= SiteAdminMessages::page($page) ?></div></div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
