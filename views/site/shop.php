<?php

use App\View\Components\Site;
use App\View\Pages\Site\SitePage;

/** @var SitePage $page */

ob_start();
?>
<div class="site-content">
    <?= Site::pageHero('Marketplace logistique', 'Les bons produits et services pour expédier sans improviser.', 'Équipements, prestations transit, assurance et réservations de transport réunis dans un catalogue professionnel.') ?>
    <?php if (!empty($page->products)): ?>
        <section class="site-shop-toolbar"><div><strong><?= count($page->products) ?> offre(s)</strong><span>Prix indicatifs, confirmation finale par un conseiller</span></div><div><button type="button" class="is-active">Toutes</button><button type="button">Transport</button><button type="button">Formalités</button><button type="button">Emballage</button></div></section>
        <?= Site::products($page->products) ?>
    <?php else: ?>
        <div style="background:#ffffff; border-radius:20px; border:1px dashed #cbd5e1; padding:55px 30px; text-align:center; margin:30px 0; box-shadow:0 4px 20px rgba(15,23,42,0.03);">
            <div style="width:60px; height:60px; border-radius:18px; background:#eff6ff; color:#2563eb; display:flex; align-items:center; justify-content:center; margin:0 auto 18px auto;">
                <svg viewBox="0 0 24 24" width="30" height="30" stroke="currentColor" stroke-width="2" fill="none"><rect x="2" y="4" width="20" height="16" rx="2"/><line x1="6" y1="8" x2="6" y2="8"/><line x1="10" y1="8" x2="18" y2="8"/></svg>
            </div>
            <h2 style="font-size:1.3rem; font-weight:800; color:#0f172a; margin:0 0 10px 0;">Marketplace en cours d'approvisionnement</h2>
            <p style="color:#64748b; font-size:0.98rem; max-width:580px; margin:0 auto 24px auto; line-height:1.6;">Aucune offre ou fourniture n'est publiée pour le moment. La Direction Générale et l'équipe logistique ajouteront prochainement les matériels d'emballage export et tarifs de transport.</p>
            <a href="<?= \App\Helpers\View::url('site/devis') ?>" style="display:inline-flex; align-items:center; gap:8px; background:#0C2A4A; color:#ffffff; font-weight:700; padding:14px 28px; border-radius:12px; text-decoration:none; font-size:0.95rem;">Demander une cotation personnalisée ➔</a>
        </div>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/site.php';
