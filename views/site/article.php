<?php
use App\Helpers\View;
use App\View\Pages\Site\SitePage;
/** @var SitePage $page */
/** @var array<string,mixed> $article */
ob_start();
?>
<div class="site-content"><article class="site-article">
<a class="site-text-link" href="<?= View::url('site/blog') ?>">← Toutes les actualités</a>
<header><p class="site-kicker">Par <?= View::e((string) ($article['author_name'] ?? 'Équipe LBP')) ?></p><h1><?= View::e((string) $article['title']) ?></h1><p><?= View::e((string) ($article['excerpt'] ?? '')) ?></p></header>
<div class="site-article__content"><?= nl2br(View::e((string) ($article['content'] ?? ''))) ?></div>
</article></div>
<?php $content=ob_get_clean(); require BASE_PATH.'/views/layouts/site.php';
