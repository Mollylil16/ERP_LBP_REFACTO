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
        $summary=$this->pdo->query("SELECT COUNT(*) events, SUM(event_type='page_view') views, SUM(event_type='click') clicks, COUNT(DISTINCT visitor_id) visitors FROM website_analytics_events WHERE created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)")->fetch()?:[];
        return [
            'summary'=>$summary,
            'daily'=>$this->pdo->query("SELECT DATE(created_at) label,SUM(event_type='page_view') views,SUM(event_type='click') clicks FROM website_analytics_events WHERE created_at>=DATE_SUB(NOW(),INTERVAL 14 DAY) GROUP BY DATE(created_at) ORDER BY label")->fetchAll()?:[],
            'pages'=>$this->pdo->query("SELECT page_path label,COUNT(*) total FROM website_analytics_events WHERE event_type='page_view' GROUP BY page_path ORDER BY total DESC LIMIT 10")->fetchAll()?:[],
            'clicks'=>$this->pdo->query("SELECT COALESCE(NULLIF(target_label,''),target_key,'Élément') label,COUNT(*) total FROM website_analytics_events WHERE event_type='click' GROUP BY label ORDER BY total DESC LIMIT 10")->fetchAll()?:[],
            'visitors'=>$this->pdo->query("SELECT created_at,page_path,event_type,target_label,ip_address,user_agent,language,timezone,screen_size,latitude,longitude FROM website_analytics_events ORDER BY id DESC LIMIT 100")->fetchAll()?:[],
        ];
    }
}
