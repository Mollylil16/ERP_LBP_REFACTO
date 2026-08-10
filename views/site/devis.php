<?php
use App\Helpers\View;
use App\View\Components\Form;
use App\View\Components\Site;
use App\View\Pages\Site\SitePage;

/** @var SitePage $page */
ob_start();
$preMode = $_GET['mode'] ?? 'aerien';
$preWeight = $_GET['weight'] ?? '10';
$preRoute = $_GET['route'] ?? 'CIV_FR';
?>
<div class="site-content">
<section class="site-page-hero site-page-hero--quote" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-radius: 20px; padding: 35px 40px; color: #ffffff; margin-bottom: 25px; box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.35);">
    <span style="display:inline-flex; align-items:center; gap:6px; background:rgba(37,99,235,0.2); color:#60a5fa; border:1px solid rgba(96,165,250,0.3); padding:4px 12px; border-radius:20px; font-size:0.8rem; font-weight:700; text-transform:uppercase; margin-bottom:12px;">
        <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" stroke-width="2.5" fill="none"><rect x="2" y="4" width="20" height="16" rx="2"/><line x1="6" y1="8" x2="6" y2="8"/><line x1="10" y1="8" x2="18" y2="8"/></svg>
        Devis Express ERP Direct
    </span>
    <h1 style="color:#ffffff; font-size:2.2rem; font-weight:800; margin:0 0 10px 0;">Demande de Devis Fret & Transit</h1>
    <p style="color:#94a3b8; font-size:1rem; margin:0;">Remplissez ce formulaire pour recevoir votre cotation officielle personnalisée par notre équipe commerciale sous 24h.</p>
</section>

<section class="site-form-layout" style="display:grid; grid-template-columns:1fr 340px; gap:25px;">
    <form class="site-form-card" method="post" action="<?= View::url('site/devis') ?>" style="background:#ffffff; border-radius:18px; border:1px solid #e2e8f0; padding:30px; box-shadow:0 10px 25px -5px rgba(15,23,42,0.05);">
        
        <h3 style="font-size:1.15rem; font-weight:800; color:#0f172a; margin-top:0; margin-bottom:20px; border-bottom:2px solid #f1f5f9; padding-bottom:10px; display:flex; align-items:center; gap:8px;">
            <svg viewBox="0 0 24 24" width="20" height="20" stroke="#2563eb" stroke-width="2.2" fill="none"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            1. Informations de Contact
        </h3>
        
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:20px;">
            <?= Form::input('customer_name', ['label' => 'Nom complet / Entreprise', 'placeholder' => 'Ex: Koffi Mensah / SIFCA S.A.', 'required' => true]) ?>
            <?= Form::input('phone', ['label' => 'Téléphone / WhatsApp', 'placeholder' => 'Ex: +225 07 00 00 00 00', 'required' => true]) ?>
        </div>
        <div style="margin-bottom:24px;">
            <?= Form::input('email', ['label' => 'Adresse Email', 'placeholder' => 'Ex: client@entreprise.com', 'type' => 'email']) ?>
        </div>

        <h3 style="font-size:1.15rem; font-weight:800; color:#0f172a; margin-top:10px; margin-bottom:20px; border-bottom:2px solid #f1f5f9; padding-bottom:10px; display:flex; align-items:center; gap:8px;">
            <svg viewBox="0 0 24 24" width="20" height="20" stroke="#2563eb" stroke-width="2.2" fill="none"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            2. Spécifications du Transport
        </h3>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:20px;">
            <?= Form::input('origin_country', ['label' => 'Pays de départ', 'placeholder' => 'Ex: Côte d\'Ivoire (Abidjan)', 'value' => str_contains($preRoute, 'FR_CIV') ? 'France (Paris)' : 'Côte d\'Ivoire (Abidjan)']) ?>
            <?= Form::input('destination_country', ['label' => 'Pays d\'arrivée', 'placeholder' => 'Ex: France (Paris)', 'value' => str_contains($preRoute, 'FR_CIV') ? 'Côte d\'Ivoire (Abidjan)' : 'France (Paris)']) ?>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:20px;">
            <div>
                <label style="display:block; font-size:0.8rem; font-weight:700; color:#475569; margin-bottom:6px;">MODE DE TRANSPORT</label>
                <select name="transport_mode" style="width:100%; padding:11px 14px; border-radius:10px; border:1px solid #cbd5e1; background:#f8fafc; font-weight:600; font-size:0.9rem; outline:none;">
                    <option value="aerien" <?= $preMode === 'aerien' ? 'selected' : '' ?>>Fret Aérien (Express/Groupage)</option>
                    <option value="maritime" <?= $preMode === 'maritime' ? 'selected' : '' ?>>Fret Maritime (Conteneur / m³)</option>
                    <option value="dhl" <?= $preMode === 'dhl' ? 'selected' : '' ?>>DHL Express International</option>
                </select>
            </div>
            <div>
                <label style="display:block; font-size:0.8rem; font-weight:700; color:#475569; margin-bottom:6px;">POIDS ESTIMÉ (KG)</label>
                <input type="number" name="weight" value="<?= View::e($preWeight) ?>" min="1" step="0.5" style="width:100%; padding:11px 14px; border-radius:10px; border:1px solid #cbd5e1; background:#f8fafc; font-weight:700; font-size:0.9rem; outline:none;">
            </div>
        </div>

        <div style="margin-bottom:24px;">
            <?= Form::textarea('goods_description', [
                'label' => 'Description précise de la marchandise & dimensions',
                'rows' => 4,
                'placeholder' => 'Précisez le type de colis (cartons, palettes, matériel médical...), les dimensions (L x l x h) et tout besoin d\'assurance ou de douane spécifique...',
            ]) ?>
        </div>

        <button type="submit" style="width:100%; background:#2563eb; color:#ffffff; font-weight:800; padding:14px; border-radius:12px; border:none; font-size:1.05rem; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:10px; box-shadow:0 4px 14px rgba(37,99,235,0.35);">
            <span>Soumettre la demande de devis</span>
            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2.5" fill="none"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </button>
    </form>

    <aside style="display:flex; flex-direction:column; gap:20px;">
        <div style="background:#ffffff; border-radius:18px; border:1px solid #e2e8f0; padding:24px; box-shadow:0 10px 25px -5px rgba(15,23,42,0.05);">
            <strong style="font-size:1.05rem; color:#0f172a; display:block; margin-bottom:12px; font-weight:800;">Documents à préparer</strong>
            <ul style="padding-left:20px; color:#475569; font-size:0.88rem; margin:0; line-height:1.7;">
                <li>Facture commerciale fournisseur</li>
                <li>Liste de colisage (Packing list)</li>
                <li>Attestation ou certificat si exigé</li>
                <li>Adresse d'enlèvement et de livraison</li>
            </ul>
        </div>

        <div style="background:#eff6ff; border-radius:18px; border:1px solid #bfdbfe; padding:24px; color:#1e40af;">
            <strong style="font-size:1rem; display:block; margin-bottom:6px; font-weight:800;">💡 Besoin d'assistance immédiate ?</strong>
            <p style="font-size:0.85rem; margin:0 0 14px 0; color:#1e3a8a;">Contactez directement un déclarant en douane ou conseiller fret de notre équipe.</p>
            <a href="tel:+22507000001" style="background:#2563eb; color:#ffffff; font-weight:700; padding:10px 16px; border-radius:8px; text-decoration:none; display:inline-flex; align-items:center; gap:6px; font-size:0.85rem;">
                📞 Appeler un conseiller
            </a>
        </div>
    </aside>
</section>
</div>
<?php $content = ob_get_clean(); require BASE_PATH . '/views/layouts/site.php';
