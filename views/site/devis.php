<?php
use App\View\Components\Form;
use App\View\Components\Ui;
use App\View\Pages\Site\SitePage;

/** @var SitePage $page */
ob_start();
?>
<section class="site-page-hero site-page-hero--quote">
    <p class="finea-eyebrow">Demande de devis</p>
    <h1>Recevez une estimation pour votre opération import-export.</h1>
    <p>Ce formulaire de démonstration est prêt à être branché au CRM comme lead entrant.</p>
</section>

<section class="site-form-layout">
    <form class="site-form-card" method="get" action="<?= \App\Helpers\View::url('site/devis') ?>">
        <div class="site-form-grid">
            <?= Form::input('customer_name', ['label' => 'Nom complet / entreprise', 'placeholder' => 'Nom complet / entreprise']) ?>
            <?= Form::input('phone', ['label' => 'Téléphone / WhatsApp', 'placeholder' => 'Téléphone / WhatsApp']) ?>
            <?= Form::input('origin_country', ['label' => 'Pays de départ', 'placeholder' => 'Pays de départ']) ?>
            <?= Form::input('destination_country', ['label' => 'Pays d’arrivée', 'placeholder' => 'Pays d’arrivée']) ?>
            <?= Form::selectSearch('operation_type', [
                ['value' => '', 'label' => 'Type d’opération'],
                ['value' => 'import', 'label' => 'Import'],
                ['value' => 'export', 'label' => 'Export'],
                ['value' => 'regional_transit', 'label' => 'Transit régional'],
            ], '', ['label' => 'Type d’opération']) ?>
            <?= Form::selectSearch('transport_mode', [
                ['value' => '', 'label' => 'Mode'],
                ['value' => 'sea', 'label' => 'Maritime'],
                ['value' => 'air', 'label' => 'Aérien'],
                ['value' => 'road', 'label' => 'Routier'],
            ], '', ['label' => 'Mode']) ?>
        </div>
        <?= Form::textarea('goods_description', [
            'label' => 'Description de la marchandise',
            'rows' => 5,
            'placeholder' => 'Décrivez la marchandise, volume, urgence, documents disponibles...',
        ]) ?>
        <?= Ui::button('Envoyer la demande test', ['variant' => 'accent', 'type' => 'button']) ?>
    </form>

    <aside class="site-card site-card--light">
        <strong>Données demandées ensuite</strong>
        <ul class="site-check-list">
            <li>Facture commerciale</li>
            <li>Liste de colisage</li>
            <li>BL / AWB si disponible</li>
            <li>Adresse d’enlèvement et de livraison</li>
        </ul>
    </aside>
</section>
<?php $content = ob_get_clean(); require BASE_PATH . '/views/layouts/site.php';
