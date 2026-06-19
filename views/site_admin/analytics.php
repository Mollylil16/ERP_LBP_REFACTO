<?php
use App\View\Components\SiteAnalytics;
use App\View\Pages\SiteAdmin\AnalyticsPage;
/** @var AnalyticsPage $page */
ob_start();
?>
<div class="finea-shell"><div class="finea-container"><?= SiteAnalytics::page($page) ?></div></div>
<?php $content=ob_get_clean(); require BASE_PATH.'/views/layouts/module.php';
