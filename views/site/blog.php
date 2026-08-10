<?php
use App\Helpers\View;
use App\View\Components\Site;
use App\View\Pages\Site\SitePage;
/** @var SitePage $page */
ob_start();
?>
<div class="site-content">
<section class="site-page-hero" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-radius: 20px; padding: 35px 40px; color: #ffffff; margin-bottom: 25px; box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.35);">
    <span style="display:inline-flex; align-items:center; gap:6px; background:rgba(37,99,235,0.2); color:#60a5fa; border:1px solid rgba(96,165,250,0.3); padding:4px 12px; border-radius:20px; font-size:0.8rem; font-weight:700; text-transform:uppercase; margin-bottom:12px;">
        <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" stroke-width="2.5" fill="none"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
        Actualités & Guides Logistiques
    </span>
    <h1 style="color:#ffffff; font-size:2.2rem; font-weight:800; margin:0 0 10px 0;">Le Mag du Transit & Commerce International</h1>
    <p style="color:#94a3b8; font-size:1rem; margin:0;">Guides pratiques, réglementations douanières et analyses des tendances fret international.</p>
</section>

<section style="display:grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap:24px; margin-bottom:40px;">
<?php foreach ($page->articles as $article): ?>
    <?php
    $words = str_word_count(strip_tags((string)($article['content'] ?? '')));
    $readTime = max(2, ceil($words / 200));
    $dateStr = !empty($article['published_at']) ? date('d/m/Y', strtotime((string)$article['published_at'])) : date('d/m/Y');
    ?>
    <article style="background:#ffffff; border-radius:18px; border:1px solid #e2e8f0; overflow:hidden; box-shadow:0 10px 25px -5px rgba(15,23,42,0.05); display:flex; flex-direction:column; transition:transform 0.2s ease;">
        <div style="height:160px; background:linear-gradient(135deg, #1e293b 0%, #3b82f6 100%); padding:20px; display:flex; flex-direction:column; justify-content:space-between; color:#ffffff;">
            <span style="align-self:flex-start; background:rgba(255,255,255,0.2); backdrop-filter:blur(6px); padding:4px 10px; border-radius:12px; font-size:0.75rem; font-weight:800; text-transform:uppercase;">
                <?= View::e((string)($article['author_name'] ?? 'Équipe LBP')) ?>
            </span>
            <div style="font-size:0.78rem; opacity:0.9; display:flex; align-items:center; gap:8px;">
                <span>📅 <?= View::e($dateStr) ?></span>
                <span>•</span>
                <span>⏱️ <?= $readTime ?> min de lecture</span>
            </div>
        </div>
        <div style="padding:22px; flex:1; display:flex; flex-direction:column; justify-content:space-between;">
            <div>
                <h2 style="font-size:1.15rem; font-weight:800; color:#0f172a; margin:0 0 10px 0; line-height:1.4;">
                    <?= View::e((string) $article['title']) ?>
                </h2>
                <p style="color:#64748b; font-size:0.88rem; margin:0 0 20px 0; line-height:1.5;">
                    <?= View::e((string) ($article['excerpt'] ?? '')) ?>
                </p>
            </div>
            <a href="<?= View::url('site/blog/' . $article['slug']) ?>" style="background:#eff6ff; color:#2563eb; border:1px solid #bfdbfe; font-weight:700; padding:10px 18px; border-radius:10px; text-decoration:none; text-align:center; font-size:0.88rem; display:inline-flex; align-items:center; justify-content:center; gap:6px;">
                <span>Lire l’article complet</span>
                <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </a>
        </div>
    </article>
<?php endforeach; ?>
</section>
</div>
<?php $content=ob_get_clean(); require BASE_PATH.'/views/layouts/site.php';
