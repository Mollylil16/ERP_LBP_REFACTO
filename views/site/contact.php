<?php
use App\Helpers\View;
ob_start();
?>
<section class="site-page-hero site-page-hero--contact"><p class="finea-eyebrow">Contact</p><h1>Parlez à un conseiller transit LBP.</h1><p>Assistance client, suivi dossier, demande commerciale ou orientation vers une agence.</p></section>
<section class="site-form-layout"><form class="site-form-card" method="post" action="#"><div class="site-form-grid"><input class="finea-input" placeholder="Nom complet"><input class="finea-input" placeholder="Email"><input class="finea-input" placeholder="Téléphone"><select class="finea-input"><option>Motif</option><option>Suivi colis</option><option>Devis</option><option>Réclamation</option><option>Partenariat</option></select></div><textarea class="finea-input" rows="5" placeholder="Votre message..."></textarea><button class="finea-action-btn finea-action-btn--primary" type="button">Envoyer le message test</button></form><aside class="site-contact-panel"><?php foreach (array_slice($agencies ?? [], 0, 3) as $agency): ?><article><strong><?= View::e($agency['name']) ?></strong><span><?= View::e($agency['phone']) ?></span><small><?= View::e($agency['email']) ?></small></article><?php endforeach; ?></aside></section>
<?php $content = ob_get_clean(); require BASE_PATH . '/views/layouts/site.php';
