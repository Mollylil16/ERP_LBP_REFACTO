<?php
declare(strict_types=1);
namespace App\View\Components;
use App\Helpers\View;
use App\View\Pages\SiteAdmin\AnalyticsPage;
final class SiteAnalytics
{
    public static function page(AnalyticsPage $page): string
    {
        $d=$page->data;$s=$d['summary']??[];
        $html=Ui::pageHeader('Audience du site','Pages vues, clics et contexte technique des visites.',['eyebrow'=>'Analytics propriétaire']);
        $html.=Dashboard::kpis([
            ['label'=>'Visiteurs uniques','value'=>$s['visitors']??0,'meta'=>'30 derniers jours'],
            ['label'=>'Pages vues','value'=>$s['views']??0,'meta'=>'30 derniers jours'],
            ['label'=>'Clics suivis','value'=>$s['clicks']??0,'meta'=>'Boutons et liens'],
            ['label'=>'Événements','value'=>$s['events']??0,'meta'=>'Total collecté'],
        ]);
        $daily=array_map(static fn(array $row):array=>['label'=>$row['label'],'total'=>(int)$row['views']+(int)$row['clicks']],$d['daily']??[]);
        $html.='<div class="site-analytics-grid">'.self::bars('Activité des 14 derniers jours',$daily).self::bars('Pages les plus vues',$d['pages']??[]).self::bars('Boutons les plus cliqués',$d['clicks']??[]).'</div>';
        $html.='<section class="finea-section-card"><h2 class="finea-section-title">Activité récente</h2><div class="site-analytics-table"><table><thead><tr><th>Date</th><th>Événement</th><th>Page / bouton</th><th>IP</th><th>Langue / fuseau</th><th>Navigateur</th></tr></thead><tbody>';
        foreach($d['visitors']??[] as $row){$html.='<tr><td>'.View::e((string)$row['created_at']).'</td><td>'.View::e((string)$row['event_type']).'</td><td>'.View::e((string)($row['target_label']?:$row['page_path'])).'</td><td>'.View::e((string)$row['ip_address']).'</td><td>'.View::e((string)$row['language'].' / '.(string)$row['timezone']).'</td><td>'.View::e(substr((string)$row['user_agent'],0,80)).'</td></tr>';}
        return $html.'</tbody></table></div><p class="health-muted">La position GPS n’est enregistrée que si le visiteur l’autorise dans son navigateur.</p></section>';
    }
    private static function bars(string $title,array $rows): string{$totals=array_map(static fn(array $row):int=>(int)($row['total']??0),$rows);$max=max([1,...$totals]);$h='<section class="finea-section-card"><h2 class="finea-section-title">'.View::e($title).'</h2><div class="site-analytics-bars">';foreach($rows as $r){$h.='<div><span>'.View::e((string)$r['label']).'</span><i><b style="width:'.(((int)$r['total']/$max)*100).'%"></b></i><strong>'.(int)$r['total'].'</strong></div>';}return $h.'</div></section>';}
}
