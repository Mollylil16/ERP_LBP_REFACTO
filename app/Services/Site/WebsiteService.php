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

    /** @return array<string,mixed>|null */
    public function getRealTrackingData(string $reference): ?array
    {
        $reference = trim($reference);
        if ($reference === '') {
            return null;
        }

        $pdo = \App\Models\Database::getConnection();

        // 1. Search in lbp_colis
        $stmt = $pdo->prepare("
            SELECT c.*,
                   exp.name AS expediteur_name,
                   dest.name AS destinataire_name,
                   s_dep.name AS agence_depart_name,
                   s_arr.name AS agence_arrivee_name,
                   r.code_rayon, r.nom_rayon
            FROM lbp_colis c
            LEFT JOIN lbp_clients exp ON c.expediteur_id = exp.id
            LEFT JOIN lbp_clients dest ON c.destinataire_id = dest.id
            LEFT JOIN company_sites s_dep ON c.agence_depart_id = s_dep.id
            LEFT JOIN company_sites s_arr ON c.agence_arrivee_id = s_arr.id
            LEFT JOIN logistique_rayons r ON c.rayon_id = r.id
            WHERE c.numero_tracking = :ref OR c.id = :id_ref
            LIMIT 1
        ");
        $stmt->execute(['ref' => $reference, 'id_ref' => is_numeric($reference) ? (int) $reference : 0]);
        $colis = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($colis) {
            $progressMap = [
                'RÉCEPTIONNÉ' => 20,
                'EN_PRÉPARATION' => 40,
                'EN_TRANSIT' => 70,
                'ARRIVÉ' => 90,
                'RETIRÉ' => 100,
                'LIVRÉ' => 100,
            ];
            $progress = $progressMap[strtoupper($colis['statut'])] ?? 30;

            // Fetch steps from lbp_tracking_gps & logistique_mouvements_rayon
            $steps = [];
            $steps[] = [
                'date' => date('d/m/Y H:i', strtotime($colis['created_at'])),
                'title' => 'Colis enregistré',
                'detail' => 'Réceptionné à l\'agence ' . ($colis['agence_depart_name'] ?? 'de départ'),
            ];

            // Rayon assignment / movement steps
            $mvtStmt = $pdo->prepare("
                SELECT m.*, r.code_rayon
                FROM logistique_mouvements_rayon m
                LEFT JOIN logistique_rayons r ON m.rayon_id = r.id
                WHERE m.colis_id = :colis_id
                ORDER BY m.created_at ASC
            ");
            $mvtStmt->execute(['colis_id' => $colis['id']]);
            $mouvements = $mvtStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            foreach ($mouvements as $mvt) {
                $typeLabel = $mvt['type_mouvement'] === 'ENTREE' ? 'Affectation Rayon ' . ($mvt['code_rayon'] ?? '') : ($mvt['type_mouvement'] === 'SORTIE' ? 'Sortie de rayon' : 'Déplacement rayon');
                $steps[] = [
                    'date' => date('d/m/Y H:i', strtotime($mvt['created_at'])),
                    'title' => $typeLabel,
                    'detail' => $mvt['commentaires'] ?? ('Action ' . $mvt['type_mouvement']),
                ];
            }

            // GPS steps if expedition associated
            if (!empty($colis['expedition_id'])) {
                $gpsStmt = $pdo->prepare("
                    SELECT * FROM lbp_tracking_gps
                    WHERE expedition_id = :exp_id OR colis_id = :colis_id
                    ORDER BY date_etape ASC
                ");
                $gpsStmt->execute(['exp_id' => $colis['expedition_id'], 'colis_id' => $colis['id']]);
                $gpsSteps = $gpsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                foreach ($gpsSteps as $g) {
                    $steps[] = [
                        'date' => date('d/m/Y H:i', strtotime($g['date_etape'])),
                        'title' => 'Étape logistique',
                        'detail' => $g['etape'] . ($g['latitude'] ? " (GPS: {$g['latitude']}, {$g['longitude']})" : ''),
                    ];
                }
            }

            if (!empty($colis['recup_date_heure'])) {
                $steps[] = [
                    'date' => date('d/m/Y H:i', strtotime($colis['recup_date_heure'])),
                    'title' => 'Retrait effectué',
                    'detail' => 'Retiré au comptoir par ' . ($colis['recup_nom'] ?? 'le client'),
                ];
            }

            $lastLocation = $colis['agence_arrivee_name'] ?? $colis['agence_depart_name'] ?? 'Hub LBP';
            if (!empty($colis['code_rayon'])) {
                $lastLocation .= ' (Rayon ' . $colis['code_rayon'] . ')';
            }

            $destName = $colis['destinataire_name'] ?? 'Client Destinataire';
            $maskedClient = mb_substr($destName, 0, 3) . '*** ' . mb_substr($destName, -2);

            return [
                'reference' => $colis['numero_tracking'],
                'client' => $maskedClient,
                'origin' => $colis['agence_depart_name'] ?? 'Agence de départ',
                'destination' => $colis['agence_arrivee_name'] ?? 'Agence d\'arrivée',
                'status' => $colis['statut'],
                'progress' => $progress,
                'eta' => !empty($colis['date_limite_retrait']) ? date('d/m/Y', strtotime($colis['date_limite_retrait'])) : 'Non spécifiée',
                'lastLocation' => $lastLocation,
                'steps' => $steps,
            ];
        }

        // 2. Search in lbp_expeditions
        $expStmt = $pdo->prepare("
            SELECT e.*, s_dep.name AS agence_depart_name, s_arr.name AS agence_arrivee_name
            FROM lbp_expeditions e
            JOIN company_sites s_dep ON e.agence_depart_id = s_dep.id
            JOIN company_sites s_arr ON e.agence_arrivee_id = s_arr.id
            WHERE e.reference = :ref
            LIMIT 1
        ");
        $expStmt->execute(['ref' => $reference]);
        $exp = $expStmt->fetch(\PDO::FETCH_ASSOC);

        if ($exp) {
            $progressMap = [
                'BROUILLON' => 15,
                'EN_PRÉPARATION' => 35,
                'EN_TRANSIT' => 70,
                'ARRIVÉ' => 100,
            ];
            $progress = $progressMap[strtoupper($exp['statut'])] ?? 50;

            $gpsStmt = $pdo->prepare("SELECT * FROM lbp_tracking_gps WHERE expedition_id = :exp_id ORDER BY date_etape ASC");
            $gpsStmt->execute(['exp_id' => $exp['id']]);
            $gpsSteps = $gpsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $steps = [
                [
                    'date' => date('d/m/Y H:i', strtotime($exp['created_at'])),
                    'title' => 'Expédition créée',
                    'detail' => 'Transport ' . $exp['type_transport'] . ' de ' . $exp['agence_depart_name'] . ' à ' . $exp['agence_arrivee_name'],
                ]
            ];
            foreach ($gpsSteps as $g) {
                $steps[] = [
                    'date' => date('d/m/Y H:i', strtotime($g['date_etape'])),
                    'title' => 'Étape de transit',
                    'detail' => $g['etape'],
                ];
            }

            return [
                'reference' => $exp['reference'],
                'client' => 'Groupage ' . $exp['type_transport'],
                'origin' => $exp['agence_depart_name'],
                'destination' => $exp['agence_arrivee_name'],
                'status' => $exp['statut'],
                'progress' => $progress,
                'eta' => !empty($exp['date_arrivee_estimee']) ? date('d/m/Y', strtotime($exp['date_arrivee_estimee'])) : 'En cours',
                'lastLocation' => $exp['statut'] === 'ARRIVÉ' ? $exp['agence_arrivee_name'] : 'En transit international',
                'steps' => $steps,
            ];
        }

        return null;
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
