<?php

declare(strict_types=1);

namespace App\Services\Site;

use App\Repositories\Site\WebsiteRepository;
use RuntimeException;

final class WebsiteService
{
    public function __construct(
        private WebsiteRepository $repository,
        private ?SiteMediaUploadService $uploads = null,
    ) {
        $this->uploads ??= new SiteMediaUploadService();
    }

    /** @return array<string,mixed> */
    public function content(): array
    {
        return [
            'branding' => $this->repository->branding(),
            'slides' => $this->repository->slides(),
            'services' => $this->repository->services(),
            'products' => $this->repository->products(),
            'topics' => $this->repository->topics(),
            'announcements' => $this->repository->announcements(),
            'articles' => $this->repository->articles(),
        ];
    }

    /** @return array<string,mixed> */
    public function administration(): array
    {
        return [
            'branding' => $this->repository->branding(),
            'slides' => $this->repository->slides(false),
            'products' => $this->repository->products(false),
            'announcements' => $this->repository->announcements(false),
            'articles' => $this->repository->articles(false),
        ];
    }

    /** @return array<string,mixed>|null */
    public function article(string $slug): ?array
    {
        return $this->repository->article($slug);
    }

    /** @param array<string,mixed> $input */
    public function updateBranding(array $input): void
    {
        $companyName = trim((string) ($input['company_name'] ?? ''));
        if ($companyName === '') {
            throw new RuntimeException('Le nom public de l’entreprise est obligatoire.');
        }

        $this->repository->updateBranding([
            'company_name' => $companyName,
            'tagline' => $this->text($input['tagline'] ?? null),
            'logo_text' => strtoupper(substr(trim((string) ($input['logo_text'] ?? 'LBP')), 0, 8)),
            'logo_url' => $this->text($input['logo_url'] ?? null),
            'primary_color' => $this->color($input['primary_color'] ?? null, '#111c44'),
            'secondary_color' => $this->color($input['secondary_color'] ?? null, '#ffcc00'),
            'accent_color' => $this->color($input['accent_color'] ?? null, '#d40511'),
            'surface_color' => $this->color($input['surface_color'] ?? null, '#f5f7fb'),
            'font_family' => $this->font($input['font_family'] ?? null),
            'announcement' => $this->text($input['announcement'] ?? null),
        ]);
    }

    /** @param array<string,mixed> $input */
    public function saveSlide(array $input, ?array $image = null): void
    {
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new RuntimeException('Le titre du slide est obligatoire.');
        }

        $uploadedImage = $this->uploads->storeSlide($image);
        $this->repository->saveSlide([
            'id' => (int) ($input['id'] ?? 0),
            'eyebrow' => $this->text($input['eyebrow'] ?? null),
            'title' => $title,
            'description' => $this->text($input['description'] ?? null),
            'image_url' => $uploadedImage ?? $this->text($input['image_url'] ?? null),
            'primary_label' => $this->text($input['primary_label'] ?? null),
            'primary_url' => $this->text($input['primary_url'] ?? null),
            'secondary_label' => $this->text($input['secondary_label'] ?? null),
            'secondary_url' => $this->text($input['secondary_url'] ?? null),
            'overlay_color' => $this->color($input['overlay_color'] ?? null, '#111c44'),
            'is_active' => !empty($input['is_active']) ? 1 : 0,
            'sort_order' => (int) ($input['sort_order'] ?? 0),
        ]);
    }

    /** @param array<string,mixed> $input */
    public function saveProduct(array $input): void
    {
        $name = trim((string) ($input['name'] ?? ''));
        $sku = strtoupper(trim((string) ($input['sku'] ?? '')));
        if ($name === '' || $sku === '') {
            throw new RuntimeException('Le nom et la référence SKU sont obligatoires.');
        }

        $this->repository->saveProduct([
            'id' => (int) ($input['id'] ?? 0),
            'sku' => preg_replace('/[^A-Z0-9_-]/', '-', $sku),
            'name' => $name,
            'category' => $this->text($input['category'] ?? null),
            'summary' => $this->text($input['summary'] ?? null),
            'price' => max(0, (float) ($input['price'] ?? 0)),
            'currency' => strtoupper(substr(trim((string) ($input['currency'] ?? 'XOF')), 0, 10)),
            'image_url' => $this->text($input['image_url'] ?? null),
            'badge' => $this->text($input['badge'] ?? null),
            'stock_status' => in_array(($input['stock_status'] ?? ''), ['available', 'on_request', 'unavailable'], true)
                ? $input['stock_status'] : 'available',
            'is_featured' => !empty($input['is_featured']) ? 1 : 0,
            'is_active' => !empty($input['is_active']) ? 1 : 0,
            'sort_order' => (int) ($input['sort_order'] ?? 0),
        ]);
    }

    /** @param array<string,mixed> $input */
    public function saveAnnouncement(array $input): void
    {
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') throw new RuntimeException('Le texte de l’annonce est obligatoire.');
        $this->repository->saveAnnouncement([
            'id' => (int) ($input['id'] ?? 0),
            'badge' => $this->text($input['badge'] ?? null),
            'title' => $title,
            'link_label' => $this->text($input['link_label'] ?? null),
            'link_url' => $this->text($input['link_url'] ?? null),
            'starts_at' => $this->text($input['starts_at'] ?? null),
            'ends_at' => $this->text($input['ends_at'] ?? null),
            'is_active' => !empty($input['is_active']) ? 1 : 0,
            'sort_order' => (int) ($input['sort_order'] ?? 0),
        ]);
    }

    /** @param array<string,mixed> $input */
    public function saveArticle(array $input): void
    {
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') throw new RuntimeException('Le titre de l’article est obligatoire.');
        $slug = trim((string) ($input['slug'] ?? ''));
        $slug = trim(strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $slug !== '' ? $slug : $title)), '-');
        $published = !empty($input['is_published']);
        $this->repository->saveArticle([
            'id' => (int) ($input['id'] ?? 0),
            'slug' => $slug,
            'title' => $title,
            'excerpt' => $this->text($input['excerpt'] ?? null),
            'content' => $this->text($input['content'] ?? null),
            'image_url' => $this->text($input['image_url'] ?? null),
            'author_name' => $this->text($input['author_name'] ?? null) ?? 'Équipe LBP',
            'is_published' => $published ? 1 : 0,
            'published_at' => $published ? (($input['published_at'] ?? '') ?: date('Y-m-d H:i:s')) : null,
        ]);
    }

    private function color(mixed $value, string $fallback): string
    {
        $value = trim((string) $value);
        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : $fallback;
    }

    private function font(mixed $value): string
    {
        $allowed = ['Inter', 'Manrope', 'Montserrat', 'Poppins', 'Roboto'];
        $value = trim((string) $value);
        return in_array($value, $allowed, true) ? $value : 'Inter';
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }
}
