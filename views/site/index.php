<?php

use App\Helpers\View;
use App\View\Components\Site;
use App\View\Pages\Site\SitePage;

/** @var SitePage $page */

ob_start();
?>
<?= Site::carousel($page->slides) ?>
<div class="site-content">
    <?= Site::trackingDock($page->defaultShipment) ?>

    <section class="site-trust-strip">
        <span>Ils expédient avec nous</span><strong>AFRICA MEDICAL</strong><strong>KAB TRANSIT</strong><strong>NOVA RETAIL</strong><strong>WEST AFRICA TRADE</strong><strong>CI INDUSTRIES</strong>
    </section>

    <?= Site::stats($page->stats) ?>

    <section id="services" class="site-home-section">
        <?= Site::sectionHeading('Solutions intégrées', 'Tout le commerce international, dans une seule chaîne.', 'De l’achat fournisseur à la livraison finale, chaque étape est coordonnée et visible.') ?>
        <?= Site::services($page->services) ?>
    </section>

    <section class="site-commerce-banner">
        <div><p class="site-kicker">Marketplace B2B</p><h2>Achetez plus que du transport.</h2><p>Réservez du groupage, sécurisez vos marchandises et commandez les fournitures nécessaires à vos expéditions.</p><a class="site-cta site-cta--primary" href="<?= View::url('site/shop') ?>">Voir toute la marketplace <span>→</span></a></div>
        <aside><span>Chine → Afrique</span><strong>Départs chaque semaine</strong><small>Groupage maritime et aérien</small></aside>
    </section>

    <section class="site-home-section">
        <?= Site::sectionHeading('Sélection professionnelle', 'Les offres les plus demandées.', 'Une expérience e-commerce adaptée aux services de transit.', '<a class="site-text-link" href="' . View::url('site/shop') . '">Tout afficher →</a>') ?>
        <?= Site::products($page->products, 4) ?>
    </section>

    <section class="site-network-story">
        <div class="site-network-story__map"><span>Guangzhou</span><span>Dubaï</span><span>Paris</span><span>Abidjan</span><span>Cotonou</span><span>Lomé</span></div>
        <div><p class="site-kicker">Réseau international</p><h2>Présents là où vos échanges commencent et se terminent.</h2><p>Nos équipes et partenaires relient les grands hubs d’approvisionnement aux corridors d’Afrique de l’Ouest.</p><a class="site-text-link" href="<?= View::url('site/agences') ?>">Explorer nos implantations →</a></div>
    </section>

    <section class="site-home-section">
        <?= Site::sectionHeading('Communauté import-export', 'Apprendre de ceux qui expédient vraiment.', 'Questions de douane, choix fournisseurs et retours d’expérience entre professionnels.', '<a class="site-text-link" href="' . View::url('site/forum') . '">Rejoindre les discussions →</a>') ?>
        <?= Site::topics($page->topics, 3) ?>
    </section>

    <section class="site-final-cta"><p class="site-kicker">Un projet en tête ?</p><h2>Transformons votre prochaine expédition en avantage commercial.</h2><div><a class="site-cta site-cta--primary" href="<?= View::url('site/devis') ?>">Obtenir une estimation <span>→</span></a><a class="site-cta site-cta--ghost" href="<?= View::url('site/contact') ?>">Parler à un conseiller</a></div></section>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/site.php';
