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
    <section class="site-account-auth" style="max-width:1000px; margin:0 auto; padding:40px 0;">
        <div style="text-align:center; margin-bottom:40px;">
            <span style="display:inline-flex; align-items:center; gap:6px; background:rgba(37,99,235,0.1); color:#2563eb; border:1px solid #bfdbfe; padding:4px 14px; border-radius:20px; font-size:0.8rem; font-weight:700; text-transform:uppercase; margin-bottom:12px;">
                Espace Client Sécurisé LBP
            </span>
            <h1 style="font-size:2.2rem; font-weight:800; color:#0f172a; margin:0 0 10px 0;">Accédez à vos expéditions & échanges en direct.</h1>
            <p style="color:#64748b; font-size:1rem; margin:0;">Connectez-vous ou créez votre compte gratuit pour suivre l'intégralité de vos colis et contacter votre gestionnaire dédié.</p>
        </div>

        <div class="site-account-auth__forms" style="display:grid; grid-template-columns:1fr 1fr; gap:30px;">
            <!-- Login Form -->
            <form method="post" action="<?= View::url('site/account/login') ?>" style="background:#ffffff; border-radius:18px; border:1px solid #e2e8f0; padding:30px; box-shadow:0 10px 25px -5px rgba(15,23,42,0.05);">
                <?= Form::hidden('_csrf_token', $page->csrfToken) ?>
                <h2 style="font-size:1.3rem; font-weight:800; color:#0f172a; margin-top:0; margin-bottom:20px;">Connexion Espace Client</h2>
                <div style="margin-bottom:14px;">
                    <?= Form::input('email', ['label' => 'Adresse Email', 'type' => 'email', 'required' => true, 'placeholder' => 'votre@email.com']) ?>
                </div>
                <div style="margin-bottom:20px;">
                    <?= Form::input('password', ['label' => 'Mot de passe', 'type' => 'password', 'required' => true, 'placeholder' => '••••••••']) ?>
                </div>
                <?= Ui::button('Se connecter ➔', ['variant' => 'primary', 'type' => 'submit', 'style' => 'width:100%; padding:12px; font-weight:700; border-radius:10px;']) ?>
            </form>

            <!-- Register Form -->
            <form method="post" action="<?= View::url('site/account/register') ?>" style="background:#ffffff; border-radius:18px; border:1px solid #e2e8f0; padding:30px; box-shadow:0 10px 25px -5px rgba(15,23,42,0.05);">
                <?= Form::hidden('_csrf_token', $page->csrfToken) ?>
                <h2 style="font-size:1.3rem; font-weight:800; color:#0f172a; margin-top:0; margin-bottom:20px;">Créer un compte client</h2>
                <div style="margin-bottom:12px;">
                    <?= Form::input('full_name', ['label' => 'Nom complet / Entreprise', 'required' => true, 'placeholder' => 'Ex: Jean Dupont']) ?>
                </div>
                <div style="margin-bottom:12px;">
                    <?= Form::input('email', ['label' => 'Adresse Email', 'type' => 'email', 'required' => true, 'placeholder' => 'jean@exemple.com']) ?>
                </div>
                <div style="margin-bottom:12px;">
                    <?= Form::input('phone', ['label' => 'Téléphone / WhatsApp', 'placeholder' => '+225 07 00 00 00 00']) ?>
                </div>
                <div style="margin-bottom:20px;">
                    <?= Form::input('password', ['label' => 'Mot de passe (8 car. min)', 'type' => 'password', 'required' => true, 'minlength' => 8, 'placeholder' => '••••••••']) ?>
                </div>
                <?= Ui::button('Créer mon compte gratuit ➔', ['variant' => 'accent', 'type' => 'submit', 'style' => 'width:100%; padding:12px; font-weight:700; border-radius:10px;']) ?>
            </form>
        </div>
    </section>
<?php else: ?>
    <!-- Authenticated Dashboard -->
    <section class="site-customer-hero" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-radius: 20px; padding: 35px 40px; color: #ffffff; margin-bottom: 25px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:20px; box-shadow:0 20px 40px -15px rgba(15,23,42,0.35);">
        <div>
            <span style="display:inline-flex; align-items:center; gap:6px; background:rgba(37,99,235,0.2); color:#60a5fa; border:1px solid rgba(96,165,250,0.3); padding:4px 12px; border-radius:20px; font-size:0.8rem; font-weight:700; text-transform:uppercase; margin-bottom:10px;">
                Compte Client Actif
            </span>
            <h1 style="color:#ffffff; font-size:2rem; font-weight:800; margin:0 0 6px 0;">Bienvenue, <?= View::e((string)$page->customer['full_name']) ?> !</h1>
            <p style="color:#94a3b8; font-size:0.95rem; margin:0;">Accédez à l'ensemble de vos expéditions enregistrées et à votre fil de discussion avec l'équipe LBP.</p>
        </div>
        <a href="<?= View::url('site/account/logout') ?>" style="background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2); color:#ffffff; font-weight:700; padding:10px 20px; border-radius:10px; text-decoration:none; font-size:0.88rem;">
            Se déconnecter
        </a>
    </section>

    <!-- KPIs grid -->
    <section class="site-customer-kpis" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:20px; margin-bottom:30px;">
        <article style="background:#ffffff; border-radius:16px; border:1px solid #e2e8f0; padding:22px; box-shadow:0 10px 25px -5px rgba(15,23,42,0.05);">
            <span style="font-size:0.8rem; color:#64748b; font-weight:700; text-transform:uppercase; display:block;">Colis Enregistrés ERP</span>
            <strong style="font-size:1.8rem; color:#2563eb; font-weight:800; display:block; margin:4px 0;"><?= count($page->parcels) ?></strong>
            <small style="color:#64748b; font-size:0.78rem;">Associés à <?= View::e((string)$page->customer['email']) ?></small>
        </article>
        <article style="background:#ffffff; border-radius:16px; border:1px solid #e2e8f0; padding:22px; box-shadow:0 10px 25px -5px rgba(15,23,42,0.05);">
            <span style="font-size:0.8rem; color:#64748b; font-weight:700; text-transform:uppercase; display:block;">Panier Sélection</span>
            <strong data-account-cart-count style="font-size:1.8rem; color:#059669; font-weight:800; display:block; margin:4px 0;">0 article</strong>
            <small style="color:#64748b; font-size:0.78rem;">Sauvegardé sur cet appareil</small>
        </article>
        <article style="background:#ffffff; border-radius:16px; border:1px solid #e2e8f0; padding:22px; box-shadow:0 10px 25px -5px rgba(15,23,42,0.05);">
            <span style="font-size:0.8rem; color:#64748b; font-weight:700; text-transform:uppercase; display:block;">Support / Chat</span>
            <strong style="font-size:1.8rem; color:#d97706; font-weight:800; display:block; margin:4px 0;"><?= View::e(strtoupper((string) ($page->conversation['status'] ?? 'OUVERTE'))) ?></strong>
            <small style="color:#64748b; font-size:0.78rem;">Ligne directe avec un conseiller</small>
        </article>
    </section>

    <!-- Client Parcels Table Section -->
    <section style="background:#ffffff; border-radius:18px; border:1px solid #e2e8f0; padding:28px; margin-bottom:30px; box-shadow:0 10px 25px -5px rgba(15,23,42,0.05);">
        <h2 style="font-size:1.25rem; font-weight:800; color:#0f172a; margin-top:0; margin-bottom:20px; display:flex; align-items:center; gap:10px;">
            <svg viewBox="0 0 24 24" width="22" height="22" stroke="#2563eb" stroke-width="2.2" fill="none"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            Mes Colis & Expéditions ERP en Temps Réel
        </h2>

        <?php if (empty($page->parcels)): ?>
            <div style="background:#f8fafc; border:1px dashed #cbd5e1; padding:30px; border-radius:12px; text-align:center;">
                <p style="color:#64748b; margin:0 0 12px 0;">Aucun colis enregistré sous votre email (<code><?= View::e((string)$page->customer['email']) ?></code>) pour le moment.</p>
                <a href="<?= View::url('site/tracking') ?>" style="background:#2563eb; color:#ffffff; font-weight:700; padding:10px 20px; border-radius:8px; text-decoration:none; display:inline-block; font-size:0.88rem;">Suivre un colis par N° de tracking ➔</a>
            </div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; text-align:left; font-size:0.9rem;">
                    <thead>
                        <tr style="background:#f8fafc; border-bottom:2px solid #e2e8f0; color:#475569; font-size:0.8rem; text-transform:uppercase;">
                            <th style="padding:12px 16px; font-weight:800;">N° Tracking</th>
                            <th style="padding:12px 16px; font-weight:800;">Trajet</th>
                            <th style="padding:12px 16px; font-weight:800;">Date Enregistrement</th>
                            <th style="padding:12px 16px; font-weight:800;">Statut ERP</th>
                            <th style="padding:12px 16px; font-weight:800; text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($page->parcels as $p): ?>
                            <tr style="border-bottom:1px solid #f1f5f9;">
                                <td style="padding:14px 16px;">
                                    <code style="font-family:Consolas, monospace; font-weight:700; color:#1d4ed8; background:#eff6ff; border:1px solid #bfdbfe; padding:4px 10px; border-radius:6px;">
                                        <?= View::e((string)$p['numero_tracking']) ?>
                                    </code>
                                </td>
                                <td style="padding:14px 16px; font-weight:600; color:#0f172a;">
                                    <?= View::e((string)($p['agence_depart_name'] ?? 'Agence départ')) ?> ➔ <?= View::e((string)($p['agence_arrivee_name'] ?? 'Agence arrivée')) ?>
                                </td>
                                <td style="padding:14px 16px; color:#64748b;">
                                    <?= View::e(date('d/m/Y H:i', strtotime((string)$p['created_at']))) ?>
                                </td>
                                <td style="padding:14px 16px;">
                                    <span style="display:inline-flex; align-items:center; gap:4px; padding:4px 12px; border-radius:20px; font-size:0.78rem; font-weight:800; background:#dbeafe; color:#1e40af; border:1px solid #93c5fd;">
                                        <?= View::e(strtoupper((string)$p['statut'])) ?>
                                    </span>
                                </td>
                                <td style="padding:14px 16px; text-align:right;">
                                    <a href="<?= View::url('site/tracking') ?>?ref=<?= urlencode((string)$p['numero_tracking']) ?>" style="background:#2563eb; color:#ffffff; font-weight:700; padding:6px 14px; border-radius:8px; text-decoration:none; font-size:0.82rem; display:inline-flex; align-items:center; gap:4px;">
                                        <span>Carte Live</span>
                                        <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" stroke-width="2.5" fill="none"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <!-- Support Chat Workspace -->
    <section id="assistance" class="site-customer-workspace" style="background:#ffffff; border-radius:18px; border:1px solid #e2e8f0; padding:28px; box-shadow:0 10px 25px -5px rgba(15,23,42,0.05);">
        <aside style="margin-bottom:20px;">
            <p class="site-kicker" style="color:#2563eb; font-weight:700; margin:0 0 4px 0;">Assistance Privée Directe</p>
            <h2 style="font-size:1.3rem; font-weight:800; color:#0f172a; margin:0 0 8px 0;">Parlez en direct à votre conseiller LBP.</h2>
            <p style="color:#64748b; font-size:0.9rem; margin:0;">Vous pouvez joindre des photos de colis, documents PDF ou enregistrer une note vocale.</p>
        </aside>
        
        <div class="site-chat" data-chat data-feed-url="<?= View::url('site/account/messages') ?>">
            <?= SiteChat::messages($page->messages, 'customer') ?>
            <form method="post" enctype="multipart/form-data" action="<?= View::url('site/account/messages') ?>" data-chat-form style="margin-top:20px;">
                <?= Form::hidden('_csrf_token', $page->csrfToken) ?>
                <?= Form::textarea('message', ['label' => 'Votre message', 'rows' => 3, 'placeholder' => 'Posez une question ou demandez un suivi sur votre dossier...']) ?>
                <div style="margin-top:10px;"><?= Form::dropzone('attachment', 'Joindre un média', ['accept' => 'image/jpeg,image/png,image/webp,video/mp4,video/webm,audio/mpeg,audio/ogg,audio/webm,audio/mp4', 'hint' => 'Image, vidéo ou note vocale · 20 Mo max.']) ?></div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:14px; flex-wrap:wrap; gap:10px;">
                    <button class="site-voice-button" type="button" data-voice-record style="background:#f1f5f9; border:1px solid #cbd5e1; color:#334155; padding:8px 16px; border-radius:8px; font-weight:600; cursor:pointer;">🎤 Note vocale</button>
                    <?= Ui::button('Envoyer le message ➔', ['variant' => 'primary', 'type' => 'submit']) ?>
                </div>
            </form>
        </div>
    </section>
<?php endif; ?>
</div>

<?php
$content = ob_get_clean();
$page = $sitePage;
require BASE_PATH . '/views/layouts/site.php';
