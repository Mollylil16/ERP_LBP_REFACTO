<?php
use App\Helpers\View;
use App\View\Components\Form;
use App\View\Components\Site;
use App\View\Components\Ui;
use App\View\Pages\Site\SitePage;

/** @var SitePage $page */
ob_start();
?>
<section class="site-hero site-hero--image">
    <div class="site-hero__content">
        <p class="finea-eyebrow">Transit • Import-export • Logistique internationale</p>
        <h1>Expédiez, dédouanez et suivez vos marchandises avec une visibilité totale.</h1>
        <p>Un site public moderne connecté à l’ERP LBP : demandes de devis, suivi colis, réseau d’agences, publicité, leads et informations opérationnelles.</p>
        <div class="site-actions">
            <?= Ui::button('Suivre un colis', ['href' => 'site/tracking', 'variant' => 'accent']) ?>
            <?= Ui::button('Demander un devis', ['href' => 'site/devis', 'variant' => 'secondary']) ?>
        </div>
    </div>
    <aside class="site-card site-tracking-card">
        <span class="site-card__badge">Tracking live</span>
        <strong>Où est votre colis ?</strong>
        <form method="get" action="<?= View::url('site/tracking') ?>">
            <?= Form::input('ref', ['label' => 'Référence', 'value' => $page->defaultShipment, 'placeholder' => 'Référence colis / BL / dossier', 'aria-label' => 'Référence de suivi']) ?>
            <?= Ui::button('Rechercher', ['variant' => 'primary', 'type' => 'submit']) ?>
        </form>
        <small>Essayez : LBP-EXP-2026-00124, LBP-COL-2026-00087 ou BL-LBP-778245-CI.</small>
    </aside>
</section>
<?= Site::stats($page->stats) ?>
<section class="site-section-head"><p class="finea-eyebrow">Services</p><h2>Une chaîne transit complète, du fournisseur au destinataire final.</h2></section>
<?= Site::services($page->services) ?>
<section class="site-split">
    <div class="site-image-panel site-image-panel--warehouse" role="img" aria-label="Entrepôt logistique générique"></div>
    <div class="site-panel-copy">
        <p class="finea-eyebrow">Innovation ERP</p>
        <h2>Un site web piloté par le module Site internet.</h2>
        <p>Les contenus, agences, leads, demandes de devis et statuts de tracking sont pensés pour être administrés depuis le backoffice LBP.</p>
        <ul class="site-check-list"><li>Publicité et acquisition clients</li><li>Suivi colis connecté aux dossiers opérationnels</li><li>Réseau d’agences paramétrable par pays</li><li>Formulaires convertis en leads CRM</li></ul>
    </div>
</section>
<section class="site-section-head"><p class="finea-eyebrow">Réseau</p><h2>Des agences LBP visibles sur carte et rattachables aux employés.</h2><?= Ui::button('Localiser une agence', ['href' => 'site/agences', 'variant' => 'secondary']) ?></section>
<section class="site-agency-strip">
    <?php foreach (array_slice($page->agencies, 0, 4) as $agency): ?>
        <article><strong><?= View::e($agency['name']) ?></strong><span><?= View::e($agency['city']) ?>, <?= View::e($agency['country']) ?></span></article>
    <?php endforeach; ?>
</section>
<section class="site-section-head"><p class="finea-eyebrow">Actualités</p><h2>Informations et annonces opérationnelles.</h2></section>
<section class="site-grid site-grid--three">
    <?php foreach ($page->news as $item): ?>
        <article class="finea-section-card site-news-card"><span><?= View::e($item['date']) ?></span><h3><?= View::e($item['title']) ?></h3><p>Contenu de démonstration administrable depuis le module Site internet.</p></article>
    <?php endforeach; ?>
</section>
<?php $content = ob_get_clean(); require BASE_PATH . '/views/layouts/site.php';
