<?php

use App\Helpers\View;
use App\View\Pages\SiteAdmin\DashboardPage;

/** @var DashboardPage $page */
/** @var array $analytics */

$analytics = $analytics ?? [];
$summary = $analytics['summary'] ?? [];
$onlineVisitors = $analytics['online_visitors'] ?? [];
$trackingSearches = $analytics['tracking_searches'] ?? [];

ob_start();
?>
<style>
.site-dash-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    border-radius: 20px;
    padding: 35px 40px;
    color: #ffffff;
    margin-bottom: 28px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.4);
    border: 1px solid rgba(255, 255, 255, 0.08);
}
.site-dash-hero__glow {
    position: absolute;
    top: -60px;
    right: -60px;
    width: 250px;
    height: 250px;
    background: radial-gradient(circle, rgba(37, 99, 235, 0.3) 0%, rgba(0,0,0,0) 70%);
    border-radius: 50%;
    pointer-events: none;
}
.site-dash-hero__badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(16, 185, 129, 0.15);
    color: #34d399;
    border: 1px solid rgba(52, 211, 153, 0.3);
    padding: 6px 16px;
    border-radius: 30px;
    font-size: 0.82rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-bottom: 14px;
}
.site-dash-hero__title {
    font-size: 2.2rem;
    font-weight: 800;
    margin: 0 0 8px 0;
    letter-spacing: -0.5px;
}
.site-dash-hero__text {
    font-size: 1.05rem;
    color: #94a3b8;
    max-width: 650px;
    margin: 0 0 24px 0;
    line-height: 1.6;
}
.site-dash-hero__actions {
    display: flex;
    gap: 14px;
    flex-wrap: wrap;
}
.btn-dash-primary {
    background: #2563eb;
    color: #ffffff;
    padding: 12px 24px;
    border-radius: 10px;
    font-weight: 700;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4);
    transition: all 0.2s;
}
.btn-dash-primary:hover {
    background: #1d4ed8;
    transform: translateY(-2px);
    color: #ffffff;
}
.btn-dash-glass {
    background: rgba(255, 255, 255, 0.1);
    color: #ffffff;
    border: 1px solid rgba(255, 255, 255, 0.2);
    padding: 12px 24px;
    border-radius: 10px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    backdrop-filter: blur(8px);
    transition: all 0.2s;
}
.btn-dash-glass:hover {
    background: rgba(255, 255, 255, 0.18);
    color: #ffffff;
}

/* KPI Cards Grid */
.site-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 28px;
}
.site-kpi-card {
    background: #ffffff;
    border-radius: 16px;
    padding: 24px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.05);
    transition: all 0.25s ease;
    position: relative;
    overflow: hidden;
}
.site-kpi-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 35px -10px rgba(15, 23, 42, 0.12);
    border-color: #cbd5e1;
}
.site-kpi-card__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}
.site-kpi-card__icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.site-kpi-card__value {
    font-size: 2.2rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1;
    margin-bottom: 6px;
}
.site-kpi-card__label {
    font-size: 0.9rem;
    font-weight: 600;
    color: #475569;
}
.site-kpi-card__meta {
    font-size: 0.78rem;
    color: #94a3b8;
    margin-top: 4px;
}

/* Operations Grid */
.site-ops-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 28px;
}
.site-op-card {
    background: #ffffff;
    border-radius: 16px;
    padding: 24px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.05);
    display: flex;
    align-items: flex-start;
    gap: 18px;
    text-decoration: none;
    color: inherit;
    transition: all 0.25s ease;
}
.site-op-card:hover {
    transform: translateY(-3px);
    border-color: #2563eb;
    box-shadow: 0 15px 30px -5px rgba(37, 99, 235, 0.15);
}
.site-op-card__icon {
    width: 50px;
    height: 50px;
    border-radius: 14px;
    background: #eff6ff;
    color: #2563eb;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.site-op-card__title {
    font-size: 1.05rem;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 4px;
}
.site-op-card__text {
    font-size: 0.88rem;
    color: #64748b;
    line-height: 1.4;
}

/* Live Feed Table Section */
.site-feed-card {
    background: #ffffff;
    border-radius: 16px;
    padding: 26px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.05);
}
.site-feed-title {
    font-size: 1.15rem;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.live-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.78rem;
    color: #047857;
    background: #ecfdf5;
    padding: 4px 12px;
    border-radius: 20px;
    font-weight: 700;
}
.live-pulse-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #10b981;
    animation: pulse 1.5s infinite;
}
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
</style>

<div class="finea-shell">
<div class="finea-container">

    <!-- Hero Header -->
    <section class="site-dash-hero">
        <div class="site-dash-hero__glow"></div>
        <span class="site-dash-hero__badge">
            <span class="live-pulse-dot"></span>
            Site Vitrine LBP Synchronisé & Actif
        </span>
        <h1 class="site-dash-hero__title">Pilotage & Trafic du Site Internet</h1>
        <p class="site-dash-hero__text">Supervisez les visiteurs en direct, les recherches de colis public, publiez des annonces flash et mettez à jour le catalogue de fret.</p>

        <div class="site-dash-hero__actions">
            <a href="<?= View::url('site-admin/analytics') ?>" class="btn-dash-primary">
                <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2.5" fill="none"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                <span>Audience & Visiteurs en direct</span>
            </a>
            <a href="<?= View::url('site-admin/configuration#announcements') ?>" class="btn-dash-glass">
                <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                <span>Publier une Annonce / Promo</span>
            </a>
            <a href="<?= View::url('site') ?>" class="btn-dash-glass" target="_blank">
                <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                <span>Voir le site vitrine ➔</span>
            </a>
        </div>
    </section>

    <!-- 4 Impactful KPI Cards -->
    <div class="site-kpi-grid">
        <div class="site-kpi-card">
            <div class="site-kpi-card__top">
                <div class="site-kpi-card__icon" style="background: #ecfdf5; color: #059669;">
                    <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <span class="live-badge"><span class="live-pulse-dot"></span> EN DIRECT</span>
            </div>
            <div class="site-kpi-card__value"><?= (int)($summary['online_count'] ?? 0) ?></div>
            <div class="site-kpi-card__label">Visiteurs en direct</div>
            <div class="site-kpi-card__meta">Connectés les 15 dernières min</div>
        </div>

        <div class="site-kpi-card">
            <div class="site-kpi-card__top">
                <div class="site-kpi-card__icon" style="background: #eff6ff; color: #2563eb;">
                    <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </div>
                <span style="font-size: 0.78rem; font-weight: 700; color: #2563eb; background: #eff6ff; padding: 3px 8px; border-radius: 12px;">AUJOURD'HUI</span>
            </div>
            <div class="site-kpi-card__value"><?= count($trackingSearches) ?></div>
            <div class="site-kpi-card__label">Recherches Tracking Colis</div>
            <div class="site-kpi-card__meta">Consultations par les clients</div>
        </div>

        <div class="site-kpi-card">
            <div class="site-kpi-card__top">
                <div class="site-kpi-card__icon" style="background: #fffbebf; color: #d97706;">
                    <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/></svg>
                </div>
                <span style="font-size: 0.78rem; font-weight: 700; color: #d97706; background: #fffbebf; padding: 3px 8px; border-radius: 12px;">BANNIÈRES</span>
            </div>
            <div class="site-kpi-card__value">1</div>
            <div class="site-kpi-card__label">Annonces & Soldes Actives</div>
            <div class="site-kpi-card__meta">Offres de fret & promos en ligne</div>
        </div>

        <div class="site-kpi-card">
            <div class="site-kpi-card__top">
                <div class="site-kpi-card__icon" style="background: #f3e8ff; color: #9333ea;">
                    <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                </div>
                <span style="font-size: 0.78rem; font-weight: 700; color: #9333ea; background: #f3e8ff; padding: 3px 8px; border-radius: 12px;">MESSAGES</span>
            </div>
            <div class="site-kpi-card__value">0</div>
            <div class="site-kpi-card__label">Demandes & Formulaires</div>
            <div class="site-kpi-card__meta">Contacts clients enregistrés</div>
        </div>
    </div>

    <!-- Quick Operations Cards Grid -->
    <div style="margin-bottom: 12px; font-size: 1.1rem; font-weight: 800; color: #0f172a;">Opérations & Raccourcis Métier</div>
    <div class="site-ops-grid">
        <a href="<?= View::url('site-admin/analytics') ?>" class="site-op-card">
            <div class="site-op-card__icon">
                <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
            </div>
            <div>
                <div class="site-op-card__title">Audience & Visiteurs en direct</div>
                <div class="site-op-card__text">Visualiser les personnes connectées, leurs adresses IP, géolocalisation et pages vues.</div>
            </div>
        </a>

        <a href="<?= View::url('site-admin/configuration#announcements') ?>" class="site-op-card">
            <div class="site-op-card__icon" style="background: #fffbebf; color: #d97706;">
                <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/></svg>
            </div>
            <div>
                <div class="site-op-card__title">Publier une Annonce / Promo Flash</div>
                <div class="site-op-card__text">Poster une solde de fret maritime, une alerte agence ou une remise sur les emballages.</div>
            </div>
        </a>

        <a href="<?= View::url('site-admin/configuration') ?>" class="site-op-card">
            <div class="site-op-card__icon" style="background: #f0fdf4; color: #16a34a;">
                <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            </div>
            <div>
                <div class="site-op-card__title">Design & Carrousel d'accueil</div>
                <div class="site-op-card__text">Gérer le branding, le logo, les visuels d'en-tête et la palette de couleurs.</div>
            </div>
        </a>

        <a href="<?= View::url('site/shop') ?>" class="site-op-card">
            <div class="site-op-card__icon" style="background: #fdf2f8; color: #db2777;">
                <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
            </div>
            <div>
                <div class="site-op-card__title">Marketplace & Tarifs Fret</div>
                <div class="site-op-card__text">Contrôler la boutique en ligne, les offres de transport au m³ et les articles.</div>
            </div>
        </a>
    </div>

    <!-- Live Feed Table Section -->
    <section class="site-feed-card">
        <div class="site-feed-title">
            <span>Dernières Recherches Colis & Activités du Site</span>
            <span class="live-badge"><span class="live-pulse-dot"></span> SYNCHRONISÉ ERP</span>
        </div>

        <?php if (empty($trackingSearches)): ?>
            <p style="color: #64748b; font-size: 0.92rem; margin: 0; padding: 10px 0;">Aucune recherche récente enregistrée. Les consultations de colis effectuées par les clients s'afficheront ici en temps réel.</p>
        <?php else: ?>
            <div class="site-analytics-table">
                <table>
                    <thead>
                        <tr>
                            <th>Heure</th>
                            <th>Code Colis recherché</th>
                            <th>Statut dans l'ERP</th>
                            <th>Adresse IP Client</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($trackingSearches, 0, 5) as $row): ?>
                            <tr>
                                <td><strong><?= View::e(date('H:i:s', strtotime((string)$row['created_at']))) ?></strong></td>
                                <td><code style="background:#eff6ff; color:#1d4ed8; padding:3px 8px; border-radius:6px; font-weight:700;"><?= View::e((string)$row['tracking_ref']) ?></code></td>
                                <td>
                                    <span style="display:inline-block; padding:3px 10px; border-radius:12px; font-size:0.78rem; font-weight:700; background:#dbeafe; color:#1e40af;">
                                        <?= View::e(strtoupper((string)($row['statut'] ?: 'EN TRANSIT'))) ?>
                                    </span>
                                </td>
                                <td><?= View::e((string)$row['ip_address']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

</div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
