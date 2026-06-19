<?php
use App\Helpers\View;
use App\View\Components\Site;
use App\View\Pages\Site\SitePage;
/** @var SitePage $page */
ob_start();
?>
<div class="site-content">
<?= Site::pageHero('Actualités & conseils', 'Comprendre le transit pour mieux décider.', 'Guides pratiques, nouvelles liaisons et informations utiles au commerce international.') ?>
<section class="site-blog-grid">
<?php foreach ($page->articles as $article): ?>
    <article><div class="site-blog-card__image"></div><small><?= View::e((string) ($article['published_at'] ?? '')) ?></small><h2><?= View::e((string) $article['title']) ?></h2><p><?= View::e((string) ($article['excerpt'] ?? '')) ?></p><a class="site-button site-button--secondary" href="<?= View::url('site/blog/' . $article['slug']) ?>">Lire l’article <span>→</span></a></article>
<?php endforeach; ?>
</section></div>
<?php $content=ob_get_clean(); require BASE_PATH.'/views/layouts/site.php';
