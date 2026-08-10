<?php
use App\Helpers\View;
use App\View\Components\Form;
use App\View\Components\Ui;
use App\View\Pages\Site\SitePage;

/** @var SitePage $page */
ob_start();
$agenciesJson = json_encode($page->agencies, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<div class="site-content">
<section class="site-page-hero site-page-hero--locator" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-radius: 20px; padding: 35px 40px; color: #ffffff; margin-bottom: 25px; box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.35);">
    <span style="display:inline-flex; align-items:center; gap:6px; background:rgba(37,99,235,0.2); color:#60a5fa; border:1px solid rgba(96,165,250,0.3); padding:4px 12px; border-radius:20px; font-size:0.8rem; font-weight:700; text-transform:uppercase; margin-bottom:12px;">
        <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" stroke-width="2.5" fill="none"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
        Réseau International LBP
    </span>
    <h1 style="color:#ffffff; font-size:2.2rem; font-weight:800; margin:0 0 10px 0;">Localisateur d'Agences & Points Relais</h1>
    <p style="color:#94a3b8; font-size:1rem; margin:0;">Trouvez l'agence la plus proche de chez vous, découvrez les services proposés et vérifiez son statut d'ouverture en temps réel.</p>
</section>

<section class="site-locator" data-agencies='<?= View::e($agenciesJson) ?>' style="display:grid; grid-template-columns:380px 1fr; gap:25px;">
    <aside class="site-locator__list" style="background:#ffffff; border-radius:18px; border:1px solid #e2e8f0; padding:20px; box-shadow:0 10px 25px -5px rgba(15,23,42,0.05);">
        <div class="site-locator__filters" style="margin-bottom:20px;">
            <?= Form::input('agency_search', ['label' => 'Recherche d\'agence', 'placeholder' => 'Rechercher ville, pays, service...', 'data-agency-search' => true]) ?>
            <div style="margin-top:10px;">
                <?= Form::selectSearch('country_filter', array_merge(
                    [['value' => '', 'label' => 'Tous les pays']],
                    array_map(static fn(string $country): array => ['value' => $country, 'label' => $country], $page->countries)
                ), '', ['label' => 'Pays d\'implantation', 'data-country-filter' => true]) ?>
            </div>
        </div>
        
        <div class="site-agency-results" data-agency-results style="max-height:550px; overflow-y:auto; padding-right:5px;">
            <?php foreach ($page->mappedAgencies as $agency): ?>
                <?php
                // Check if open currently (assume 08:00 - 18:00)
                $hour = (int) date('H');
                $isOpen = ($hour >= 8 && $hour < 18);
                ?>
                <article class="site-agency-card" data-agency-card data-code="<?= View::e($agency['code']) ?>" data-search="<?= View::e(strtolower($agency['name'].' '.$agency['city'].' '.$agency['country'].' '.$agency['services'])) ?>" data-country="<?= View::e($agency['country']) ?>" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:14px; padding:16px; margin-bottom:14px; transition:all 0.2s ease; cursor:pointer;">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
                        <strong style="font-size:1.05rem; color:#0f172a;"><?= View::e($agency['name']) ?></strong>
                        <span style="font-size:0.7rem; font-weight:800; padding:3px 8px; border-radius:12px; <?= $isOpen ? 'background:#dcfce7; color:#15803d; border:1px solid #86efac;' : 'background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5;' ?>">
                            <?= $isOpen ? '● OUVERT' : '○ FERMÉ' ?>
                        </span>
                    </div>
                    <span style="display:block; font-size:0.85rem; color:#475569; margin-bottom:6px;"><?= View::e($agency['address']) ?></span>
                    <small style="color:#64748b; font-size:0.78rem; display:block; margin-bottom:8px;"><?= View::e($agency['city']) ?>, <?= View::e($agency['country']) ?> &bull; <?= View::e($agency['hours']) ?></small>
                    <div style="font-size:0.78rem; color:#2563eb; background:#eff6ff; border:1px solid #bfdbfe; padding:4px 8px; border-radius:6px; margin-bottom:8px; font-weight:600;"><?= View::e($agency['services']) ?></div>
                    <a href="tel:<?= View::e($agency['phone']) ?>" style="color:#10b981; font-weight:700; font-size:0.85rem; text-decoration:none; display:inline-flex; align-items:center; gap:4px;">
                        <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" stroke-width="2.2" fill="none"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        <?= View::e($agency['phone']) ?>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    </aside>

    <div class="site-map-panel" style="background:#ffffff; border-radius:18px; border:1px solid #e2e8f0; padding:20px; box-shadow:0 10px 25px -5px rgba(15,23,42,0.05); display:flex; flex-direction:column;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:15px;">
            <strong style="font-size:1.1rem; color:#0f172a; font-weight:800;">Carte des Agences LBP (OpenStreetMap)</strong>
            <span data-agency-count style="font-size:0.85rem; font-weight:700; color:#2563eb; background:#eff6ff; padding:4px 12px; border-radius:20px;"><?= count($page->agencies) ?> agence(s) actives</span>
        </div>
        <div id="agencies-leaflet-map" style="height:520px; width:100%; border-radius:14px; overflow:hidden; border:1px solid #cbd5e1;"></div>
    </div>
</section>
</div>

<!-- Interactive Leaflet Agencies Map Script -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    if (typeof L === 'undefined') return;

    var agencies = [
        { name: "Agence Abidjan Treichville", lat: 5.359951, lng: -4.008256, city: "Abidjan, CIV", phone: "+225 07 00 00 01", services: "Fret Aérien, Maritime & Douane" },
        { name: "Agence Paris CDG", lat: 48.856614, lng: 2.352221, city: "Paris, France", phone: "+33 1 40 00 00 00", services: "Transit Europe & Groupage" },
        { name: "Agence Dakar Plateau", lat: 14.716677, lng: -17.467686, city: "Dakar, Sénégal", phone: "+221 33 800 00 00", services: "Fret Maritime & Colis" },
        { name: "Agence Montréal Centre", lat: 45.501688, lng: -73.567256, city: "Montréal, Canada", phone: "+1 514 000 0000", services: "Express Canada & Afrique" }
    ];

    var map = L.map('agencies-leaflet-map').setView([20, 0], 2);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; CARTO &copy; OpenStreetMap',
        maxZoom: 18
    }).addTo(map);

    var agencyIcon = L.divIcon({
        className: '',
        html: '<div style="background:#2563eb; color:#fff; width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:800; border:3px solid #ffffff; box-shadow:0 6px 15px rgba(37,99,235,0.4);"><svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2.5" fill="none"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></div>',
        iconSize: [36, 36],
        iconAnchor: [18, 18]
    });

    var bounds = [];
    agencies.forEach(function (agency) {
        var marker = L.marker([agency.lat, agency.lng], { icon: agencyIcon }).addTo(map);
        marker.bindPopup(
            '<div style="font-family:sans-serif; padding:4px;">' +
            '<strong style="color:#0f172a; font-size:0.95rem; display:block; margin-bottom:4px;">' + agency.name + '</strong>' +
            '<small style="color:#64748b; display:block; margin-bottom:6px;">' + agency.city + '</small>' +
            '<span style="font-size:0.75rem; background:#eff6ff; color:#2563eb; padding:2px 6px; border-radius:4px; font-weight:700;">' + agency.services + '</span><br>' +
            '<a href="tel:' + agency.phone + '" style="color:#10b981; font-weight:700; text-decoration:none; margin-top:6px; display:inline-block;">📞 ' + agency.phone + '</a>' +
            '</div>'
        );
        bounds.push([agency.lat, agency.lng]);
    });

    if (bounds.length > 0) {
        map.fitBounds(bounds, { padding: [50, 50] });
    }
});
</script>

<?php $content = ob_get_clean(); require BASE_PATH . '/views/layouts/site.php';
