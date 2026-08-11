<?php

use App\Helpers\View;
use App\View\Components\Site;
use App\View\Pages\Site\SitePage;

/** @var SitePage $page */

ob_start();
?>
<div class="site-content">
    
    <!-- Hero Section -->
    <section class="site-page-hero" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-radius: 20px; padding: 35px 40px; color: #ffffff; margin-bottom: 25px; box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.35);">
        <span style="display:inline-flex; align-items:center; gap:6px; background:rgba(37,99,235,0.2); color:#60a5fa; border:1px solid rgba(96,165,250,0.3); padding:4px 12px; border-radius:20px; font-size:0.8rem; font-weight:700; text-transform:uppercase; margin-bottom:12px;">
            <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" stroke-width="2.5" fill="none"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            Forum Import-Export & Logistics
        </span>
        <h1 style="color:#ffffff; font-size:2.2rem; font-weight:800; margin:0 0 10px 0;">Communauté d'Échange LBP Transit</h1>
        <p style="color:#94a3b8; font-size:1rem; margin:0 0 20px 0;">Posez vos questions sur la douane, les transports maritimes/aériens et partagez vos retours d'expérience avec les experts du réseau.</p>

        <button type="button" onclick="document.getElementById('modal-new-topic').style.display='flex';" style="background:#2563eb; color:#ffffff; border:none; padding:12px 24px; border-radius:10px; font-weight:700; font-size:0.95rem; cursor:pointer; display:inline-flex; align-items:center; gap:8px; box-shadow:0 4px 14px rgba(37,99,235,0.4);">
            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2.5" fill="none"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
            <span>Poser une nouvelle question</span>
        </button>
    </section>

    <!-- Categories Grid -->
    <section style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:30px;">
        <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:18px; box-shadow:0 4px 12px rgba(0,0,0,0.03);">
            <div style="width:36px; height:36px; border-radius:10px; background:#eff6ff; color:#2563eb; display:flex; align-items:center; justify-content:center; margin-bottom:10px;">
                <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
            </div>
            <strong style="font-size:1rem; color:#0f172a; display:block;">Fret Europe & Transit</strong>
            <span style="font-size:0.82rem; color:#64748b;">Envois France ➔ Afrique, dédouanement et suivi colis.</span>
        </div>
        <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:18px; box-shadow:0 4px 12px rgba(0,0,0,0.03);">
            <div style="width:36px; height:36px; border-radius:10px; background:#ecfdf5; color:#059669; display:flex; align-items:center; justify-content:center; margin-bottom:10px;">
                <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </div>
            <strong style="font-size:1rem; color:#0f172a; display:block;">Douane & Réglementation</strong>
            <span style="font-size:0.82rem; color:#64748b;">Dédouanement, droits de douane, taxes et certificats.</span>
        </div>
        <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:18px; box-shadow:0 4px 12px rgba(0,0,0,0.03);">
            <div style="width:36px; height:36px; border-radius:10px; background:#fef3c7; color:#d97706; display:flex; align-items:center; justify-content:center; margin-bottom:10px;">
                <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none"><path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.7 5.2c.3.4.8.5 1.3.3l.5-.3c.4-.2.6-.6.5-1.1z"/></svg>
            </div>
            <strong style="font-size:1rem; color:#0f172a; display:block;">Corridors & Transport</strong>
            <span style="font-size:0.82rem; color:#64748b;">Fret Aérien, Groupage Maritime & Suivi colis.</span>
        </div>
    </section>

    <!-- Discussions List Header -->
    <section class="site-forum-toolbar" style="margin-bottom:20px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:15px;">
        <div>
            <h2 style="font-size:1.4rem; font-weight:800; color:#0f172a; margin:0;">Sujets & Discussions Récentes</h2>
            <span style="color:#64748b; font-size:0.88rem;"><?= count($page->topics) ?> sujet(s) publiés par la communauté</span>
        </div>
    </section>

    <!-- Forum Topics -->
    <?= Site::topics($page->topics) ?>

</div>

<!-- Modal Creation de Sujet -->
<div id="modal-new-topic" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); backdrop-filter:blur(6px); z-index:999; align-items:center; justify-content:center; padding:20px;">
    <div style="background:#ffffff; border-radius:20px; max-width:550px; width:100%; padding:30px; box-shadow:0 20px 40px rgba(0,0,0,0.25); border:1px solid #e2e8f0; position:relative;">
        <button type="button" onclick="document.getElementById('modal-new-topic').style.display='none';" style="position:absolute; top:20px; right:20px; background:transparent; border:none; font-size:1.5rem; color:#64748b; cursor:pointer;">&times;</button>
        
        <h3 style="font-size:1.3rem; font-weight:800; color:#0f172a; margin:0 0 6px 0;">Poser une question à la communauté</h3>
        <p style="color:#64748b; font-size:0.88rem; margin:0 0 20px 0;">Obtenez des conseils d'experts logistiques et d'autres professionnels du commerce.</p>

        <form method="post" action="<?= View::url('site/forum/new') ?>">
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:0.8rem; font-weight:700; color:#475569; margin-bottom:6px;">CATÉGORIE DU SUJET</label>
                <select name="category" style="width:100%; padding:11px 14px; border-radius:10px; border:1px solid #cbd5e1; font-size:0.9rem; outline:none; background:#f8fafc; font-weight:600;">
                    <option value="Douane & Transit">Douane & Transit</option>
                    <option value="Douane & Conformité">Douane & Conformité</option>
                    <option value="Transport & Logistique">Transport & Logistique</option>
                    <option value="Retours d'expérience">Retours d'expérience</option>
                </select>
            </div>
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:0.8rem; font-weight:700; color:#475569; margin-bottom:6px;">VOTRE NOM / PSEUDO</label>
                <input type="text" name="author_name" placeholder="Ex: Marc D., Importateur Abidjan" required style="width:100%; padding:11px 14px; border-radius:10px; border:1px solid #cbd5e1; font-size:0.9rem; outline:none; background:#f8fafc;">
            </div>
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:0.8rem; font-weight:700; color:#475569; margin-bottom:6px;">TITRE DU SUJET</label>
                <input type="text" name="title" placeholder="Ex: Quel est le délai moyen pour un dédouanement à Abidjan ?" required style="width:100%; padding:11px 14px; border-radius:10px; border:1px solid #cbd5e1; font-size:0.9rem; outline:none; background:#f8fafc;">
            </div>
            <div style="margin-bottom:20px;">
                <label style="display:block; font-size:0.8rem; font-weight:700; color:#475569; margin-bottom:6px;">VOTRE QUESTION / MESSAGE DÉTAILLÉ</label>
                <textarea name="content" rows="4" placeholder="Expliquez en détails votre question ou situation..." required style="width:100%; padding:11px 14px; border-radius:10px; border:1px solid #cbd5e1; font-size:0.9rem; outline:none; background:#f8fafc; resize:vertical;"></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="document.getElementById('modal-new-topic').style.display='none';" style="background:#f1f5f9; color:#475569; border:none; padding:10px 20px; border-radius:10px; font-weight:600; cursor:pointer;">Annuler</button>
                <button type="submit" style="background:#2563eb; color:#ffffff; border:none; padding:10px 24px; border-radius:10px; font-weight:700; cursor:pointer;">Publier le sujet</button>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/site.php';
