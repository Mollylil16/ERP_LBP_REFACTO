<?php
declare(strict_types=1);
namespace App\Controllers\Site;
use App\Helpers\Response;
use App\Helpers\Session;
use App\Models\Database;
use App\Repositories\Site\WebsiteAnalyticsRepository;
final class WebsiteAnalyticsController
{
    public function record(): void
    {
        $payload=json_decode((string) file_get_contents('php://input'),true);
        if(!is_array($payload)) $payload=$_POST;
        $visitor=preg_replace('/[^a-zA-Z0-9_-]/','',(string)($payload['visitor_id']??''));
        if($visitor==='') Response::json(['ok'=>false],422);
        (new WebsiteAnalyticsRepository(Database::getConnection()))->record([
            'visitor_id'=>substr($visitor,0,80),'customer_id'=>(int)Session::get('site_customer_id',0)?:null,
            'event_type'=>($payload['event_type']??'')==='click'?'click':'page_view',
            'page_path'=>substr((string)($payload['page_path']??'/site'),0,255),
            'target_key'=>substr((string)($payload['target_key']??''),0,180)?:null,
            'target_label'=>substr((string)($payload['target_label']??''),0,255)?:null,
            'referrer'=>substr((string)($payload['referrer']??''),0,500)?:null,
            'ip_address'=>substr((string)($_SERVER['REMOTE_ADDR']??''),0,80)?:null,
            'user_agent'=>substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500)?:null,
            'language'=>substr((string)($payload['language']??''),0,50)?:null,
            'timezone'=>substr((string)($payload['timezone']??''),0,100)?:null,
            'screen_size'=>substr((string)($payload['screen_size']??''),0,40)?:null,
            'latitude'=>is_numeric($payload['latitude']??null)?$payload['latitude']:null,
            'longitude'=>is_numeric($payload['longitude']??null)?$payload['longitude']:null,
        ]);
        Response::json(['ok'=>true]);
    }
}
