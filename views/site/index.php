<?php

use App\Helpers\View;
use App\View\Components\Site;
use App\View\Pages\Site\SitePage;

/** @var SitePage $page */

ob_start();
?>
<?= Site::carousel($page->slides) ?>

<div class="site-content">

    <!-- Dock Suivre mon Colis -->
    <section class="site-tracking-dock" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-radius: 18px; padding: 32px 36px; color: #fff; margin: 30px 0; box-shadow: 0 20px 40px -10px rgba(15, 23, 42, 0.3);">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
            <div>
                <span style="display: inline-flex; align-items: center; gap: 6px; background: rgba(37, 99, 235, 0.2); color: #60a5fa; border: 1px solid rgba(96, 165, 250, 0.3); padding: 4px 14px; border-radius: 20px; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px;">
                    <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" stroke-width="2" fill="none"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                    Suivi Temps Réel LBP
                </span>
                <h2 style="font-size: 1.6rem; font-weight: 800; color: #fff; margin: 8px 0 4px 0;">Où se trouve votre expédition ?</h2>
                <p style="color: #94a3b8; font-size: 0.95rem; margin: 0;">Entrez votre numéro de tracking (ex: <code>LB-CI-001</code>) pour suivre son acheminement.</p>
            </div>

            <form method="get" action="<?= View::url('site/tracking') ?>" style="display: flex; gap: 10px; flex-wrap: wrap; flex: 1; max-width: 500px;">
                <input type="text" name="ref" value="<?= View::e($page->defaultShipment) ?>" placeholder="Saisissez votre code colis..." required style="flex: 1; min-width: 220px; padding: 14px 18px; border-radius: 10px; border: 1px solid #334155; background: #0f172a; color: #fff; font-size: 1rem; outline: none;">
                <button type="submit" style="background: #2563eb; color: #fff; border: none; padding: 14px 24px; border-radius: 10px; font-weight: 700; font-size: 1rem; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
                    <span>Suivre</span>
                    <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </button>
            </form>
        </div>
    </section>

    <!-- Partenaires & Hubs -->
    <section class="site-trust-strip" style="background: #ffffff; border-radius: 14px; padding: 20px 28px; border: 1px solid #e2e8f0; margin-bottom: 30px;">
        <span style="font-size: 0.85rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Réseau & Partenaires International</span>
        <strong>AFRICA MEDICAL</strong>
        <strong>KAB TRANSIT</strong>
        <strong>NOVA RETAIL</strong>
        <strong>WEST AFRICA TRADE</strong>
        <strong>CI INDUSTRIES</strong>
    </section>

    <?= Site::stats($page->stats) ?>

    <!-- Solutions de Fret -->
    <section id="services" class="site-home-section">
        <?= Site::sectionHeading('Solutions intégrées', 'Tout le commerce international, dans une seule chaîne.', 'De l’achat fournisseur à la livraison finale, chaque étape est coordonnée et visible.') ?>
        <?= Site::services($page->services) ?>
    </section>

    <!-- Bannière Marketplace B2B -->
    <section class="site-commerce-banner" style="background: linear-gradient(135deg, #1d2b57 0%, #0f172a 100%); border-radius: 20px; padding: 40px; color: #fff;">
        <div>
            <p class="site-kicker" style="color: #60a5fa; font-weight: 700;">Marketplace B2B & Fret</p>
            <h2 style="font-size: 2rem; color: #fff; margin-bottom: 12px;">Achetez plus que du transport.</h2>
            <p style="color: #94a3b8; max-width: 550px; margin-bottom: 24px;">Réservez du groupage maritime ou aérien, sécurisez vos marchandises et commandez les emballages nécessaires.</p>
            <a class="site-cta site-cta--primary" href="<?= View::url('site/shop') ?>" style="background: #2563eb; color: #fff; padding: 12px 24px; border-radius: 10px; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 8px;">
                <span>Voir toute la marketplace</span>
                <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </a>
        </div>
        <aside style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); border-radius: 14px; padding: 24px; color: #fff;">
            <span style="color: #60a5fa; font-weight: 700; font-size: 0.85rem; text-transform: uppercase;">Chine & World → Afrique</span>
            <strong style="display: block; font-size: 1.3rem; margin: 6px 0;">Départs chaque semaine</strong>
            <small style="color: #94a3b8;">Groupage maritime (FCL/LCL) et Aérien Express</small>
        </aside>
    </section>

    <!-- Offres Marketplace -->
    <section class="site-home-section">
        <?= Site::sectionHeading('Sélection professionnelle', 'Les offres les plus demandées.', 'Une expérience e-commerce adaptée aux services de transit.', '<a class="site-text-link" href="' . View::url('site/shop') . '">Tout afficher →</a>') ?>
        <?= Site::products($page->products, 4) ?>
    </section>

    <!-- Communauté & Forum -->
    <section class="site-home-section">
        <?= Site::sectionHeading('Communauté import-export', 'Apprendre de ceux qui expédient vraiment.', 'Questions de douane, choix fournisseurs et retours d’expérience entre professionnels.', '<a class="site-text-link" href="' . View::url('site/forum') . '">Rejoindre les discussions →</a>') ?>
        <?= Site::topics($page->topics, 3) ?>
    </section>

    <!-- CTA Final -->
    <section class="site-final-cta" style="text-align: center; padding: 50px 20px; background: #ffffff; border-radius: 20px; border: 1px solid #e2e8f0; margin-top: 40px;">
        <p class="site-kicker" style="color: #2563eb; font-weight: 700;">Un projet d'expédition ?</p>
        <h2 style="font-size: 2rem; color: #0f172a; margin: 10px 0 24px 0;">Transformons votre prochaine expédition en avantage commercial.</h2>
        <div style="display: flex; gap: 14px; justify-content: center; flex-wrap: wrap;">
            <a class="site-cta site-cta--primary" href="<?= View::url('site/devis') ?>" style="background: #2563eb; color: #fff; padding: 14px 28px; border-radius: 10px; font-weight: 700; text-decoration: none;">Obtenir une estimation ➔</a>
            <a class="site-cta site-cta--ghost" href="<?= View::url('site/contact') ?>" style="background: #f1f5f9; color: #0f172a; padding: 14px 28px; border-radius: 10px; font-weight: 600; text-decoration: none;">Parler à un conseiller</a>
        </div>
    </section>

</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/site.php';
