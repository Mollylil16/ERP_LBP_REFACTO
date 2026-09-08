<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use App\View\Components\Form;

final class Site
{
    public static function button(string $label, array $options = []): string
    {
        $variant = (string) ($options['variant'] ?? 'primary');
        $options['class'] = Html::classes(['site-button', 'site-button--' . $variant, (string) ($options['class'] ?? '')]);
        $options['variant'] = $variant;
        return Ui::button($label, $options);
    }
    public static function icon(string $name): string
    {
        $icons = [
            'customs' => '<svg viewBox="0 0 24 24"><path d="M5 4h14v5c0 5.5-3 9-7 11-4-2-7-5.5-7-11V4Z"/><path d="M8 11h8M12 7v8"/></svg>',
            'freight' => '<svg viewBox="0 0 24 24"><path d="M3 16h18M6 16V8l6-3 6 3v8"/><path d="M8 16v3M16 16v3M9 10h6"/></svg>',
            'tracking' => '<svg viewBox="0 0 24 24"><path d="M12 21s7-5.1 7-12A7 7 0 1 0 5 9c0 6.9 7 12 7 12Z"/><circle cx="12" cy="9" r="2.4"/></svg>',
            'delivery' => '<svg viewBox="0 0 24 24"><path d="M3 7h11v10H3zM14 11h4l3 3v3h-7z"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/></svg>',
        ];
        return $icons[$name] ?? $icons['tracking'];
    }

    /** @param array<int,array<string,string>> $stats */
    public static function stats(array $stats): string
    {
        $html = '<section class="site-stats" aria-label="Indicateurs LBP Transit">';
        foreach ($stats as $stat) {
            $html .= '<article><strong>' . View::e((string) ($stat['value'] ?? ''))
                . '</strong><span>' . View::e((string) ($stat['label'] ?? '')) . '</span></article>';
        }
        return $html . '</section>';
    }

    /** @param array<int,array<string,mixed>> $services */
    public static function services(array $services): string
    {
        $html = '<section class="site-grid site-grid--four">';
        foreach ($services as $service) {
            $html .= '<article class="site-service-card"><span class="site-service-card__icon">'
                . self::icon((string) ($service['icon'] ?? 'tracking')) . '</span><h3>'
                . View::e((string) ($service['title'] ?? '')) . '</h3><p>'
                . View::e((string) ($service['text'] ?? $service['summary'] ?? '')) . '</p>'
                . '<a href="' . View::url('site/devis') . '">Découvrir <span>→</span></a></article>';
        }
        return $html . '</section>';
    }

    /** @param array<int,array<string,mixed>> $slides */
    public static function carousel(array $slides): string
    {
        $html = '<section class="site-carousel" data-site-carousel>';
        foreach ($slides as $index => $slide) {
            $image = self::assetUrl((string) ($slide['image_url'] ?? 'images/site/hero-logistics.svg'));
            $overlay = self::safeColor((string) ($slide['overlay_color'] ?? '#111c44'));
            $html .= '<article class="site-carousel__slide' . ($index === 0 ? ' is-active' : '')
                . '" data-carousel-slide style="--slide-image:url(\'' . View::e($image)
                . '\');--slide-overlay:' . View::e($overlay) . '"><div class="site-carousel__shade"></div>'
                . '<div class="site-carousel__content"><p class="site-kicker">'
                . View::e((string) ($slide['eyebrow'] ?? 'LBP Transit')) . '</p><h1>'
                . View::e((string) ($slide['title'] ?? '')) . '</h1><p>'
                . View::e((string) ($slide['description'] ?? '')) . '</p><div class="site-cta-row">'
                . self::link((string) ($slide['primary_label'] ?? ''), (string) ($slide['primary_url'] ?? ''), 'primary')
                . self::link((string) ($slide['secondary_label'] ?? ''), (string) ($slide['secondary_url'] ?? ''), 'ghost')
                . '</div></div></article>';
        }
        $html .= '<div class="site-carousel__controls"><button type="button" data-carousel-prev aria-label="Slide précédent">←</button>'
            . '<div class="site-carousel__dots">';
        foreach ($slides as $index => $_slide) {
            $html .= '<button type="button" data-carousel-dot="' . $index . '" class="'
                . ($index === 0 ? 'is-active' : '') . '" aria-label="Afficher le slide ' . ($index + 1) . '"></button>';
        }
        return $html . '</div><button type="button" data-carousel-next aria-label="Slide suivant">→</button></div></section>';
    }

    public static function trackingDock(string $reference): string
    {
        return '<section class="site-tracking-dock"><div><span>Suivi international</span>'
            . '<strong>Où se trouve votre expédition ?</strong></div>'
            . '<form method="get" action="' . View::url('site/tracking') . '">'
            . Form::inputControl('ref', ['value' => $reference, 'placeholder' => 'N° colis, BL ou dossier', 'aria-label' => 'Référence de suivi'])
            . '<button type="submit">Suivre maintenant <span>→</span></button></form>'
            . '<small>Résultat instantané, sans création de compte</small></section>';
    }

    public static function sectionHeading(string $eyebrow, string $title, string $description = '', string $action = ''): string
    {
        return '<header class="site-section-heading"><div><p class="site-kicker">' . View::e($eyebrow)
            . '</p><h2>' . View::e($title) . '</h2>'
            . ($description !== '' ? '<p>' . View::e($description) . '</p>' : '')
            . '</div>' . $action . '</header>';
    }

    /** @param array<int,array<string,mixed>> $products */
    public static function products(array $products, int $limit = 0): string
    {
        $products = $limit > 0 ? array_slice($products, 0, $limit) : $products;
        if (empty($products)) {
            return '<div style="background:#ffffff; border-radius:18px; border:1px dashed #cbd5e1; padding:45px 24px; text-align:center; margin:20px 0; box-shadow:0 4px 15px rgba(15,23,42,0.03);">'
                . '<div style="width:54px; height:54px; border-radius:16px; background:#eff6ff; color:#2563eb; display:flex; align-items:center; justify-content:center; margin:0 auto 16px auto;">'
                . '<svg viewBox="0 0 24 24" width="26" height="26" stroke="currentColor" stroke-width="2" fill="none"><rect x="2" y="4" width="20" height="16" rx="2"/><line x1="6" y1="8" x2="6" y2="8"/><line x1="10" y1="8" x2="18" y2="8"/></svg>'
                . '</div>'
                . '<h3 style="font-size:1.15rem; font-weight:800; color:#0f172a; margin:0 0 8px 0;">Marketplace en cours d\'approvisionnement</h3>'
                . '<p style="color:#64748b; font-size:0.92rem; max-width:540px; margin:0 auto 20px auto; line-height:1.5;">La Direction Générale et l\'équipe logistique publieront prochainement les tarifs officiels de kits d\'emballage export et de réservation de fret.</p>'
                . '<a href="' . View::url('site/contact') . '" style="display:inline-flex; align-items:center; gap:8px; background:#0C2A4A; color:#ffffff; font-weight:700; padding:12px 24px; border-radius:12px; text-decoration:none; font-size:0.92rem;">Demander un devis sur mesure ➔</a>'
                . '</div>';
        }

        $html = '<section class="site-product-grid">';
        foreach ($products as $product) {
            $price = number_format((float) ($product['price'] ?? 0), 0, ',', ' ');
            $image = trim((string) ($product['image_url'] ?? ''));
            $visualStyle = $image !== ''
                ? ' style="--product-image:url(\'' . View::e(self::assetUrl($image)) . '\')"'
                : '';
            $html .= '<article class="site-product-card"><div class="site-product-card__visual'
                . ($image !== '' ? ' has-image' : '') . '"' . $visualStyle . '>'
                . '<span>' . View::e((string) ($product['category'] ?? 'Service')) . '</span>'
                . self::icon(self::productIcon((string) ($product['category'] ?? '')))
                . (($product['badge'] ?? '') !== '' ? '<em>' . View::e((string) $product['badge']) . '</em>' : '')
                . '</div><div class="site-product-card__body"><small>'
                . View::e((string) ($product['sku'] ?? '')) . '</small><h3>'
                . View::e((string) ($product['name'] ?? '')) . '</h3><p>'
                . View::e((string) ($product['summary'] ?? '')) . '</p><footer><strong>'
                . $price . ' ' . View::e((string) ($product['currency'] ?? 'XOF'))
                . '</strong><button type="button" data-add-cart data-product="'
                . View::e((string) ($product['name'] ?? 'Produit')) . '" data-price="'
                . View::e((string) ($product['price'] ?? 0)) . '">Ajouter</button></footer></div></article>';
        }
        return $html . '</section>';
    }

    /** @param array<int,array<string,mixed>> $topics */
    public static function topics(array $topics, int $limit = 0): string
    {
        $topics = $limit > 0 ? array_slice($topics, 0, $limit) : $topics;
        if (empty($topics)) {
            return '<div style="background:#ffffff; border-radius:18px; border:1px dashed #cbd5e1; padding:45px 24px; text-align:center; margin:20px 0; box-shadow:0 4px 15px rgba(15,23,42,0.03);">'
                . '<div style="width:54px; height:54px; border-radius:16px; background:#ecfdf5; color:#10b981; display:flex; align-items:center; justify-content:center; margin:0 auto 16px auto;">'
                . '<svg viewBox="0 0 24 24" width="26" height="26" stroke="currentColor" stroke-width="2" fill="none"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>'
                . '</div>'
                . '<h3 style="font-size:1.15rem; font-weight:800; color:#0f172a; margin:0 0 8px 0;">Aucune discussion publique pour le moment</h3>'
                . '<p style="color:#64748b; font-size:0.92rem; max-width:540px; margin:0 auto 20px auto; line-height:1.5;">Soyez le premier expéditeur à démarrer un fil de discussion ou posez vos questions douanières directement à nos conseillers en agence.</p>'
                . '<a href="' . View::url('site/contact') . '" style="display:inline-flex; align-items:center; gap:8px; background:#10b981; color:#ffffff; font-weight:700; padding:12px 24px; border-radius:12px; text-decoration:none; font-size:0.92rem;">Contacter un expert agence ➔</a>'
                . '</div>';
        }

        $html = '<section class="site-forum-list">';
        foreach ($topics as $topic) {
            $html .= '<article><div class="site-forum-avatar">'
                . View::e(strtoupper(substr((string) ($topic['author_name'] ?? 'L'), 0, 1)))
                . '</div><div><span>' . View::e((string) ($topic['category'] ?? 'Discussion'))
                . (!empty($topic['is_pinned']) ? ' · Épinglé' : '') . '</span><h3>'
                . View::e((string) ($topic['title'] ?? '')) . '</h3><p>'
                . View::e((string) ($topic['excerpt'] ?? '')) . '</p><small>Par '
                . View::e((string) ($topic['author_name'] ?? 'Équipe LBP')) . '</small></div>'
                . '<aside><strong>' . (int) ($topic['replies_count'] ?? 0)
                . '</strong><span>réponses</span><small>' . (int) ($topic['views_count'] ?? 0)
                . ' vues</small></aside></article>';
        }
        return $html . '</section>';
    }

    public static function partnerLogos(): string
    {
        $partners = [
            ['name' => 'DHL Express', 'file' => '19336-dhl-logo.png'],
            ['name' => 'Air France Cargo', 'file' => 'Air-France-Logo-1.png'],
            ['name' => 'CMA CGM', 'file' => 'CMA_CGM_logo.jpg'],
            ['name' => 'Maersk Line', 'file' => 'Maersk_Group_Logo.jpg'],
            ['name' => 'Corsair International', 'file' => 'corsair.jpg'],
            ['name' => 'FedEx Express', 'file' => 'logo-fedex-3.jpg'],
            ['name' => 'Port Autonome d\'Abidjan', 'file' => 'paa.jpg'],
        ];

        $html = '<section style="margin:50px 0; text-align:center;">'
            . '<span style="font-size:0.75rem; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:1.5px; display:block; margin-bottom:20px;">PARTENAIRES MARITIMES, AÉRIENS & DOUANIERS</span>'
            . '<div style="display:flex; flex-wrap:wrap; align-items:center; justify-content:center; gap:30px; opacity:0.85;">';

        foreach ($partners as $p) {
            $imgUrl = View::asset('assets/images/img partenaire/' . $p['file']);
            $html .= '<div style="background:#ffffff; padding:12px 20px; border-radius:12px; border:1px solid #e2e8f0; box-shadow:0 4px 12px rgba(15,23,42,0.04); display:flex; align-items:center; justify-content:center; height:60px;">'
                . '<img src="' . View::e($imgUrl) . '" alt="' . View::e($p['name']) . '" style="max-height:36px; max-width:110px; object-fit:contain;" />'
                . '</div>';
        }

        $html .= '</div></section>';
        return $html;
    }

    public static function directorNote(): string
    {
        $dgImg = View::asset('images/dg_Serge_Kadjo-la-belle-porte.png');
        return '<section style="background:linear-gradient(135deg, #0C2A4A 0%, #17385c 100%); border-radius:24px; padding:45px 40px; color:#ffffff; margin:60px 0; box-shadow:0 20px 40px -10px rgba(12,42,74,0.3); border:1px solid rgba(255,255,255,0.08); overflow:hidden;">'
            . '<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:40px; align-items:center;">'
            . '<div>'
            . '<span style="display:inline-flex; align-items:center; gap:8px; background:rgba(16,185,129,0.2); color:#34d399; border:1px solid rgba(52,211,153,0.35); padding:5px 16px; border-radius:20px; font-size:0.8rem; font-weight:800; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:18px;">'
            . '<span style="width:8px; height:8px; border-radius:50%; background:#34d399; display:inline-block;"></span>'
            . 'MOT DE LA DIRECTION GÉNÉRALE'
            . '</span>'
            . '<h2 style="font-size:2rem; font-weight:900; color:#ffffff; margin:0 0 16px 0; line-height:1.25; letter-spacing:-0.5px;">'
            . '« Assurer la fluidité et la sécurité de vos échanges intercontinentaux avec rigueur. »'
            . '</h2>'
            . '<p style="color:#cbd5e1; font-size:1.02rem; line-height:1.65; margin:0 0 24px 0;">'
            . 'Chez LA BELLE PORTE TRANSIT, chaque colis et conteneur confié représente un engagement d\'excellence. Nos agences directes à Abidjan, Paris-Bobigny, Dakar et Montréal garantissent un contrôle physique et digital de bout en bout, sans intermédiaire anonyme.'
            . '</p>'
            . '<div style="display:flex; align-items:center; gap:16px; padding-top:14px; border-top:1px solid rgba(255,255,255,0.1);">'
            . '<div style="width:52px; height:52px; border-radius:50%; border:2px solid #10b981; overflow:hidden; background:#ffffff; flex-shrink:0;">'
            . '<img src="' . View::e($dgImg) . '" alt="Serge Kadjo" style="width:100%; height:100%; object-fit:cover; object-position:top;" />'
            . '</div>'
            . '<div>'
            . '<strong style="display:block; font-size:1.1rem; color:#ffffff; font-weight:800;">M. Serge KADJO</strong>'
            . '<span style="color:#34d399; font-weight:700; font-size:0.85rem;">Directeur Général — LA BELLE PORTE TRANSIT</span>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '<div style="text-align:center; display:flex; justify-content:center;">'
            . '<div style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.15); padding:16px; border-radius:24px; backdrop-filter:blur(10px); box-shadow:0 25px 50px rgba(0,0,0,0.35); max-width:340px; width:100%;">'
            . '<img src="' . View::e($dgImg) . '" alt="M. Serge KADJO, Directeur Général" style="width:100%; height:auto; max-height:360px; object-fit:cover; object-position:top; border-radius:18px; border:1px solid rgba(255,255,255,0.2);" />'
            . '<div style="margin-top:12px; font-size:0.85rem; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.8px;">M. Serge KADJO · Directeur Général</div>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '</section>';
    }

    public static function pageHero(string $eyebrow, string $title, string $description): string
    {
        return '<section class="site-inner-hero"><p class="site-kicker">' . View::e($eyebrow)
            . '</p><h1>' . View::e($title) . '</h1><p>' . View::e($description)
            . '</p><div class="site-inner-hero__orb"></div></section>';
    }

    private static function link(string $label, string $url, string $variant): string
    {
        if ($label === '' || $url === '') {
            return '';
        }
        return '<a class="site-cta site-cta--' . View::e($variant) . '" href="'
            . View::url(ltrim($url, '/')) . '">' . View::e($label) . '<span>→</span></a>';
    }

    private static function assetUrl(string $url): string
    {
        return preg_match('#^(?:https?:)?//#i', $url) ? $url : View::asset(ltrim($url, '/'));
    }

    private static function safeColor(string $color): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#111c44';
    }

    private static function productIcon(string $category): string
    {
        $category = strtolower($category);
        return str_contains($category, 'transport') ? 'freight'
            : (str_contains($category, 'emballage') ? 'delivery'
            : (str_contains($category, 'formalit') ? 'customs' : 'tracking'));
    }
}
