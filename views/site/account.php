<?php

use App\Helpers\View;
use App\View\Components\Form;
use App\View\Components\SiteChat;
use App\View\Components\Ui;
use App\View\Pages\Site\CustomerAccountPage;

/** @var CustomerAccountPage $page */

$sitePage = $page->site;
ob_start();
?>
<div class="site-content">
<?php if (!$page->authenticated): ?>
    <section class="site-account-auth">
        <div>
            <p class="site-kicker">Espace client</p>
            <h1>Suivez vos demandes et échangez directement avec LBP.</h1>
            <p>Créez gratuitement votre compte public. Il reste séparé de l’accès ERP réservé aux équipes internes.</p>
        </div>
        <div class="site-account-auth__forms">
            <form method="post" action="<?= View::url('site/account/login') ?>">
                <?= Form::hidden('_csrf_token', $page->csrfToken) ?>
                <h2>Se connecter</h2>
                <?= Form::input('email', ['label' => 'Email', 'type' => 'email', 'required' => true]) ?>
                <?= Form::input('password', ['label' => 'Mot de passe', 'type' => 'password', 'required' => true]) ?>
                <?= Ui::button('Ouvrir mon espace', ['variant' => 'primary', 'type' => 'submit']) ?>
            </form>
            <form method="post" action="<?= View::url('site/account/register') ?>">
                <?= Form::hidden('_csrf_token', $page->csrfToken) ?>
                <h2>Créer un compte</h2>
                <?= Form::input('full_name', ['label' => 'Nom complet', 'required' => true]) ?>
                <?= Form::input('email', ['label' => 'Email', 'type' => 'email', 'required' => true]) ?>
                <?= Form::input('phone', ['label' => 'Téléphone / WhatsApp']) ?>
                <?= Form::input('password', ['label' => 'Mot de passe', 'type' => 'password', 'required' => true, 'minlength' => 8]) ?>
                <?= Ui::button('Créer gratuitement mon compte', ['variant' => 'accent', 'type' => 'submit']) ?>
            </form>
        </div>
    </section>
<?php else: ?>
    <section class="site-customer-hero">
        <div><p class="site-kicker">Bonjour <?= View::e(explode(' ', (string) $page->customer['full_name'])[0]) ?></p><h1>Votre espace client LBP.</h1><p>Centralisez votre panier, vos futures commandes, vos suivis et vos échanges avec un gestionnaire.</p></div>
        <a href="<?= View::url('site/account/logout') ?>">Se déconnecter</a>
    </section>
    <section class="site-customer-kpis">
        <article><span>Panier actuel</span><strong data-account-cart-count>0 article</strong><small>Conservé sur cet appareil</small></article>
        <article><span>Commandes</span><strong>0</strong><small>Paiement en ligne à venir</small></article>
        <article><span>Conversation</span><strong><?= View::e((string) ($page->conversation['status'] ?? 'ouverte')) ?></strong><small>Assistance gestionnaire</small></article>
    </section>
    <section id="assistance" class="site-customer-workspace">
        <aside><p class="site-kicker">Assistance privée</p><h2>Parlez à votre gestionnaire de site.</h2><p>Images, vidéos et notes vocales sont acceptées. Les fichiers restent hébergés localement sur le serveur LBP.</p><ul><li>Images : JPG, PNG, WEBP</li><li>Vidéos : MP4, WEBM</li><li>Audio : MP3, OGG, WEBM, M4A</li><li>20 Mo maximum par média</li></ul></aside>
        <div class="site-chat" data-chat data-feed-url="<?= View::url('site/account/messages') ?>">
            <?= SiteChat::messages($page->messages, 'customer') ?>
            <form method="post" enctype="multipart/form-data" action="<?= View::url('site/account/messages') ?>" data-chat-form>
                <?= Form::hidden('_csrf_token', $page->csrfToken) ?>
                <?= Form::textarea('message', ['label' => 'Votre message', 'rows' => 3, 'placeholder' => 'Écrivez au gestionnaire...']) ?>
                <div><?= Form::dropzone('attachment', 'Joindre un média', ['accept' => 'image/jpeg,image/png,image/webp,video/mp4,video/webm,audio/mpeg,audio/ogg,audio/webm,audio/mp4', 'hint' => 'Image, vidéo ou note vocale · 20 Mo max.']) ?></div>
                <button class="site-voice-button" type="button" data-voice-record>Enregistrer une note vocale</button>
                <?= Ui::button('Envoyer', ['variant' => 'primary', 'type' => 'submit']) ?>
            </form>
        </div>
    </section>
<?php endif; ?>
</div>
<?php
$content = ob_get_clean();
$page = $sitePage;
require BASE_PATH . '/views/layouts/site.php';
