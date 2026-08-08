<?php

declare(strict_types=1);

namespace App\Repositories\SiteAdmin;

final class SiteAdminDashboardRepository extends \App\Repositories\Shared\ModuleDashboardRepository
{
    /**
     * @return array<string,mixed>
     */
    public function dashboard(): array
    {
        $module = $this->dashboardFor('site-admin');
        
        $count = fn(string $table, string $where = '1=1'): string => (string) (
            $this->pdo->query("SELECT COUNT(*) FROM {$table} WHERE {$where}")->fetchColumn() ?: 0
        );

        $onlineCount = (string) ($this->pdo->query("
            SELECT COUNT(DISTINCT visitor_id) 
            FROM website_analytics_events 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ")->fetchColumn() ?: 0);

        $trackingSearchesToday = (string) ($this->pdo->query("
            SELECT COUNT(*) 
            FROM website_analytics_events 
            WHERE (target_key = 'tracking_search' OR page_path LIKE '%tracking%') 
              AND DATE(created_at) = CURDATE()
        ")->fetchColumn() ?: 0);

        $module['kpis'] = [
            ['label' => 'Visiteurs en direct', 'value' => $onlineCount, 'meta' => 'Connectés les 15 dernières min', 'href' => '/site-admin/analytics'],
            ['label' => 'Recherches tracking (Auj.)', 'value' => $trackingSearchesToday, 'meta' => 'Consultations colis aujourd’hui', 'href' => '/site-admin/analytics'],
            ['label' => 'Annonces & Soldes', 'value' => $count('website_announcements', 'is_active = 1'), 'meta' => 'Bannières promos actives', 'href' => '/site-admin/configuration#announcements'],
            ['label' => 'Conversations & Messages', 'value' => $count('website_conversations'), 'meta' => 'Demandes clients enregistrées', 'href' => '/site-admin/messages'],
        ];

        $module['actions'] = [
            ['label' => 'Audience & Visiteurs en direct', 'hint' => 'Suivre qui est connecté et qui recherche son colis', 'url' => '/site-admin/analytics'],
            ['label' => 'Publier une Annonce / Promo', 'hint' => 'Poster une solde de fret ou une alerte en bannière', 'url' => '/site-admin/configuration#announcements'],
            ['label' => 'Design & Carrousel', 'hint' => 'Modifier le branding, les images d’en-tête et les couleurs', 'url' => '/site-admin/configuration'],
            ['label' => 'Voir le site vitrine', 'hint' => 'Prévisualiser immédiatement le site public', 'url' => '/site'],
        ];

        $module['showWorkflow'] = false;
        return $module;
    }
}
