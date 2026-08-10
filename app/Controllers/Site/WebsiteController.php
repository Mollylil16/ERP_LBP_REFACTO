<?php

namespace App\Controllers\Site;

use App\Controllers\BaseController;

use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\Shared\BusinessModuleRepository;
use App\Repositories\Site\WebsiteRepository;
use App\Services\Shared\BusinessModuleService;
use App\Services\Site\WebsiteService;
use App\View\Pages\Site\SitePage;
use App\View\Pages\SiteAdmin\DashboardPage;
use App\View\Navigation\SiteAdminNavigation;

final class WebsiteController extends BaseController
{
    private WebsiteService $website;

    public function __construct()
    {
        $this->website = new WebsiteService(new WebsiteRepository(Database::getConnection()));
    }

    public function dashboard(): void
    {
        AuthMiddleware::check();
        $module = (new BusinessModuleService(new BusinessModuleRepository(Database::getConnection())))->websiteDashboard();
        $this->view('site_admin/dashboard', [
            'pageTitle' => 'Pilotage site internet',
            'moduleName' => 'Site internet',
            'moduleCode' => 'WEB',
            'moduleTheme' => $module,
            'activeModule' => 'dashboard',
            'moduleNavigation' => SiteAdminNavigation::items(),
            'page' => new DashboardPage($module),
            'additionalStyles' => ['css/finea-ui.css'],
        ]);
    }

    public function publicSite(): void
    {
        $this->siteView('site/index', 'Accueil', 'home');
    }

    public function tracking(): void
    {
        $this->siteView('site/tracking', 'Suivi colis', 'tracking', (string) ($_GET['ref'] ?? ''));
    }

    public function quote(): void
    {
        $this->siteView('site/devis', 'Demande de devis', 'quote');
    }

    public function submitQuote(): void
    {
        $name = trim((string)($_POST['customer_name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $origin = trim((string)($_POST['origin_country'] ?? ''));
        $dest = trim((string)($_POST['destination_country'] ?? ''));
        $mode = trim((string)($_POST['transport_mode'] ?? ''));
        $goods = trim((string)($_POST['goods_description'] ?? ''));

        if ($name !== '' && ($phone !== '' || $email !== '')) {
            $subject = "Demande de Devis (" . strtoupper($mode ?: 'Transit') . ") : {$origin} -> {$dest}";
            $message = "Origine: {$origin}\nDestination: {$dest}\nMode: {$mode}\nMarchandises: {$goods}";

            $this->repository->saveLead([
                'source' => 'devis_form',
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'subject' => $subject,
                'message' => $message,
            ]);
            \App\Helpers\Session::setFlash('success', 'Votre demande de devis a bien été enregistrée ! Un conseiller LBP vous recontactera sous 24h.');
        } else {
            \App\Helpers\Session::setFlash('error', 'Veuillez remplir votre nom et au moins un moyen de contact (Téléphone ou Email).');
        }

        $this->redirect('site/devis');
    }

    public function contact(): void
    {
        $this->siteView('site/contact', 'Contact', 'contact');
    }

    public function agencies(): void
    {
        $this->siteView('site/agences', 'Nos agences', 'agencies');
    }

    public function shop(): void
    {
        $this->siteView('site/shop', 'Marketplace', 'shop');
    }

    public function forum(): void
    {
        $this->siteView('site/forum', 'Communauté', 'forum');
    }

    public function createForumTopic(): void
    {
        $title = trim((string)($_POST['title'] ?? ''));
        $category = trim((string)($_POST['category'] ?? 'Général'));
        $content = trim((string)($_POST['content'] ?? ''));
        $author = trim((string)($_POST['author_name'] ?? 'Membre LBP'));

        if ($title !== '' && $content !== '') {
            $slug = strtolower((string)preg_replace('/[^a-z0-9]+/i', '-', $title));
            $this->repository->saveForumTopic([
                'category' => $category,
                'title' => $title,
                'slug' => $slug,
                'excerpt' => mb_substr($content, 0, 120) . '...',
                'content' => $content,
                'author_name' => $author,
            ]);
            \App\Helpers\Session::setFlash('success', 'Votre sujet a été publié sur la communauté LBP !');
        } else {
            \App\Helpers\Session::setFlash('error', 'Veuillez remplir le titre et le contenu de votre message.');
        }

        $this->redirect('site/forum');
    }

    public function blog(): void
    {
        $this->siteView('site/blog', 'Actualités', 'blog');
    }

    public function article(string $slug): void
    {
        $article = $this->website->article($slug);
        if ($article === null) {
            (new \App\Controllers\Error\ErrorController())->notFound('/site/blog/' . $slug);
            return;
        }
        $content = $this->website->content();
        $page = new SitePage('Article', 'blog', $this->demoShipments(), $this->demoAgencies(), $content['services'], $this->demoNews(), [], $content['branding'], $content['slides'], $content['products'], $content['topics'], $content['announcements'], $content['articles']);
        $this->view('site/article', ['pageTitle' => (string) $article['title'], 'page' => $page, 'article' => $article]);
    }

    private function siteView(string $view, string $title, string $activePage, string $reference = ''): void
    {
        $content = $this->website->content();
        $shipments = $this->realShipments();

        if ($reference !== '') {
            $realData = $this->website->getRealTrackingData($reference);
            if ($realData !== null) {
                $shipments[$realData['reference']] = $realData;
            }
        }

        $this->view($view, [
            'pageTitle' => $title,
            'page' => new SitePage(
                $title,
                $activePage,
                $shipments,
                $this->demoAgencies(),
                $content['services'] !== [] ? $content['services'] : $this->demoServices(),
                $this->demoNews(),
                [
                ['label' => 'Pays couverts', 'value' => '14+'],
                ['label' => 'Dossiers suivis', 'value' => '2 480'],
                ['label' => 'Agences & points relais', 'value' => '9'],
                ['label' => 'SLA suivi client', 'value' => '24/7'],
                ],
                $content['branding'],
                $content['slides'],
                $content['products'],
                $content['topics'],
                $content['announcements'],
                $content['articles'],
                $reference,
            ),
        ]);
    }

    private function realShipments(): array
    {
        try {
            $pdo = \App\Models\Database::getConnection();
            $colisList = $pdo->query("SELECT numero_tracking FROM lbp_colis ORDER BY id DESC LIMIT 5")->fetchAll(\PDO::FETCH_COLUMN);
            $shipments = [];
            foreach ($colisList as $ref) {
                if (!empty($ref)) {
                    $data = $this->website->getRealTrackingData((string)$ref);
                    if ($data !== null) {
                        $shipments[$data['reference']] = $data;
                    }
                }
            }
            if (!empty($shipments)) {
                return $shipments;
            }

            $expList = $pdo->query("SELECT reference FROM lbp_expeditions ORDER BY id DESC LIMIT 5")->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($expList as $ref) {
                if (!empty($ref)) {
                    $data = $this->website->getRealTrackingData((string)$ref);
                    if ($data !== null) {
                        $shipments[$data['reference']] = $data;
                    }
                }
            }
            if (!empty($shipments)) {
                return $shipments;
            }
        } catch (\Throwable $e) {
            // fallback
        }

        return [];
    }

    private function demoAgencies(): array
    {
        try {
            $pdo = \App\Models\Database::getConnection();
            $sites = $pdo->query("SELECT * FROM company_sites WHERE is_active = 1 ORDER BY id ASC")->fetchAll(\PDO::FETCH_ASSOC);
            if (!empty($sites)) {
                $coordsMap = [
                    'abidjan' => [5.359951, -4.008256],
                    'paris' => [48.856614, 2.352221],
                    'dakar' => [14.716677, -17.467686],
                    'guangzhou' => [23.1291, 113.2644],
                    'san pedro' => [4.7485, -6.6363],
                    'yamoussoukro' => [6.8276, -5.2893],
                    'dubai' => [25.2048, 55.2708],
                    'lome' => [6.1375, 1.2123],
                    'cotonou' => [6.3703, 2.3912],
                ];

                return array_map(static function(array $site) use ($coordsMap): array {
                    $nameLower = mb_strtolower((string)$site['name'], 'UTF-8');
                    $cityLower = mb_strtolower((string)($site['city'] ?? ''), 'UTF-8');

                    $lat = 5.359951; $lng = -4.008256;
                    foreach ($coordsMap as $k => $c) {
                        if (str_contains($nameLower, $k) || str_contains($cityLower, $k)) {
                            $lat = $c[0]; $lng = $c[1]; break;
                        }
                    }

                    return [
                        'code' => $site['code'] ?? ('AG-' . $site['id']),
                        'name' => $site['name'],
                        'city' => $site['city'] ?? 'Abidjan',
                        'country' => $site['country'] ?? 'Côte d\'Ivoire',
                        'address' => $site['address'] ?? 'Agence LBP Logistics',
                        'phone' => $site['phone'] ?? '+225 07 00 00 00 00',
                        'email' => $site['email'] ?? 'contact@lbp-logistics.com',
                        'hours' => 'Lun–Ven 08:00–18:00',
                        'lat' => $lat,
                        'lng' => $lng,
                        'services' => 'Transit, Fret Aérien & Maritime, Douane',
                    ];
                }, $sites);
            }
        } catch (\Throwable $e) {
            // fallback
        }

        return [
            ['code' => 'ABJ-HQ', 'name' => 'LBP Siège Abidjan', 'city' => 'Abidjan', 'country' => 'Côte d’Ivoire', 'address' => 'Plateau, Avenue de la République', 'phone' => '+225 07 00 00 00 01', 'email' => 'contact@lbp-logistics.com', 'hours' => 'Lun–Ven 08:00–18:00', 'lat' => 5.3204, 'lng' => -4.0161, 'services' => 'Transit, devis, suivi client'],
            ['code' => 'FRA', 'name' => 'LBP Paris - Bobigny', 'city' => 'Bobigny', 'country' => 'France', 'address' => '17 chemin des Vignes, 93000 Bobigny', 'phone' => '+33 1 48 00 00 00', 'email' => 'paris@lbp-logistics.com', 'hours' => 'Lun–Ven 09:00–18:00', 'lat' => 48.9086, 'lng' => 2.4397, 'services' => 'Colis, fret aérien, réception'],
            ['code' => 'SEN', 'name' => 'LBP Agence Sénégal', 'city' => 'Dakar', 'country' => 'Sénégal', 'address' => 'Avenue Lamine Guèye, Dakar', 'phone' => '+221 33 800 00 00', 'email' => 'dakar@lbp-logistics.com', 'hours' => 'Lun–Ven 08:00–17:30', 'lat' => 14.6937, 'lng' => -17.4441, 'services' => 'Transit régional, colis, fret'],
            ['code' => 'SPY', 'name' => 'LBP Agence San Pedro', 'city' => 'San Pedro', 'country' => 'Côte d’Ivoire', 'address' => 'Zone portuaire, San Pedro', 'phone' => '+225 07 00 00 00 03', 'email' => 'sanpedro@lbp-logistics.com', 'hours' => 'Lun–Ven 08:00–17:00', 'lat' => 4.7485, 'lng' => -6.6363, 'services' => 'Export, fret maritime, logistique'],
        ];
    }

    private function demoServices(): array
    {
        return [
            ['title' => 'Dédouanement & Douane', 'text' => 'Formalités import-export, liquidation et suivi des mainlevées douanières.', 'icon' => 'customs'],
            ['title' => 'Fret International Aérien & Maritime', 'text' => 'Organisation de transport aérien express et conteneurs maritimes FCL/LCL.', 'icon' => 'freight'],
            ['title' => 'Suivi GPS en Temps Réel', 'text' => 'Tracking digital par code-barres et jalons GPS connectés à l’ERP.', 'icon' => 'tracking'],
            ['title' => 'Enlèvement & Livraison Locale', 'text' => 'Prise en charge à domicile, stockage en rayon et remise finale.', 'icon' => 'delivery'],
        ];
    }

    private function demoNews(): array
    {
        return [
            ['title' => 'Liaison Fret Aérien Paris ➔ Abidjan : Départs Quotidiens', 'date' => 'Juin 2026'],
            ['title' => 'Suivi GPS Colis en temps réel disponible 24/7', 'date' => 'Juin 2026'],
            ['title' => 'Optimisation du Dédouanement au Port Autonome d’Abidjan', 'date' => 'Mai 2026'],
        ];
    }
}
