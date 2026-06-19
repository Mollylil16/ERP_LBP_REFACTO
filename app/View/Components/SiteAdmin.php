<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use App\View\Pages\SiteAdmin\ConfigurationPage;

final class SiteAdmin
{
    public static function configuration(ConfigurationPage $page): string
    {
        $brand = $page->branding;
        $html = Ui::pageHeader(
            'Design et contenus du site public',
            'Personnalisez l’identité, le carrousel et les blocs commerciaux sans modifier les vues.',
            [
                'eyebrow' => 'Site Internet',
                'actions' => [Ui::button('Prévisualiser le site', ['href' => 'site', 'variant' => 'secondary'])],
            ]
        );
        $html .= '<div class="site-admin-grid"><form class="finea-section-card site-admin-form" method="post" action="'
            . View::url('site-admin/configuration/branding') . '">'
            . Form::hidden('_csrf_token', $page->csrfToken)
            . '<div class="finea-section-heading"><h2 class="finea-section-title">Identité de marque</h2><span>Logo, typographie et palette</span></div>'
            . '<div class="site-admin-fields">'
            . Form::input('company_name', ['label' => 'Nom public', 'value' => $brand['company_name'] ?? 'LBP Transit', 'required' => true])
            . Form::input('logo_text', ['label' => 'Monogramme', 'value' => $brand['logo_text'] ?? 'LBP', 'maxlength' => 8])
            . Form::input('tagline', ['label' => 'Signature', 'value' => $brand['tagline'] ?? ''])
            . Form::input('logo_url', ['label' => 'URL ou chemin du logo', 'value' => $brand['logo_url'] ?? '', 'placeholder' => 'images/site/logo.svg'])
            . Form::select('font_family', [
                ['value' => 'Inter', 'label' => 'Inter'],
                ['value' => 'Manrope', 'label' => 'Manrope'],
                ['value' => 'Montserrat', 'label' => 'Montserrat'],
                ['value' => 'Poppins', 'label' => 'Poppins'],
                ['value' => 'Roboto', 'label' => 'Roboto'],
            ], $brand['font_family'] ?? 'Inter', ['label' => 'Typographie'])
            . Form::input('announcement', ['label' => 'Bandeau d’annonce', 'value' => $brand['announcement'] ?? ''])
            . '</div><div class="site-admin-colors">'
            . Form::colorPalette('primary_color', 'Couleur principale', (string) ($brand['primary_color'] ?? '#111c44'))
            . Form::colorPalette('secondary_color', 'Couleur secondaire', (string) ($brand['secondary_color'] ?? '#ffcc00'))
            . Form::colorPalette('accent_color', 'Couleur d’action', (string) ($brand['accent_color'] ?? '#d40511'))
            . Form::colorPalette('surface_color', 'Couleur de surface', (string) ($brand['surface_color'] ?? '#f5f7fb'))
            . '</div><footer>' . Ui::button('Enregistrer le branding', ['variant' => 'primary', 'type' => 'submit']) . '</footer></form>'
            . self::brandPreview($brand) . '</div>';

        $html .= '<section id="carousel" class="finea-section-card site-admin-carousel"><div class="finea-section-heading"><h2 class="finea-section-title">Carrousel d’accueil</h2><span>'
            . count($page->slides) . ' slide(s)</span></div><div class="site-admin-slide-list">';
        foreach ($page->slides as $slide) {
            $html .= self::slideForm($slide, $page->csrfToken);
        }
        $html .= self::slideForm([], $page->csrfToken) . '</div></section>'
            . '<section id="marketplace" class="finea-section-card site-admin-carousel"><div class="finea-section-heading"><h2 class="finea-section-title">Marketplace</h2><span>'
            . count($page->products) . ' offre(s)</span></div><div class="site-admin-slide-list">';
        foreach ($page->products as $product) {
            $html .= self::productForm($product, $page->csrfToken);
        }
        $html .= self::productForm([], $page->csrfToken) . '</div></section>'
            . '<section id="announcements" class="finea-section-card site-admin-carousel"><div class="finea-section-heading"><h2 class="finea-section-title">Annonces du bandeau</h2><span>'
            . count($page->announcements) . ' annonce(s)</span></div><div class="site-admin-slide-list">';
        foreach ($page->announcements as $announcement) $html .= self::announcementForm($announcement, $page->csrfToken);
        $html .= self::announcementForm([], $page->csrfToken) . '</div></section>'
            . '<section id="articles" class="finea-section-card site-admin-carousel"><div class="finea-section-heading"><h2 class="finea-section-title">Articles du blog</h2><span>'
            . count($page->articles) . ' article(s)</span></div><div class="site-admin-slide-list">';
        foreach ($page->articles as $article) $html .= self::articleForm($article, $page->csrfToken);
        return $html . self::articleForm([], $page->csrfToken) . '</div></section>';
    }

    /** @param array<string,mixed> $brand */
    private static function brandPreview(array $brand): string
    {
        return '<aside class="site-admin-preview" style="--preview-primary:' . View::e((string) ($brand['primary_color'] ?? '#111c44'))
            . ';--preview-secondary:' . View::e((string) ($brand['secondary_color'] ?? '#ffcc00'))
            . ';--preview-accent:' . View::e((string) ($brand['accent_color'] ?? '#d40511')) . '">'
            . '<span>Prévisualisation</span><div><em>' . View::e((string) ($brand['logo_text'] ?? 'LBP'))
            . '</em><strong>' . View::e((string) ($brand['company_name'] ?? 'LBP Transit')) . '</strong></div>'
            . '<h3>Votre commerce n’a plus de frontières.</h3><p>'
            . View::e((string) ($brand['tagline'] ?? '')) . '</p><button type="button">Appel à l’action</button></aside>';
    }

    /** @param array<string,mixed> $slide */
    private static function slideForm(array $slide, string $csrfToken): string
    {
        $isNew = empty($slide['id']);
        return '<form class="site-admin-slide" method="post" enctype="multipart/form-data" action="' . View::url('site-admin/configuration/slides') . '">'
            . Form::hidden('_csrf_token', $csrfToken)
            . Form::hidden('id', $slide['id'] ?? 0)
            . '<header><strong>' . ($isNew ? 'Ajouter un slide' : 'Slide #' . (int) $slide['id'])
            . '</strong><span>' . (!empty($slide['is_active']) ? 'Visible' : 'Masqué') . '</span></header>'
            . '<div class="site-admin-fields">'
            . Form::input('eyebrow', ['label' => 'Sur-titre', 'value' => $slide['eyebrow'] ?? ''])
            . Form::input('title', ['label' => 'Titre', 'value' => $slide['title'] ?? '', 'required' => true])
            . Form::textarea('description', ['label' => 'Description', 'value' => $slide['description'] ?? '', 'rows' => 3])
            . Form::input('image_url', ['label' => 'Image actuelle / URL', 'value' => $slide['image_url'] ?? '', 'placeholder' => 'images/site/hero-logistics.svg'])
            . Form::dropzone('slide_image', 'Téléverser une nouvelle image', [
                'accept' => 'image/jpeg,image/png,image/webp',
                'hint' => 'JPG, PNG ou WEBP · minimum 1600 × 600 px · recommandé 1920 × 760 px · 8 Mo maximum.',
                'preview' => (string) ($slide['image_url'] ?? ''),
                'class' => 'site-admin-slide-upload',
            ])
            . Form::input('primary_label', ['label' => 'Bouton principal', 'value' => $slide['primary_label'] ?? ''])
            . Form::input('primary_url', ['label' => 'Lien principal', 'value' => $slide['primary_url'] ?? ''])
            . Form::input('secondary_label', ['label' => 'Bouton secondaire', 'value' => $slide['secondary_label'] ?? ''])
            . Form::input('secondary_url', ['label' => 'Lien secondaire', 'value' => $slide['secondary_url'] ?? ''])
            . Form::input('sort_order', ['label' => 'Ordre', 'type' => 'number', 'value' => $slide['sort_order'] ?? 0])
            . Form::checkbox('is_active', ['label' => 'Afficher ce slide', 'checked' => !isset($slide['is_active']) || !empty($slide['is_active'])])
            . '</div>' . Form::colorPalette('overlay_color', 'Couleur du voile', (string) ($slide['overlay_color'] ?? '#111c44'))
            . '<footer>' . Ui::button($isNew ? 'Ajouter au carrousel' : 'Enregistrer le slide', ['variant' => 'primary', 'type' => 'submit']) . '</footer></form>';
    }

    /** @param array<string,mixed> $product */
    private static function productForm(array $product, string $csrfToken): string
    {
        $isNew = empty($product['id']);
        return '<form class="site-admin-slide" method="post" action="' . View::url('site-admin/configuration/products') . '">'
            . Form::hidden('_csrf_token', $csrfToken) . Form::hidden('id', $product['id'] ?? 0)
            . '<header><strong>' . ($isNew ? 'Ajouter une offre' : View::e((string) ($product['name'] ?? 'Offre')))
            . '</strong><span>' . (!empty($product['is_active']) ? 'Publiée' : 'Masquée') . '</span></header>'
            . '<div class="site-admin-fields">'
            . Form::input('sku', ['label' => 'SKU / référence', 'value' => $product['sku'] ?? '', 'required' => true])
            . Form::input('name', ['label' => 'Nom de l’offre', 'value' => $product['name'] ?? '', 'required' => true])
            . Form::input('category', ['label' => 'Catégorie', 'value' => $product['category'] ?? ''])
            . Form::input('badge', ['label' => 'Badge commercial', 'value' => $product['badge'] ?? ''])
            . Form::input('price', ['label' => 'Prix', 'type' => 'number', 'min' => 0, 'step' => '0.01', 'value' => $product['price'] ?? 0])
            . Form::select('currency', [['value' => 'XOF', 'label' => 'XOF'], ['value' => 'EUR', 'label' => 'EUR'], ['value' => 'USD', 'label' => 'USD']], $product['currency'] ?? 'XOF', ['label' => 'Devise'])
            . Form::select('stock_status', [['value' => 'available', 'label' => 'Disponible'], ['value' => 'on_request', 'label' => 'Sur demande'], ['value' => 'unavailable', 'label' => 'Indisponible']], $product['stock_status'] ?? 'available', ['label' => 'Disponibilité'])
            . Form::input('sort_order', ['label' => 'Ordre', 'type' => 'number', 'value' => $product['sort_order'] ?? 0])
            . Form::textarea('summary', ['label' => 'Résumé commercial', 'value' => $product['summary'] ?? '', 'rows' => 3])
            . Form::input('image_url', ['label' => 'Image', 'value' => $product['image_url'] ?? ''])
            . Form::checkbox('is_featured', ['label' => 'Mettre en avant', 'checked' => !empty($product['is_featured'])])
            . Form::checkbox('is_active', ['label' => 'Publier cette offre', 'checked' => !isset($product['is_active']) || !empty($product['is_active'])])
            . '</div><footer>' . Ui::button($isNew ? 'Ajouter à la marketplace' : 'Enregistrer l’offre', ['variant' => 'primary', 'type' => 'submit']) . '</footer></form>';
    }

    private static function announcementForm(array $item, string $csrfToken): string
    {
        return '<form class="site-admin-slide" method="post" action="' . View::url('site-admin/configuration/announcements') . '">'
            . Form::hidden('_csrf_token', $csrfToken) . Form::hidden('id', $item['id'] ?? 0)
            . '<header><strong>' . (empty($item['id']) ? 'Ajouter une annonce' : View::e((string) $item['title'])) . '</strong></header>'
            . '<div class="site-admin-fields">'
            . Form::input('badge', ['label' => 'Badge', 'value' => $item['badge'] ?? 'Nouveau'])
            . Form::input('title', ['label' => 'Texte', 'value' => $item['title'] ?? '', 'required' => true])
            . Form::input('link_label', ['label' => 'Libellé du lien', 'value' => $item['link_label'] ?? 'En savoir plus'])
            . Form::input('link_url', ['label' => 'Lien', 'value' => $item['link_url'] ?? 'site/devis'])
            . Form::input('starts_at', ['label' => 'Début', 'type' => 'datetime-local', 'value' => isset($item['starts_at']) ? str_replace(' ', 'T', (string) $item['starts_at']) : ''])
            . Form::input('ends_at', ['label' => 'Fin', 'type' => 'datetime-local', 'value' => isset($item['ends_at']) ? str_replace(' ', 'T', (string) $item['ends_at']) : ''])
            . Form::input('sort_order', ['label' => 'Ordre', 'type' => 'number', 'value' => $item['sort_order'] ?? 0])
            . Form::checkbox('is_active', ['label' => 'Annonce active', 'checked' => !isset($item['is_active']) || !empty($item['is_active'])])
            . '</div><footer>' . Ui::button('Enregistrer l’annonce', ['variant' => 'primary', 'type' => 'submit']) . '</footer></form>';
    }

    private static function articleForm(array $item, string $csrfToken): string
    {
        return '<form class="site-admin-slide" method="post" action="' . View::url('site-admin/configuration/articles') . '">'
            . Form::hidden('_csrf_token', $csrfToken) . Form::hidden('id', $item['id'] ?? 0)
            . '<header><strong>' . (empty($item['id']) ? 'Créer un article' : View::e((string) $item['title'])) . '</strong></header>'
            . '<div class="site-admin-fields">'
            . Form::input('title', ['label' => 'Titre', 'value' => $item['title'] ?? '', 'required' => true])
            . Form::input('slug', ['label' => 'Slug', 'value' => $item['slug'] ?? ''])
            . Form::input('author_name', ['label' => 'Auteur', 'value' => $item['author_name'] ?? 'Équipe LBP'])
            . Form::input('image_url', ['label' => 'Image', 'value' => $item['image_url'] ?? ''])
            . Form::textarea('excerpt', ['label' => 'Résumé', 'value' => $item['excerpt'] ?? '', 'rows' => 3])
            . Form::textarea('content', ['label' => 'Contenu', 'value' => $item['content'] ?? '', 'rows' => 8])
            . Form::input('published_at', ['label' => 'Publication', 'type' => 'datetime-local', 'value' => isset($item['published_at']) ? str_replace(' ', 'T', (string) $item['published_at']) : ''])
            . Form::checkbox('is_published', ['label' => 'Publier cet article', 'checked' => !empty($item['is_published'])])
            . '</div><footer>' . Ui::button('Enregistrer l’article', ['variant' => 'primary', 'type' => 'submit']) . '</footer></form>';
    }
}
