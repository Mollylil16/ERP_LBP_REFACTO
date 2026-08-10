<?php

use App\Helpers\View;
use App\View\Components\Form;
use App\View\Components\Site;
use App\View\Pages\Site\SitePage;

/** @var SitePage $page */
ob_start();
$current = $page->currentShipment;

$originCoords = $current['origin_coords'] ?? [5.359951, -4.008256]; // Abidjan
$destCoords = $current['dest_coords'] ?? [48.856614, 2.352221];   // Paris
$transportMode = $current['transport_mode'] ?? 'plane';
$progressPct = (int) ($current['progress'] ?? 50);

$statusClass = match(strtoupper($current['status'] ?? '')) {
    'RETIRÉ', 'LIVRÉ' => 'status-delivered',
    'ARRIVÉ', 'RÉCEPTIONNÉ' => 'status-arrived',
    'EN_TRANSIT', 'EN TRANSIT' => 'status-transit',
    default => 'status-prep'
};
?>

<style>
/* Yango Live Tracking Styles */
.yango-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px 20px 60px 20px;
}
.yango-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    border-radius: 20px;
    padding: 35px 40px;
    color: #ffffff;
    margin-bottom: 25px;
    box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.35);
    border: 1px solid rgba(255, 255, 255, 0.08);
}
.yango-hero h1 {
    font-size: 2rem;
    font-weight: 800;
    margin: 0 0 10px 0;
    color: #ffffff;
}
.yango-hero p {
    color: #94a3b8;
    font-size: 1rem;
    margin: 0 0 24px 0;
}
.yango-searchbar {
    display: flex;
    gap: 12px;
    max-width: 650px;
    background: rgba(255, 255, 255, 0.1);
    padding: 8px;
    border-radius: 14px;
    border: 1px solid rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
}
.yango-searchbar input {
    flex: 1;
    background: transparent;
    border: none;
    padding: 12px 18px;
    color: #ffffff;
    font-size: 1rem;
    outline: none;
    font-weight: 600;
}
.yango-searchbar input::placeholder {
    color: #94a3b8;
}
.yango-searchbar button {
    background: #2563eb;
    color: #ffffff;
    border: none;
    padding: 12px 26px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.95rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4);
}
.yango-searchbar button:hover {
    background: #1d4ed8;
    transform: translateY(-1px);
}

/* Tracking Map Card */
.yango-map-card {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 15px 35px -10px rgba(15, 23, 42, 0.08);
    overflow: hidden;
    margin-bottom: 25px;
    position: relative;
}
.yango-map-header {
    padding: 20px 28px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #ffffff;
    flex-wrap: wrap;
    gap: 15px;
}
.yango-map-header__title {
    display: flex;
    align-items: center;
    gap: 12px;
}
.yango-map-header__title strong {
    font-size: 1.3rem;
    font-weight: 800;
    color: #0f172a;
    font-family: Consolas, Monaco, monospace;
}
.yango-live-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
    padding: 5px 14px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.yango-pulse {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #10b981;
    animation: yangoPulse 1.5s infinite;
}
@keyframes yangoPulse { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.4; transform: scale(1.3); } }

/* Leaflet Container */
#yango-live-map {
    height: 420px;
    width: 100%;
    background: #0f172a;
    z-index: 1;
}

/* Map Overlay Floating HUD */
.yango-hud {
    position: absolute;
    bottom: 20px;
    left: 20px;
    z-index: 10;
    background: rgba(15, 23, 42, 0.88);
    border: 1px solid rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(12px);
    border-radius: 14px;
    padding: 16px 22px;
    color: #ffffff;
    display: flex;
    align-items: center;
    gap: 20px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.3);
    max-width: calc(100% - 40px);
}
.yango-hud__speed {
    display: flex;
    align-items: center;
    gap: 12px;
}
.yango-hud__icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: #2563eb;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
}
.yango-hud__info strong {
    display: block;
    font-size: 0.95rem;
    font-weight: 700;
    color: #ffffff;
}
.yango-hud__info small {
    color: #94a3b8;
    font-size: 0.78rem;
}

/* Details Grid */
.yango-details-grid {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 25px;
}
@media (max-width: 900px) {
    .yango-details-grid {
        grid-template-columns: 1fr;
    }
}
.yango-card {
    background: #ffffff;
    border-radius: 18px;
    padding: 28px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.05);
}
.yango-card-title {
    font-size: 1.15rem;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Timeline */
.yango-timeline {
    position: relative;
    padding-left: 30px;
}
.yango-timeline::before {
    content: '';
    position: absolute;
    top: 5px;
    bottom: 5px;
    left: 10px;
    width: 3px;
    background: #e2e8f0;
    border-radius: 2px;
}
.yango-step {
    position: relative;
    margin-bottom: 24px;
}
.yango-step:last-child {
    margin-bottom: 0;
}
.yango-step-dot {
    position: absolute;
    left: -30px;
    top: 3px;
    width: 23px;
    height: 23px;
    border-radius: 50%;
    background: #ffffff;
    border: 3px solid #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
}
.yango-step--active .yango-step-dot {
    background: #10b981;
    border-color: #10b981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.25);
}
.yango-step-time {
    font-size: 0.78rem;
    font-weight: 700;
    color: #64748b;
    margin-bottom: 3px;
}
.yango-step-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #0f172a;
}
.yango-step-detail {
    font-size: 0.85rem;
    color: #64748b;
    margin-top: 2px;
}

/* Custom Vehicle Map Marker Icon */
.yango-vehicle-marker {
    background: #2563eb;
    border: 3px solid #ffffff;
    border-radius: 50%;
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    box-shadow: 0 8px 20px rgba(37, 99, 235, 0.5);
    transition: all 0.3s ease;
}
.yango-vehicle-marker svg {
    width: 22px;
    height: 22px;
}

/* Custom Pin Marker Icon */
.yango-pin-marker {
    background: #0f172a;
    border: 2px solid #ffffff;
    border-radius: 50%;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-weight: 800;
    font-size: 0.75rem;
    box-shadow: 0 4px 10px rgba(0,0,0,0.3);
}
</style>

<div class="site-content">
<div class="yango-container">

    <!-- Search Hero -->
    <section class="yango-hero">
        <span style="display:inline-flex; align-items:center; gap:6px; background:rgba(37,99,235,0.2); color:#60a5fa; border:1px solid rgba(96,165,250,0.3); padding:4px 12px; border-radius:20px; font-size:0.8rem; font-weight:700; text-transform:uppercase; margin-bottom:12px;">
            <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" stroke-width="2.5" fill="none"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10z"/></svg>
            Suivi GPS Temps Réel ERP LBP
        </span>
        <h1>Suivi Live du Déplacement Colis (Style Yango)</h1>
        <p>Visualisez la trajectoire animée de votre cargaison entre l'agence de départ et sa destination finale.</p>
        
        <form class="yango-searchbar" method="get" action="<?= View::url('site/tracking') ?>">
            <input type="text" name="ref" value="<?= View::e($current['reference'] ?? '') ?>" placeholder="Entrez votre N° de tracking (Ex: LB-CI-001, MP-FR-002)..." required>
            <button type="submit">
                <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2.5" fill="none"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <span>Localiser</span>
            </button>
        </form>
    </section>

    <!-- Interactive Yango Map Card -->
    <section class="yango-map-card">
        <div class="yango-map-header">
            <div class="yango-map-header__title">
                <div style="width:38px; height:38px; border-radius:10px; background:#eff6ff; color:#2563eb; display:flex; align-items:center; justify-content:center;">
                    <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2.2" fill="none"><path d="M12 22s-8-4.5-8-11.8A8 8 0 0 1 12 2a8 8 0 0 1 8 8.2c0 7.3-8 11.8-8 11.8z"/><circle cx="12" cy="10" r="3"/></svg>
                </div>
                <div>
                    <span style="font-size:0.75rem; color:#64748b; font-weight:700; text-transform:uppercase;">Cargaison Suivie</span>
                    <strong><?= View::e($current['reference'] ?? 'Non spécifié') ?></strong>
                </div>
            </div>
            <div style="display:flex; align-items:center; gap:12px;">
                <span class="yango-live-tag">
                    <span class="yango-pulse"></span>
                    SUIVI EN DIRECT YANGO GPS
                </span>
                <button type="button" id="btn-replay-animation" style="background:#f1f5f9; border:1px solid #cbd5e1; color:#334155; padding:6px 14px; border-radius:8px; font-weight:700; font-size:0.82rem; cursor:pointer; display:flex; align-items:center; gap:6px;">
                    <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" stroke-width="2.5" fill="none"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
                    Rejouer le trajet
                </button>
            </div>
        </div>

        <!-- Leaflet Map Container -->
        <div id="yango-live-map"></div>

        <!-- Floating HUD Overlay -->
        <div class="yango-hud">
            <div class="yango-hud__speed">
                <div class="yango-hud__icon">
                    <?php if ($transportMode === 'ship'): ?>
                        <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none"><path d="M2 21c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 2.5 0 2.5 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"/><path d="M19.38 20A11.6 11.6 0 0 0 21 14l-9-4-9 4c0 2.9.94 5.34 2.81 7.03"/><path d="M12 10V4"/><path d="M8 7h8"/></svg>
                    <?php elseif ($transportMode === 'truck'): ?>
                        <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                    <?php else: ?>
                        <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none"><path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.7 5.2c.3.4.8.5 1.3.3l.5-.3c.4-.2.6-.6.5-1.1z"/></svg>
                    <?php endif; ?>
                </div>
                <div class="yango-hud__info">
                    <strong><?= View::e($current['origin']) ?> ➔ <?= View::e($current['destination']) ?></strong>
                    <small>Progression : <strong><?= $progressPct ?>%</strong> &bull; Position : <?= View::e($current['lastLocation'] ?? 'En transit') ?></small>
                </div>
            </div>
            <div style="border-left:1px solid rgba(255,255,255,0.15); padding-left:18px;">
                <span style="font-size:0.75rem; color:#94a3b8; display:block;">Est. Arrivée (ETA)</span>
                <strong style="color:#10b981; font-size:1.1rem;"><?= View::e($current['eta'] ?? 'En cours') ?></strong>
            </div>
        </div>
    </section>

    <!-- Details Grid -->
    <div class="yango-details-grid">
        
        <!-- Left: Status & Metadata -->
        <div class="yango-card">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; flex-wrap:wrap; gap:10px;">
                <h3 class="yango-card-title" style="margin:0;">
                    <svg viewBox="0 0 24 24" width="22" height="22" stroke="#2563eb" stroke-width="2.2" fill="none"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    Fiche Récapitulative du Colis
                </h3>
                <span style="display:inline-flex; align-items:center; gap:5px; padding:5px 14px; border-radius:20px; font-size:0.8rem; font-weight:800; background:#dbeafe; color:#1e40af; border:1px solid #93c5fd;">
                    <?= View::e(strtoupper((string)($current['status'] ?? 'EN TRANSIT'))) ?>
                </span>
            </div>

            <!-- Progression Bar -->
            <div style="margin-bottom:25px;">
                <div style="display:flex; justify-content:space-between; font-size:0.85rem; font-weight:700; color:#475569; margin-bottom:8px;">
                    <span>Avancement de l'expédition</span>
                    <span style="color:#2563eb;"><?= $progressPct ?>%</span>
                </div>
                <div style="height:10px; background:#e2e8f0; border-radius:10px; overflow:hidden;">
                    <div style="height:100%; width:<?= $progressPct ?>%; background:linear-gradient(90deg, #2563eb, #10b981); border-radius:10px; transition:width 1s ease;"></div>
                </div>
            </div>

            <!-- Grid Metadata -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:25px;">
                <div style="background:#f8fafc; padding:14px; border-radius:12px; border:1px solid #e2e8f0;">
                    <span style="font-size:0.75rem; color:#64748b; font-weight:700; text-transform:uppercase; display:block;">Client / Destinataire</span>
                    <strong style="font-size:0.95rem; color:#0f172a; margin-top:2px; display:block;"><?= View::e($current['client'] ?? 'Non masqué') ?></strong>
                </div>
                <div style="background:#f8fafc; padding:14px; border-radius:12px; border:1px solid #e2e8f0;">
                    <span style="font-size:0.75rem; color:#64748b; font-weight:700; text-transform:uppercase; display:block;">Dernier Rayon / Emplacement</span>
                    <strong style="font-size:0.95rem; color:#0f172a; margin-top:2px; display:block;"><?= View::e($current['lastLocation'] ?? 'Zone Transit') ?></strong>
                </div>
            </div>

            <!-- Action Buttons -->
            <div style="display:flex; gap:12px; flex-wrap:wrap;">
                <a href="<?= View::url('site/contact') ?>" style="flex:1; background:#2563eb; color:#ffffff; font-weight:700; padding:12px 18px; border-radius:10px; text-decoration:none; text-align:center; display:inline-flex; align-items:center; justify-content:center; gap:8px; box-shadow:0 4px 12px rgba(37,99,235,0.25);">
                    <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    Contacter le support d'agence
                </a>
            </div>
        </div>

        <!-- Right: GPS Steps Timeline -->
        <div class="yango-card">
            <h3 class="yango-card-title">
                <svg viewBox="0 0 24 24" width="22" height="22" stroke="#16a34a" stroke-width="2.2" fill="none"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Jalons Logistiques & GPS
            </h3>

            <div class="yango-timeline">
                <?php $stepsList = $current['steps'] ?? []; ?>
                <?php if (empty($stepsList)): ?>
                    <p style="color:#64748b; font-size:0.88rem;">Aucun jalon GPS pour le moment.</p>
                <?php else: ?>
                    <?php foreach ($stepsList as $idx => $step): ?>
                        <div class="yango-step <?= $idx === count($stepsList) - 1 ? 'yango-step--active' : '' ?>">
                            <div class="yango-step-dot"></div>
                            <div class="yango-step-time"><?= View::e($step['date']) ?></div>
                            <div class="yango-step-title"><?= View::e($step['title']) ?></div>
                            <div class="yango-step-detail"><?= View::e($step['detail']) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Reference test list -->
    <div style="margin-top:35px; background:#ffffff; padding:24px; border-radius:16px; border:1px solid #e2e8f0;">
        <h4 style="margin:0 0 12px 0; font-size:0.95rem; font-weight:800; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">Colis de démonstration disponibles pour tester la carte Yango</h4>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <?php foreach ($page->shipments as $shipment): ?>
                <a href="<?= View::url('site/tracking') ?>?ref=<?= urlencode($shipment['reference']) ?>" style="background:#f1f5f9; border:1px solid #cbd5e1; color:#1e293b; padding:8px 16px; border-radius:10px; font-weight:700; font-size:0.85rem; text-decoration:none; display:inline-flex; align-items:center; gap:8px;">
                    <code><?= View::e($shipment['reference']) ?></code>
                    <span style="font-size:0.75rem; background:#dbeafe; color:#1e40af; padding:2px 6px; border-radius:4px;"><?= View::e($shipment['status']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

</div>
</div>

<!-- Yango Live Map Script (Leaflet Animated Path) -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    if (typeof L === 'undefined') return;

    var originCoords = [<?= (float) $originCoords[0] ?>, <?= (float) $originCoords[1] ?>];
    var destCoords = [<?= (float) $destCoords[0] ?>, <?= (float) $destCoords[1] ?>];
    var progressPct = <?= $progressPct ?> / 100.0;
    var mode = "<?= $transportMode ?>";

    // Initialize Leaflet Map
    var map = L.map('yango-live-map', {
        zoomControl: true,
        scrollWheelZoom: false
    }).setView([
        (originCoords[0] + destCoords[0]) / 2,
        (originCoords[1] + destCoords[1]) / 2
    ], 4);

    // Dark Tile Layer (Voyager / CartoDB)
    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://carto.com/">CARTO</a> &copy; OpenStreetMap',
        maxZoom: 19
    }).addTo(map);

    // Custom Departure Pin (Green)
    var startIcon = L.divIcon({
        className: '',
        html: '<div class="yango-pin-marker" style="background:#10b981;">A</div>',
        iconSize: [32, 32],
        iconAnchor: [16, 16]
    });
    L.marker(originCoords, { icon: startIcon }).addTo(map).bindPopup("<b>Agence de Départ</b><br><?= View::e($current['origin']) ?>");

    // Custom Arrival Pin (Red)
    var endIcon = L.divIcon({
        className: '',
        html: '<div class="yango-pin-marker" style="background:#ef4444;">B</div>',
        iconSize: [32, 32],
        iconAnchor: [16, 16]
    });
    L.marker(destCoords, { icon: endIcon }).addTo(map).bindPopup("<b>Agence d'Arrivée</b><br><?= View::e($current['destination']) ?>");

    // Generate Trajectory Path Points (Geodesic / Curved interpolation)
    function interpolate(p1, p2, factor) {
        return [
            p1[0] + (p2[0] - p1[0]) * factor,
            p1[1] + (p2[1] - p1[1]) * factor
        ];
    }

    // Trajectory Polyline (Dashed Blue Line)
    var polyline = L.polyline([originCoords, destCoords], {
        color: '#2563eb',
        weight: 4,
        opacity: 0.8,
        dashArray: '8, 12'
    }).addTo(map);

    map.fitBounds(polyline.getBounds(), { padding: [50, 50] });

    // Vehicle Icon SVG depending on mode
    var svgIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.7 5.2c.3.4.8.5 1.3.3l.5-.3c.4-.2.6-.6.5-1.1z"/></svg>';
    if (mode === 'ship') {
        svgIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 21c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 2.5 0 2.5 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"/><path d="M19.38 20A11.6 11.6 0 0 0 21 14l-9-4-9 4c0 2.9.94 5.34 2.81 7.03"/><path d="M12 10V4"/><path d="M8 7h8"/></svg>';
    } else if (mode === 'truck') {
        svgIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>';
    }

    var vehicleIcon = L.divIcon({
        className: '',
        html: '<div class="yango-vehicle-marker">' + svgIcon + '</div>',
        iconSize: [44, 44],
        iconAnchor: [22, 22]
    });

    // Current Vehicle Marker on Map
    var currentPos = interpolate(originCoords, destCoords, progressPct);
    var vehicleMarker = L.marker(currentPos, { icon: vehicleIcon }).addTo(map);
    vehicleMarker.bindPopup("<b>Position Yango en Direct</b><br>Colis <b><?= View::e($current['reference']) ?></b><br>Statut : <?= View::e($current['status']) ?>").openPopup();

    // Replay Animation Function (Smooth Yango Movement)
    function animateVehicle() {
        var start = 0;
        var duration = 3000; // 3 seconds smooth sliding
        var startTime = null;

        function step(timestamp) {
            if (!startTime) startTime = timestamp;
            var elapsed = timestamp - startTime;
            var currentFactor = Math.min(progressPct, (elapsed / duration) * progressPct);

            var pos = interpolate(originCoords, destCoords, currentFactor);
            vehicleMarker.setLatLng(pos);

            if (elapsed < duration) {
                requestAnimationFrame(step);
            }
        }
        requestAnimationFrame(step);
    }

    // Trigger animation on replay button
    var btnReplay = document.getElementById('btn-replay-animation');
    if (btnReplay) {
        btnReplay.addEventListener('click', function () {
            vehicleMarker.setLatLng(originCoords);
            animateVehicle();
        });
    }

    // Auto-run initial smooth slide animation
    setTimeout(animateVehicle, 500);
});
</script>

<?php $content = ob_get_clean(); require BASE_PATH . '/views/layouts/site.php';
