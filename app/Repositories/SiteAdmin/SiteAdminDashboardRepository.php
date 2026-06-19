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
            ['label' => 'Leads reçus', 'value' => $count('website_leads'), 'meta' => 'Contacts et demandes de devis', 'href' => '/site-admin/dashboard'],
        ];
        $module['actions'] = [
            ['label' => 'Design & contenus', 'hint' => 'Branding, couleurs, carrousel et boutique', 'url' => '/site-admin/configuration'],
            ['label' => 'Prévisualiser le site', 'hint' => 'Contrôler immédiatement le rendu public', 'url' => '/site'],
            ['label' => 'Voir la marketplace', 'hint' => 'Tester le catalogue et le panier', 'url' => '/site/shop'],
        ];
        return $module;
    }
}
