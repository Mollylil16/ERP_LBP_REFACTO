<?php
use App\Helpers\View;
ob_start();
?>
<section class="site-hero">
    <div>
        <p class="finea-eyebrow">Transit • Import-export • Logistique internationale</p>
        <h1>Votre partenaire transit pour suivre, dédouaner et livrer vos marchandises.</h1>
        <p>Site public connecté à l’ERP : publicité, demandes de devis, suivi colis, agences et services configurables depuis le module Site internet.</p>
        <div class="site-actions">
            <a href="#tracking" class="finea-action-btn finea-action-btn--accent">Suivre un colis</a>
            <a href="#contact" class="finea-action-btn finea-action-btn--secondary">Demander un devis</a>
        </div>
    </div>
    <aside class="site-card">
        <strong>Tracking colis</strong>
        <form id="tracking" method="post" action="#">
            <input class="finea-input" placeholder="Référence colis / BL / dossier" aria-label="Référence de suivi">
            <button class="finea-action-btn finea-action-btn--primary" type="button">Rechercher</button>
        </form>
        <small>Le branchement réel se fera sur les dossiers colisage/logistique.</small>
    </aside>
</section>
<section class="site-grid">
    <?php foreach ([['Dédouanement','Formalités import-export et suivi documentaire.'],['Fret & transport','Organisation enlèvement, acheminement et livraison.'],['Suivi digital','Tracking, notifications client et portail web.']] as $item): ?>
        <article class="finea-section-card"><h2><?= View::e($item[0]) ?></h2><p><?= View::e($item[1]) ?></p></article>
    <?php endforeach; ?>
</section>
<?php
$content=ob_get_clean();
require BASE_PATH . '/views/layouts/site.php';
