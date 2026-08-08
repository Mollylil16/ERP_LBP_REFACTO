<?php
declare(strict_types=1);
namespace App\Repositories\Site;
use PDO;
final class WebsiteAnalyticsRepository
{
    public function __construct(private PDO $pdo) {}
    public function record(array $event): void
    {
        $stmt=$this->pdo->prepare("INSERT INTO website_analytics_events (visitor_id,customer_id,event_type,page_path,target_key,target_label,referrer,ip_address,user_agent,language,timezone,screen_size,latitude,longitude) VALUES (:visitor_id,:customer_id,:event_type,:page_path,:target_key,:target_label,:referrer,:ip_address,:user_agent,:language,:timezone,:screen_size,:latitude,:longitude)");
        $stmt->execute($event);
    }
    public function dashboard(): array
    {
        $summary = $this->pdo->query("
            SELECT COUNT(*) events, 
                   SUM(event_type='page_view') views, 
                   SUM(event_type='click') clicks, 
                   COUNT(DISTINCT visitor_id) visitors,
                   (SELECT COUNT(DISTINCT visitor_id) FROM website_analytics_events WHERE created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)) AS online_count
            FROM website_analytics_events 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ")->fetch() ?: [];

        // Active visitors in the last 15 minutes
        $onlineVisitors = $this->pdo->query("
            SELECT visitor_id, ip_address, page_path, target_label, user_agent, created_at, language, timezone
            FROM website_analytics_events
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
            ORDER BY id DESC
            LIMIT 50
        ")->fetchAll() ?: [];

        // Recent parcel tracking lookups
        $trackingSearches = $this->pdo->query("
            SELECT e.created_at, e.target_label AS tracking_ref, e.ip_address, c.statut, c.reference, c.created_at AS colis_date
            FROM website_analytics_events e
            LEFT JOIN lbp_colis c ON (c.numero_tracking = e.target_label OR c.reference = e.target_label)
            WHERE e.target_key = 'tracking_search' OR e.page_path LIKE '%tracking%'
            ORDER BY e.id DESC
            LIMIT 25
        ")->fetchAll() ?: [];

        // Parcels picked up / delivered
        $deliveredParcels = $this->pdo->query("
            SELECT c.*, 
                   exp.name AS expediteur_name, 
                   dest.name AS destinataire_name,
                   s.name AS agence_name
            FROM lbp_colis c
            LEFT JOIN lbp_clients exp ON c.expediteur_id = exp.id
            LEFT JOIN lbp_clients dest ON c.destinataire_id = dest.id
            LEFT JOIN company_sites s ON c.agence_arrivee_id = s.id
            WHERE c.statut IN ('RETIRÉ', 'LIVRÉ', 'retire', 'livre')
            ORDER BY c.updated_at DESC
            LIMIT 20
        ")->fetchAll() ?: [];

        return [
            'summary' => $summary,
            'online_visitors' => $onlineVisitors,
            'tracking_searches' => $trackingSearches,
            'delivered_parcels' => $deliveredParcels,
            'daily' => $this->pdo->query("SELECT DATE(created_at) label,SUM(event_type='page_view') views,SUM(event_type='click') clicks FROM website_analytics_events WHERE created_at>=DATE_SUB(NOW(),INTERVAL 14 DAY) GROUP BY DATE(created_at) ORDER BY label")->fetchAll() ?: [],
            'pages' => $this->pdo->query("SELECT page_path label,COUNT(*) total FROM website_analytics_events WHERE event_type='page_view' GROUP BY page_path ORDER BY total DESC LIMIT 10")->fetchAll() ?: [],
            'clicks' => $this->pdo->query("SELECT COALESCE(NULLIF(target_label,''),target_key,'Élément') label,COUNT(*) total FROM website_analytics_events WHERE event_type='click' GROUP BY label ORDER BY total DESC LIMIT 10")->fetchAll() ?: [],
            'visitors' => $this->pdo->query("SELECT created_at,page_path,event_type,target_label,ip_address,user_agent,language,timezone,screen_size,latitude,longitude FROM website_analytics_events ORDER BY id DESC LIMIT 100")->fetchAll() ?: [],
        ];
    }
}
