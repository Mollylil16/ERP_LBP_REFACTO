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
