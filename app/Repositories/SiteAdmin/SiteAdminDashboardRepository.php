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
        $module['kpis'] = [
            ['label' => 'Slides actifs', 'value' => $count('website_slides', 'is_active = 1'), 'meta' => 'Carrousel de la page d’accueil', 'href' => '/site-admin/configuration#carousel'],
            ['label' => 'Offres publiées', 'value' => $count('website_products', 'is_active = 1'), 'meta' => 'Marketplace publique', 'href' => '/site-admin/configuration#marketplace'],
            ['label' => 'Discussions', 'value' => $count('website_forum_topics', 'is_published = 1'), 'meta' => 'Communauté import-export', 'href' => '/site/forum'],
            ['label' => 'Conversations', 'value' => $count('website_conversations'), 'meta' => 'Échanges avec les clients connectés', 'href' => '/site-admin/messages'],
        ];
        $module['actions'] = [
            ['label' => 'Design & contenus', 'hint' => 'Branding, couleurs, carrousel et boutique', 'url' => '/site-admin/configuration'],
            ['label' => 'Prévisualiser le site', 'hint' => 'Contrôler immédiatement le rendu public', 'url' => '/site'],
            ['label' => 'Voir la marketplace', 'hint' => 'Tester le catalogue et le panier', 'url' => '/site/shop'],
            ['label' => 'Répondre aux clients', 'hint' => 'Messages, images, vidéos et notes vocales', 'url' => '/site-admin/messages'],
        ];
        $module['showWorkflow'] = false;
        return $module;
    }
}
