<?php

declare(strict_types=1);

namespace App\Repositories\Site;

use PDO;

final class WebsiteRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<string,mixed> */
    public function branding(): array
    {
        return $this->pdo->query('SELECT * FROM website_branding WHERE id = 1 LIMIT 1')->fetch() ?: [];
    }

    /** @return array<int,array<string,mixed>> */
    public function slides(bool $activeOnly = true): array
    {
        $where = $activeOnly ? 'WHERE is_active = 1' : '';
        return $this->pdo->query("SELECT * FROM website_slides {$where} ORDER BY sort_order, id")->fetchAll() ?: [];
    }

    /** @return array<int,array<string,mixed>> */
    public function services(): array
    {
        return $this->pdo->query(
            'SELECT * FROM website_services WHERE is_active = 1 ORDER BY sort_order, id'
        )->fetchAll() ?: [];
    }

    /** @return array<int,array<string,mixed>> */
    public function products(bool $activeOnly = true): array
    {
        $where = $activeOnly ? 'WHERE is_active = 1' : '';
        return $this->pdo->query(
            "SELECT * FROM website_products {$where} ORDER BY is_featured DESC, sort_order, id"
        )->fetchAll() ?: [];
    }

    /** @return array<int,array<string,mixed>> */
    public function topics(int $limit = 12): array
    {
        $limit = max(1, min(50, $limit));
        return $this->pdo->query(
            "SELECT * FROM website_forum_topics WHERE is_published = 1
             ORDER BY is_pinned DESC, COALESCE(last_activity_at, created_at) DESC, id DESC
             LIMIT {$limit}"
        )->fetchAll() ?: [];
    }

    /** @param array<string,string> $branding */
    public function updateBranding(array $branding): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE website_branding SET
                company_name = :company_name,
                tagline = :tagline,
                logo_text = :logo_text,
                logo_url = :logo_url,
                primary_color = :primary_color,
                secondary_color = :secondary_color,
                accent_color = :accent_color,
                surface_color = :surface_color,
                font_family = :font_family,
                announcement = :announcement,
                updated_at = NOW()
            WHERE id = 1
        ");
        $stmt->execute($branding);
    }

    /** @param array<string,mixed> $slide */
    public function saveSlide(array $slide): void
    {
        $id = (int) ($slide['id'] ?? 0);
        if ($id > 0) {
            $stmt = $this->pdo->prepare("
                UPDATE website_slides SET eyebrow = :eyebrow, title = :title,
                    description = :description, image_url = :image_url,
                    primary_label = :primary_label, primary_url = :primary_url,
                    secondary_label = :secondary_label, secondary_url = :secondary_url,
                    overlay_color = :overlay_color, is_active = :is_active,
                    sort_order = :sort_order, updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute($slide);
            return;
        }

        unset($slide['id']);
        $stmt = $this->pdo->prepare("
            INSERT INTO website_slides
                (eyebrow, title, description, image_url, primary_label, primary_url,
                 secondary_label, secondary_url, overlay_color, is_active, sort_order)
            VALUES
                (:eyebrow, :title, :description, :image_url, :primary_label, :primary_url,
                 :secondary_label, :secondary_url, :overlay_color, :is_active, :sort_order)
        ");
        $stmt->execute($slide);
    }

    /** @param array<string,mixed> $product */
    public function saveProduct(array $product): void
    {
        $id = (int) ($product['id'] ?? 0);
        if ($id > 0) {
            $stmt = $this->pdo->prepare("
                UPDATE website_products SET sku = :sku, name = :name, category = :category,
                    summary = :summary, price = :price, currency = :currency,
                    image_url = :image_url, badge = :badge, stock_status = :stock_status,
                    is_featured = :is_featured, is_active = :is_active,
                    sort_order = :sort_order, updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute($product);
            return;
        }

        unset($product['id']);
        $stmt = $this->pdo->prepare("
            INSERT INTO website_products
                (sku, name, category, summary, price, currency, image_url, badge,
                 stock_status, is_featured, is_active, sort_order)
            VALUES
                (:sku, :name, :category, :summary, :price, :currency, :image_url, :badge,
                 :stock_status, :is_featured, :is_active, :sort_order)
        ");
        $stmt->execute($product);
    }
}
