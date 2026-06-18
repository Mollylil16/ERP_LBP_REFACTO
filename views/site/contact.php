<?php
use App\Helpers\View;
use App\View\Components\Form;
use App\View\Components\Ui;

/** @var \App\Support\ViewBag $viewData */ $viewData ??= \App\Support\ViewBag::from(get_defined_vars());
$agencies = $agencies ?? [];

ob_start();
?>
<section class="site-page-hero site-page-hero--contact">
    <p class="finea-eyebrow">Contact</p>
    <h1>Parlez à un conseiller transit LBP.</h1>
    <p>Assistance client, suivi dossier, demande commerciale ou orientation vers une agence.</p>
</section>

<section class="site-form-layout">
    <form class="site-form-card" method="post" action="#">
        <div class="site-form-grid">
            <?= Form::input('full_name', ['label' => 'Nom complet', 'placeholder' => 'Nom complet']) ?>
            <?= Form::input('email', ['label' => 'Email', 'type' => 'email', 'placeholder' => 'Email']) ?>
            <?= Form::input('phone', ['label' => 'Téléphone', 'placeholder' => 'Téléphone']) ?>
            <?= Form::selectSearch('reason', [
                ['value' => '', 'label' => 'Motif'],
                ['value' => 'tracking', 'label' => 'Suivi colis'],
                ['value' => 'quote', 'label' => 'Devis'],
                ['value' => 'claim', 'label' => 'Réclamation'],
                ['value' => 'partnership', 'label' => 'Partenariat'],
            ], '', ['label' => 'Motif']) ?>
        </div>
        <?= Form::textarea('message', ['label' => 'Message', 'rows' => 5, 'placeholder' => 'Votre message...']) ?>
        <?= Ui::button('Envoyer le message test', ['variant' => 'primary', 'type' => 'button']) ?>
    </form>

    <aside class="site-contact-panel">
        <?php foreach (array_slice($agencies, 0, 3) as $agency): ?>
            <article>
                <strong><?= View::e($agency['name'] ?? '') ?></strong>
                <span><?= View::e($agency['phone'] ?? '') ?></span>
                <small><?= View::e($agency['email'] ?? '') ?></small>
            </article>
        <?php endforeach; ?>
    </aside>
</section>
<?php $content = ob_get_clean(); require BASE_PATH . '/views/layouts/site.php';
