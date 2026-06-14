<?php

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Models\Database;
use App\Repositories\BusinessModuleRepository;
use App\Services\BusinessModuleService;

final class WebsiteController extends BaseController
{
    public function dashboard(): void
    {
        AuthMiddleware::check();
        $module = (new BusinessModuleService(new BusinessModuleRepository(Database::getConnection())))->websiteDashboard();
        $this->view('modules/dashboard', [
            'pageTitle' => 'Pilotage site internet',
            'moduleName' => 'Site internet',
            'moduleCode' => 'WEB',
            'moduleTheme' => $module,
            'activeModule' => 'dashboard',
            'moduleNavigation' => $module['navigation'],
            'dashboardModule' => $module,
            'additionalStyles' => ['css/finea-ui.css'],
        ]);
    }

    public function publicSite(): void
    {
        $this->view('site/index', $this->sitePayload('Accueil'));
    }

    public function tracking(): void
    {
        $this->view('site/tracking', $this->sitePayload('Suivi colis'));
    }

    public function quote(): void
    {
        $this->view('site/devis', $this->sitePayload('Demande de devis'));
    }

    public function contact(): void
    {
        $this->view('site/contact', $this->sitePayload('Contact'));
    }

    public function agencies(): void
    {
        $this->view('site/agences', $this->sitePayload('Nos agences'));
    }

    private function sitePayload(string $page): array
    {
        return [
            'pageTitle' => $page . ' - LBP Transit',
            'shipments' => $this->demoShipments(),
            'agencies' => $this->demoAgencies(),
            'services' => $this->demoServices(),
            'news' => $this->demoNews(),
            'stats' => [
                ['label' => 'Pays couverts', 'value' => '14+'],
                ['label' => 'Dossiers suivis', 'value' => '2 480'],
                ['label' => 'Agences & points relais', 'value' => '9'],
                ['label' => 'SLA suivi client', 'value' => '24/7'],
            ],
        ];
    }

    private function demoShipments(): array
    {
        return [
            'LBP-EXP-2026-00124' => [
                'reference' => 'LBP-EXP-2026-00124',
                'client' => 'Société KAB Transit',
                'origin' => 'Guangzhou, Chine',
                'destination' => 'Abidjan, Côte d’Ivoire',
                'status' => 'Dédouanement en cours',
                'progress' => 68,
                'eta' => '21/06/2026',
                'lastLocation' => 'Port Autonome d’Abidjan',
                'steps' => [
                    ['date' => '05/06/2026', 'title' => 'Dossier créé', 'detail' => 'Documents commerciaux reçus et contrôlés par le service transit.'],
                    ['date' => '09/06/2026', 'title' => 'Marchandise embarquée', 'detail' => 'Conteneur confirmé au départ de Guangzhou.'],
                    ['date' => '13/06/2026', 'title' => 'Arrivée portuaire', 'detail' => 'Déclaration douanière en cours de traitement.'],
                    ['date' => 'Prévu', 'title' => 'Livraison finale', 'detail' => 'Livraison planifiée après mainlevée.'],
                ],
            ],
            'LBP-COL-2026-00087' => [
                'reference' => 'LBP-COL-2026-00087',
                'client' => 'Client Particulier',
                'origin' => 'Paris, France',
                'destination' => 'Yamoussoukro, Côte d’Ivoire',
                'status' => 'En transit international',
                'progress' => 44,
                'eta' => '24/06/2026',
                'lastLocation' => 'Hub aérien Europe',
                'steps' => [
                    ['date' => '10/06/2026', 'title' => 'Colis enregistré', 'detail' => 'Référence de suivi générée.'],
                    ['date' => '12/06/2026', 'title' => 'Pris en charge', 'detail' => 'Tri et consolidation export réalisés.'],
                    ['date' => 'En cours', 'title' => 'Transit aérien', 'detail' => 'Acheminement vers Abidjan.'],
                ],
            ],
            'BL-LBP-778245-CI' => [
                'reference' => 'BL-LBP-778245-CI',
                'client' => 'AFRICA MEDICAL SUPPLY',
                'origin' => 'Dubaï, EAU',
                'destination' => 'Cotonou, Bénin',
                'status' => 'Livré',
                'progress' => 100,
                'eta' => '12/06/2026',
                'lastLocation' => 'Cotonou - Client final',
                'steps' => [
                    ['date' => '01/06/2026', 'title' => 'Départ fournisseur', 'detail' => 'Lot sécurisé et documenté.'],
                    ['date' => '08/06/2026', 'title' => 'Dédouané', 'detail' => 'Mainlevée obtenue.'],
                    ['date' => '12/06/2026', 'title' => 'Livré', 'detail' => 'Remis au destinataire final.'],
                ],
            ],
        ];
    }

    private function demoAgencies(): array
    {
        return [
            ['code' => 'ABJ-SIEGE', 'name' => 'LBP Siège Abidjan', 'city' => 'Abidjan', 'country' => 'Côte d’Ivoire', 'address' => 'Plateau, Avenue de la République', 'phone' => '+225 07 00 00 00 01', 'email' => 'abidjan@lbp-transit.test', 'hours' => 'Lun–Ven 08:00–18:00', 'lat' => 5.3204, 'lng' => -4.0161, 'services' => 'Transit, devis, suivi client'],
            ['code' => 'ABJ-PORT', 'name' => 'LBP Port Autonome', 'city' => 'Abidjan', 'country' => 'Côte d’Ivoire', 'address' => 'Zone portuaire, Treichville', 'phone' => '+225 07 00 00 00 02', 'email' => 'port.abidjan@lbp-transit.test', 'hours' => 'Lun–Sam 07:30–17:30', 'lat' => 5.2929, 'lng' => -4.0083, 'services' => 'Dédouanement, conteneurs'],
            ['code' => 'SAN-PEDRO', 'name' => 'LBP San Pedro', 'city' => 'San Pedro', 'country' => 'Côte d’Ivoire', 'address' => 'Quartier portuaire, San Pedro', 'phone' => '+225 07 00 00 00 03', 'email' => 'sanpedro@lbp-transit.test', 'hours' => 'Lun–Ven 08:00–17:00', 'lat' => 4.7485, 'lng' => -6.6363, 'services' => 'Export, port, logistique'],
            ['code' => 'YAMO', 'name' => 'LBP Yamoussoukro', 'city' => 'Yamoussoukro', 'country' => 'Côte d’Ivoire', 'address' => 'Quartier administratif', 'phone' => '+225 07 00 00 00 04', 'email' => 'yamoussoukro@lbp-transit.test', 'hours' => 'Lun–Ven 08:00–17:00', 'lat' => 6.8276, 'lng' => -5.2893, 'services' => 'Distribution intérieure'],
            ['code' => 'LOME', 'name' => 'LBP Lomé', 'city' => 'Lomé', 'country' => 'Togo', 'address' => 'Avenue du Port', 'phone' => '+228 90 00 00 01', 'email' => 'lome@lbp-transit.test', 'hours' => 'Lun–Ven 08:00–17:30', 'lat' => 6.1375, 'lng' => 1.2123, 'services' => 'Transit régional, corridor'],
            ['code' => 'COTONOU', 'name' => 'LBP Cotonou', 'city' => 'Cotonou', 'country' => 'Bénin', 'address' => 'Akpakpa, route portuaire', 'phone' => '+229 01 00 00 00 01', 'email' => 'cotonou@lbp-transit.test', 'hours' => 'Lun–Ven 08:00–17:30', 'lat' => 6.3703, 'lng' => 2.3912, 'services' => 'Import, last mile'],
            ['code' => 'DUBAI', 'name' => 'LBP Dubai Desk', 'city' => 'Dubaï', 'country' => 'Émirats Arabes Unis', 'address' => 'Deira Logistics Center', 'phone' => '+971 50 000 0001', 'email' => 'dubai@lbp-transit.test', 'hours' => 'Lun–Ven 09:00–18:00', 'lat' => 25.2048, 'lng' => 55.2708, 'services' => 'Sourcing, consolidation'],
            ['code' => 'GUANGZHOU', 'name' => 'LBP Guangzhou Desk', 'city' => 'Guangzhou', 'country' => 'Chine', 'address' => 'Baiyun logistics area', 'phone' => '+86 20 0000 0001', 'email' => 'guangzhou@lbp-transit.test', 'hours' => 'Lun–Sam 09:00–18:00', 'lat' => 23.1291, 'lng' => 113.2644, 'services' => 'Achat, groupage, export'],
            ['code' => 'PARIS', 'name' => 'LBP Paris Relay', 'city' => 'Paris', 'country' => 'France', 'address' => 'Île-de-France, zone fret', 'phone' => '+33 1 00 00 00 01', 'email' => 'paris@lbp-transit.test', 'hours' => 'Lun–Ven 09:00–18:00', 'lat' => 48.8566, 'lng' => 2.3522, 'services' => 'Colis, documents, fret aérien'],
        ];
    }

    private function demoServices(): array
    {
        return [
            ['title' => 'Dédouanement', 'text' => 'Formalités import-export, classification, liquidation et suivi mainlevée.', 'icon' => 'customs'],
            ['title' => 'Fret international', 'text' => 'Organisation maritime, aérienne et routière avec consolidation multi-pays.', 'icon' => 'freight'],
            ['title' => 'Tracking digital', 'text' => 'Références colis, notifications client et jalons opérationnels connectés ERP.', 'icon' => 'tracking'],
            ['title' => 'Livraison locale', 'text' => 'Planification enlèvement, entreposage, dispatch et preuve de livraison.', 'icon' => 'delivery'],
        ];
    }

    private function demoNews(): array
    {
        return [
            ['title' => 'Nouvelle liaison Guangzhou vers Abidjan', 'date' => 'Juin 2026'],
            ['title' => 'Suivi colis disponible 24/7', 'date' => 'Juin 2026'],
            ['title' => 'Extension du réseau LBP à Lomé et Cotonou', 'date' => 'Mai 2026'],
        ];
    }
}
