<?php
use App\Helpers\View;
use App\View\Components\Form;
use App\View\Components\Ui;
ob_start();
$defaultShipment = array_key_first($shipments ?? []) ?: 'LBP-EXP-2026-00124';
function siteIcon(string $name): string {
    $icons = [
        'customs' => '<svg viewBox="0 0 24 24"><path d="M5 4h14v5c0 5.5-3 9-7 11-4-2-7-5.5-7-11V4Z"/><path d="M8 11h8M12 7v8"/></svg>',
        'freight' => '<svg viewBox="0 0 24 24"><path d="M3 16h18M6 16V8l6-3 6 3v8"/><path d="M8 16v3M16 16v3M9 10h6"/></svg>',
        'tracking' => '<svg viewBox="0 0 24 24"><path d="M12 21s7-5.1 7-12A7 7 0 1 0 5 9c0 6.9 7 12 7 12Z"/><circle cx="12" cy="9" r="2.4"/></svg>',
        'delivery' => '<svg viewBox="0 0 24 24"><path d="M3 7h11v10H3zM14 11h4l3 3v3h-7z"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/></svg>',
    ];
    return $icons[$name] ?? $icons['tracking'];
}
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
            <?= Form::input('ref', ['label' => 'Référence', 'value' => $defaultShipment, 'placeholder' => 'Référence colis / BL / dossier', 'aria-label' => 'Référence de suivi']) ?>
            <?= Ui::button('Rechercher', ['variant' => 'primary', 'type' => 'submit']) ?>
        </form>
        <small>Essayez : LBP-EXP-2026-00124, LBP-COL-2026-00087 ou BL-LBP-778245-CI.</small>
    </aside>
</section>
<section class="site-stats" aria-label="Indicateurs LBP Transit">
    <?php foreach (($stats ?? []) as $stat): ?>
        <article><strong><?= View::e($stat['value']) ?></strong><span><?= View::e($stat['label']) ?></span></article>
    <?php endforeach; ?>
</section>
<section class="site-section-head"><p class="finea-eyebrow">Services</p><h2>Une chaîne transit complète, du fournisseur au destinataire final.</h2></section>
<section class="site-grid site-grid--four">
    <?php foreach (($services ?? []) as $service): ?>
        <article class="site-service-card">
            <span class="site-service-card__icon"><?= siteIcon((string)$service['icon']) ?></span>
            <h3><?= View::e($service['title']) ?></h3>
            <p><?= View::e($service['text']) ?></p>
        </article>
    <?php endforeach; ?>
</section>
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
    <?php foreach (array_slice($agencies ?? [], 0, 4) as $agency): ?>
        <article><strong><?= View::e($agency['name']) ?></strong><span><?= View::e($agency['city']) ?>, <?= View::e($agency['country']) ?></span></article>
    <?php endforeach; ?>
</section>
<section class="site-section-head"><p class="finea-eyebrow">Actualités</p><h2>Informations et annonces opérationnelles.</h2></section>
<section class="site-grid site-grid--three">
    <?php foreach (($news ?? []) as $item): ?>
        <article class="finea-section-card site-news-card"><span><?= View::e($item['date']) ?></span><h3><?= View::e($item['title']) ?></h3><p>Contenu de démonstration administrable depuis le module Site internet.</p></article>
    <?php endforeach; ?>
</section>
<?php $content = ob_get_clean(); require BASE_PATH . '/views/layouts/site.php';
