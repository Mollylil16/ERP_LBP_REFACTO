<?php

use App\Helpers\View;
use App\View\Components\Site;
use App\View\Pages\Site\SitePage;

/** @var SitePage $page */

ob_start();
?>

<!-- 1. HERO SHOWCASE SECTION -->
<div class="site-hero-showcase" style="position:relative; margin-bottom:40px;">
    <?= Site::carousel($page->slides) ?>

    <!-- Floating Dual Quick Action Card (Suivi + Calculateur) -->
    <div class="site-content" style="position:relative; margin-top:-60px; z-index:10;">
        <div style="background:#ffffff; border-radius:24px; padding:32px; border:1px solid #e2e8f0; box-shadow:0 25px 50px -12px rgba(15,23,42,0.15);">
            
            <!-- Quick Action Tabs Header -->
            <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:15px; border-bottom:2px solid #f1f5f9; padding-bottom:18px; margin-bottom:24px;">
                <div style="display:flex; gap:12px; flex-wrap:wrap;">
                    <button type="button" id="tab-btn-tracking" onclick="switchHomeTab('tracking')" style="background:#2563eb; color:#ffffff; border:none; padding:10px 22px; border-radius:12px; font-weight:700; font-size:0.92rem; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:all 0.2s;">
                        <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2.2" fill="none"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <span>Suivre un Colis</span>
                    </button>
                    <button type="button" id="tab-btn-calc" onclick="switchHomeTab('calc')" style="background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; padding:10px 22px; border-radius:12px; font-weight:700; font-size:0.92rem; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:all 0.2s;">
                        <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2.2" fill="none"><rect x="2" y="4" width="20" height="16" rx="2"/><line x1="6" y1="8" x2="6" y2="8"/><line x1="10" y1="8" x2="18" y2="8"/></svg>
                        <span>Simuler un Tarif Fret</span>
                    </button>
                </div>
                <span style="font-size:0.8rem; font-weight:800; color:#059669; background:#ecfdf5; border:1px solid #a7f3d0; padding:6px 14px; border-radius:20px; text-transform:uppercase; letter-spacing:0.5px;">
                    ● ERP SYNCHRONISÉ EN DIRECT
                </span>
            </div>

            <!-- TAB 1: QUICK TRACKING -->
            <div id="home-tab-tracking" style="display:block;">
                <form method="get" action="<?= View::url('site/tracking') ?>" style="display:flex; gap:14px; flex-wrap:wrap; align-items:center;">
                    <div style="flex:1; min-width:280px;">
                        <label style="display:block; font-size:0.82rem; font-weight:700; color:#475569; margin-bottom:6px;">NUMÉRO DE TRACKING / EXPÉDITION</label>
                        <input type="text" name="ref" value="<?= View::e($page->defaultShipment) ?>" placeholder="Entrez votre N° colis (Ex: LBP-EXP-2026-00124)..." required style="width:100%; padding:14px 18px; border-radius:12px; border:1.5px solid #cbd5e1; font-size:1rem; font-weight:600; outline:none; background:#f8fafc; transition:all 0.2s;">
                    </div>
                    <div style="margin-top:22px;">
                        <button type="submit" style="background:#2563eb; color:#ffffff; border:none; padding:14px 32px; border-radius:12px; font-weight:800; font-size:1rem; cursor:pointer; display:inline-flex; align-items:center; gap:10px; box-shadow:0 4px 14px rgba(37,99,235,0.35);">
                            <span>Localiser en direct</span>
                            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2.5" fill="none"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                        </button>
                    </div>
                </form>
            </div>

            <!-- TAB 2: FREIGHT CALCULATOR -->
            <div id="home-tab-calc" style="display:none;">
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:18px; margin-bottom:18px;">
                    <div>
                        <label style="display:block; font-size:0.8rem; font-weight:700; color:#475569; margin-bottom:6px;">MODE DE TRANSPORT</label>
                        <select id="home-calc-mode" style="width:100%; padding:12px 14px; border-radius:10px; border:1px solid #cbd5e1; background:#f8fafc; font-weight:700; font-size:0.9rem; outline:none;">
                            <option value="aerien">Fret Aérien (Express / Groupage)</option>
                            <option value="maritime">Fret Maritime (Conteneur / m³)</option>
                            <option value="dhl">DHL Express International</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:0.8rem; font-weight:700; color:#475569; margin-bottom:6px;">TRAJET EXPÉDITION</label>
                        <select id="home-calc-route" style="width:100%; padding:12px 14px; border-radius:10px; border:1px solid #cbd5e1; background:#f8fafc; font-weight:700; font-size:0.9rem; outline:none;">
                            <option value="CIV_FR">Côte d'Ivoire (Abidjan) ➔ France (Paris-Bobigny)</option>
                            <option value="FR_CIV">France (Paris-Bobigny) ➔ Côte d'Ivoire (Abidjan)</option>
                            <option value="CIV_SEN">Côte d'Ivoire (Abidjan) ➔ Sénégal (Dakar)</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:0.8rem; font-weight:700; color:#475569; margin-bottom:6px;">POIDS TOTAL (KG)</label>
                        <input type="number" id="home-calc-weight" value="10" min="1" step="0.5" style="width:100%; padding:12px 14px; border-radius:10px; border:1px solid #cbd5e1; background:#f8fafc; font-weight:800; font-size:0.95rem; outline:none;">
                    </div>
                    <div>
                        <label style="display:block; font-size:0.8rem; font-weight:700; color:#475569; margin-bottom:6px;">ESTIMATION TARIF</label>
                        <div style="background:#eff6ff; border:1px solid #bfdbfe; padding:10px 14px; border-radius:10px; display:flex; align-items:center; justify-content:space-between;">
                            <strong id="home-calc-result" style="color:#1d4ed8; font-size:1.2rem; font-weight:900;">45 000 XOF</strong>
                            <small id="home-calc-eur" style="color:#64748b; font-weight:700;">(~68.60 €)</small>
                        </div>
                    </div>
                </div>
                <div style="display:flex; justify-content:flex-end;">
                    <a id="home-calc-link" href="<?= View::url('site/devis') ?>" style="background:#2563eb; color:#ffffff; font-weight:800; padding:12px 24px; border-radius:10px; text-decoration:none; display:inline-flex; align-items:center; gap:8px; font-size:0.95rem;">
                        <span>Obtenir un devis officiel</span>
                        <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>

<div class="site-content">

    <!-- 2. METRICS & IMPACT KEY STATS -->
    <div style="margin-bottom:50px;">
        <?= Site::stats($page->stats) ?>
    </div>

    <!-- 4. NOS SOLUTIONS LOGISTIQUES ET MÉTIERS -->
    <section id="services" style="margin-bottom:60px;">
        <?= Site::sectionHeading('Solutions Intégrées', 'Chaque étape du fret international coordonnée.', 'De la prise en charge en agence jusqu\'à la livraison finale avec suivi GPS en direct.') ?>
        <?= Site::services($page->services) ?>
    </section>

    <!-- 5. POURQUOI CHOISIR LBP LOGISTICS (PILLIERS D'EXCELLENCE) -->
    <section style="background:linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-radius:24px; padding:45px 40px; color:#ffffff; margin-bottom:60px; box-shadow:0 20px 40px -10px rgba(15,23,42,0.3);">
        <div style="max-width:650px; margin-bottom:35px;">
            <span style="display:inline-block; background:rgba(37,99,235,0.25); color:#60a5fa; border:1px solid rgba(96,165,250,0.3); padding:4px 14px; border-radius:20px; font-size:0.8rem; font-weight:800; text-transform:uppercase; margin-bottom:12px;">
                Excellence Logistique
            </span>
            <h2 style="font-size:2.2rem; font-weight:900; color:#ffffff; margin:0 0 10px 0;">Pourquoi confier vos marchandises à LBP ?</h2>
            <p style="color:#94a3b8; font-size:1.05rem; margin:0;">Une infrastructure solide connectée au système ERP pour garantir sécurité, rapidité et transparence.</p>
        </div>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:24px;">
            <div style="background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); padding:24px; border-radius:16px;">
                <div style="width:44px; height:44px; border-radius:12px; background:#2563eb; color:#ffffff; display:flex; align-items:center; justify-content:center; margin-bottom:16px;">
                    <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2.2" fill="none"><path d="M12 22s-8-4.5-8-11.8A8 8 0 0 1 12 2a8 8 0 0 1 8 8.2c0 7.3-8 11.8-8 11.8z"/><circle cx="12" cy="10" r="3"/></svg>
                </div>
                <h3 style="font-size:1.1rem; font-weight:800; color:#ffffff; margin:0 0 8px 0;">Traçabilité GPS en Direct</h3>
                <p style="color:#94a3b8; font-size:0.9rem; margin:0; line-height:1.5;">Suivez la progression exacte de votre colis sur la carte interactive à chaque étape du trajet.</p>
            </div>

            <div style="background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); padding:24px; border-radius:16px;">
                <div style="width:44px; height:44px; border-radius:12px; background:#10b981; color:#ffffff; display:flex; align-items:center; justify-content:center; margin-bottom:16px;">
                    <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2.2" fill="none"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </div>
                <h3 style="font-size:1.1rem; font-weight:800; color:#ffffff; margin:0 0 8px 0;">Sécurité & Assurance Fret</h3>
                <p style="color:#94a3b8; font-size:0.9rem; margin:0; line-height:1.5;">Prise en charge sécurisée et protection intégrale de vos colis contre les pertes et avaries.</p>
            </div>

            <div style="background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); padding:24px; border-radius:16px;">
                <div style="width:44px; height:44px; border-radius:12px; background:#f59e0b; color:#ffffff; display:flex; align-items:center; justify-content:center; margin-bottom:16px;">
                    <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2.2" fill="none"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                </div>
                <h3 style="font-size:1.1rem; font-weight:800; color:#ffffff; margin:0 0 8px 0;">Maîtrise du Dédouanement</h3>
                <p style="color:#94a3b8; font-size:0.9rem; margin:0; line-height:1.5;">Gestion complète des formalités de douanes pour une mainlevée rapide sans blocage.</p>
            </div>

            <div style="background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); padding:24px; border-radius:16px;">
                <div style="width:44px; height:44px; border-radius:12px; background:#8b5cf6; color:#ffffff; display:flex; align-items:center; justify-content:center; margin-bottom:16px;">
                    <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2.2" fill="none"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                </div>
                <h3 style="font-size:1.1rem; font-weight:800; color:#ffffff; margin:0 0 8px 0;">Réseau d'Agences Directes</h3>
                <p style="color:#94a3b8; font-size:0.9rem; margin:0; line-height:1.5;">Présence directe à Abidjan, Paris-Bobigny, Dakar et San Pedro pour un traitement sans intermédiaire.</p>
            </div>
        </div>
    </section>

    <!-- 6. NOS AGENCES DIRECTES (PREVIEW DES AGENCES RÉELLES) -->
    <section style="margin-bottom:60px;">
        <?= Site::sectionHeading('Réseau Réel LBP', 'Nos agences et comptoirs de réception.', 'Des équipes dédiées sur place pour réceptionner, traiter et livrer vos marchandises.', '<a class="site-text-link" href="' . View::url('site/agences') . '">Voir toutes les agences →</a>') ?>
        
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:20px;">
            <div style="background:#ffffff; border-radius:16px; padding:24px; border:1px solid #e2e8f0; box-shadow:0 4px 15px rgba(15,23,42,0.04);">
                <span style="font-size:0.75rem; font-weight:800; color:#2563eb; background:#eff6ff; padding:3px 10px; border-radius:12px; text-transform:uppercase;">SIÈGE SOCIAL</span>
                <h3 style="font-size:1.15rem; font-weight:800; color:#0f172a; margin:10px 0 6px 0;">LBP Siège Abidjan</h3>
                <p style="color:#64748b; font-size:0.88rem; margin:0 0 12px 0;">Plateau, Avenue de la République, Abidjan</p>
                <small style="color:#10b981; font-weight:700; display:block;">● OUVERT · Lun–Ven 08h-18h</small>
            </div>

            <div style="background:#ffffff; border-radius:16px; padding:24px; border:1px solid #e2e8f0; box-shadow:0 4px 15px rgba(15,23,42,0.04);">
                <span style="font-size:0.75rem; font-weight:800; color:#2563eb; background:#eff6ff; padding:3px 10px; border-radius:12px; text-transform:uppercase;">HUB EUROPE</span>
                <h3 style="font-size:1.15rem; font-weight:800; color:#0f172a; margin:10px 0 6px 0;">LBP Paris - Bobigny</h3>
                <p style="color:#64748b; font-size:0.88rem; margin:0 0 12px 0;">17 chemin des Vignes, 93000 Bobigny, France</p>
                <small style="color:#10b981; font-weight:700; display:block;">● OUVERT · Lun–Ven 09h-18h</small>
            </div>

            <div style="background:#ffffff; border-radius:16px; padding:24px; border:1px solid #e2e8f0; box-shadow:0 4px 15px rgba(15,23,42,0.04);">
                <span style="font-size:0.75rem; font-weight:800; color:#2563eb; background:#eff6ff; padding:3px 10px; border-radius:12px; text-transform:uppercase;">HUB AFRIQUE DE L'OUEST</span>
                <h3 style="font-size:1.15rem; font-weight:800; color:#0f172a; margin:10px 0 6px 0;">LBP Agence Sénégal</h3>
                <p style="color:#64748b; font-size:0.88rem; margin:0 0 12px 0;">Avenue Lamine Guèye, Dakar, Sénégal</p>
                <small style="color:#10b981; font-weight:700; display:block;">● OUVERT · Lun–Ven 08h-17h30</small>
            </div>

            <div style="background:#ffffff; border-radius:16px; padding:24px; border:1px solid #e2e8f0; box-shadow:0 4px 15px rgba(15,23,42,0.04);">
                <span style="font-size:0.75rem; font-weight:800; color:#2563eb; background:#eff6ff; padding:3px 10px; border-radius:12px; text-transform:uppercase;">HUB PORTUAIRE</span>
                <h3 style="font-size:1.15rem; font-weight:800; color:#0f172a; margin:10px 0 6px 0;">LBP Agence San Pedro</h3>
                <p style="color:#64748b; font-size:0.88rem; margin:0 0 12px 0;">Zone portuaire, San Pedro, Côte d'Ivoire</p>
                <small style="color:#10b981; font-weight:700; display:block;">● OUVERT · Lun–Ven 08h-17h</small>
            </div>
        </div>
    </section>

    <!-- 7. MARKETPLACE & FOURNITURES -->
    <section style="margin-bottom:60px;">
        <?= Site::sectionHeading('Boutique & Emballages', 'Matériel de conditionnement et services au m³.', 'Commandez vos cartons renforcés, fûts et prestations de transit directement en ligne.', '<a class="site-text-link" href="' . View::url('site/shop') . '">Voir toute la boutique →</a>') ?>
        <?= Site::products($page->products, 4) ?>
    </section>

    <!-- 8. COMMUNAUTÉ & ACTUALITÉS -->
    <section style="margin-bottom:60px;">
        <?= Site::sectionHeading('Actualités & Forum', 'Conseils, réglementation et échanges d\'expérience.', 'Restez informé des évolutions douanières et échangez avec notre communauté d\'expéditeurs.', '<a class="site-text-link" href="' . View::url('site/blog') . '">Découvrir le mag →</a>') ?>
        <?= Site::topics($page->topics, 3) ?>
    </section>

    <!-- 9. CALL TO ACTION FINAL -->
    <section style="text-align:center; padding:55px 30px; background:#ffffff; border-radius:24px; border:1px solid #e2e8f0; margin-top:40px; box-shadow:0 15px 35px -10px rgba(15,23,42,0.05);">
        <span style="font-size:0.82rem; font-weight:800; color:#2563eb; background:#eff6ff; padding:4px 14px; border-radius:20px; text-transform:uppercase;">Projet d'expédition ou devis sur mesure ?</span>
        <h2 style="font-size:2.2rem; font-weight:900; color:#0f172a; margin:12px 0 14px 0;">Transformons vos besoins logistiques en une chaîne performante.</h2>
        <p style="color:#64748b; max-width:600px; margin:0 auto 28px auto; font-size:1rem;">Nos équipes en agence sont à votre disposition pour vous conseiller et vous établir un tarif optimisé.</p>
        
        <div style="display:flex; gap:16px; justify-content:center; flex-wrap:wrap;">
            <a href="<?= View::url('site/devis') ?>" style="background:#2563eb; color:#ffffff; padding:15px 32px; border-radius:12px; font-weight:800; font-size:1rem; text-decoration:none; display:inline-flex; align-items:center; gap:8px; box-shadow:0 4px 14px rgba(37,99,235,0.35);">
                <span>Obtenir une cotation officielle</span>
                <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2.5" fill="none"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </a>
            <a href="<?= View::url('site/contact') ?>" style="background:#f1f5f9; color:#0f172a; padding:15px 32px; border-radius:12px; font-weight:700; font-size:1rem; text-decoration:none; border:1px solid #cbd5e1;">
                Contacter un conseiller agence
            </a>
        </div>
    </section>

</div>

<!-- Home Quick Tabs Script -->
<script>
function switchHomeTab(tabName) {
    var tabTracking = document.getElementById('home-tab-tracking');
    var tabCalc = document.getElementById('home-tab-calc');
    var btnTracking = document.getElementById('tab-btn-tracking');
    var btnCalc = document.getElementById('tab-btn-calc');

    if (!tabTracking || !tabCalc) return;

    if (tabName === 'calc') {
        tabTracking.style.display = 'none';
        tabCalc.style.display = 'block';
        btnTracking.style.background = '#f1f5f9';
        btnTracking.style.color = '#475569';
        btnTracking.style.border = '1px solid #cbd5e1';
        btnCalc.style.background = '#2563eb';
        btnCalc.style.color = '#ffffff';
        btnCalc.style.border = 'none';
    } else {
        tabTracking.style.display = 'block';
        tabCalc.style.display = 'none';
        btnCalc.style.background = '#f1f5f9';
        btnCalc.style.color = '#475569';
        btnCalc.style.border = '1px solid #cbd5e1';
        btnTracking.style.background = '#2563eb';
        btnTracking.style.color = '#ffffff';
        btnTracking.style.border = 'none';
    }
}

document.addEventListener("DOMContentLoaded", function () {
    var modeEl = document.getElementById("home-calc-mode");
    var routeEl = document.getElementById("home-calc-route");
    var weightEl = document.getElementById("home-calc-weight");
    var resEl = document.getElementById("home-calc-result");
    var eurEl = document.getElementById("home-calc-eur");
    var linkEl = document.getElementById("home-calc-link");

    function updateHomeCalc() {
        if (!modeEl || !weightEl || !resEl) return;
        var mode = modeEl.value;
        var weight = parseFloat(weightEl.value) || 1;
        var ratePerKg = 4500; // Fret Aérien
        if (mode === 'maritime') ratePerKg = 2500;
        if (mode === 'dhl') ratePerKg = 8500;

        var totalXof = Math.round(weight * ratePerKg);
        var totalEur = (totalXof / 655.957).toFixed(2);

        resEl.innerText = totalXof.toLocaleString("fr-FR") + " XOF";
        eurEl.innerText = "(~" + totalEur + " €)";
        if (linkEl) {
            linkEl.href = "<?= View::url('site/devis') ?>?mode=" + mode + "&weight=" + weight + "&route=" + routeEl.value;
        }
    }

    if (modeEl) modeEl.addEventListener("change", updateHomeCalc);
    if (routeEl) routeEl.addEventListener("change", updateHomeCalc);
    if (weightEl) weightEl.addEventListener("input", updateHomeCalc);
    updateHomeCalc();
});
</script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/site.php';
