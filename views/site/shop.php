<?php

use App\View\Components\Site;
use App\View\Pages\Site\SitePage;

/** @var SitePage $page */

ob_start();
?>
<div class="site-content">
    <?= Site::pageHero('Marketplace logistique', 'Les bons produits et services pour expédier sans improviser.', 'Équipements, prestations transit, assurance et réservations de transport réunis dans un catalogue professionnel.') ?>
    <section class="site-shop-toolbar"><div><strong><?= count($page->products) ?> offre(s)</strong><span>Prix indicatifs, confirmation finale par un conseiller</span></div><div><button type="button" class="is-active">Toutes</button><button type="button">Transport</button><button type="button">Formalités</button><button type="button">Emballage</button></div></section>
    <?= Site::products($page->products) ?>
    <section class="site-shop-assurance"><strong>Paiement et commande bientôt disponibles en ligne</strong><p>La première version fonctionne comme un catalogue assisté : ajoutez vos besoins puis transmettez-les à un conseiller.</p></section>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/site.php';
