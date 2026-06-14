<?php

namespace App\Services;

use App\Repositories\BusinessModuleRepository;

final class BusinessModuleService
{
    public function __construct(private BusinessModuleRepository $repository) {}

    public function crmDashboard(): array
    {
        return $this->dashboard('crm', 'CRM', 'CRM', 'crm', '#ec4899', '#9d174d', 'linear-gradient(135deg,#9d174d,#ec4899)', 'Clients, prospects, relances, opportunités commerciales et suivi relationnel import-export.', $this->repository->crmStats(), [
            ['label' => 'Nouveau client', 'hint' => 'Créer une fiche client/prospect', 'url' => '/crm/clients/nouveau'],
            ['label' => 'Pipeline', 'hint' => 'Suivre les opportunités et relances', 'url' => '/crm/dashboard'],
            ['label' => 'Interactions', 'hint' => 'Historique appels, mails et visites', 'url' => '/crm/dashboard'],
        ]);
    }

    public function ticketDashboard(): array
    {
        return $this->dashboard('tickets', 'Tickets', 'TIC', 'tickets', '#ef4444', '#991b1b', 'linear-gradient(135deg,#991b1b,#ef4444)', 'Demandes interpersonnelles, interactions interservices, SLA, relances et traçabilité interne.', $this->repository->ticketStats(), [
            ['label' => 'Nouveau ticket', 'hint' => 'Créer une demande vers un service ou un collègue', 'url' => '/tickets/nouveau'],
            ['label' => 'Mes tickets', 'hint' => 'Suivre les demandes émises et reçues', 'url' => '/tickets/dashboard'],
            ['label' => 'SLA & relances', 'hint' => 'Contrôler les délais et escalades', 'url' => '/tickets/dashboard'],
        ]);
    }

    public function websiteDashboard(): array
    {
        return $this->dashboard('site-admin', 'Site internet', 'WEB', 'website', '#14b8a6', '#0f766e', 'linear-gradient(135deg,#0f766e,#14b8a6)', 'Pilotage du site vitrine transit, contenus, services, demandes clients et suivi colis public.', $this->repository->websiteStats(), [
            ['label' => 'Gérer les pages', 'hint' => 'Accueil, services, agences, FAQ', 'url' => '/site-admin/dashboard'],
            ['label' => 'Demandes web', 'hint' => 'Traiter devis, tracking et contacts', 'url' => '/site-admin/dashboard'],
            ['label' => 'Voir le site', 'hint' => 'Ouvrir la partie publique', 'url' => '/site'],
        ]);
    }

    private function dashboard(string $slug, string $label, string $code, string $icon, string $accent, string $accent2, string $gradient, string $description, array $stats, array $actions): array
    {
        return [
            'slug' => $slug, 'label' => $label, 'code' => $code, 'iconKey' => $icon, 'accent' => $accent, 'accent2' => $accent2, 'gradient' => $gradient, 'description' => $description,
            'kpis' => $stats, 'actions' => $actions,
            'navigation' => [
                ['key'=>'dashboard','label'=>'Tableau de bord','icon'=>'DB','url'=>'/'.$slug.'/dashboard','available'=>true],
                ['key'=>'operations','label'=>'Opérations','icon'=>'OP','url'=>'/'.$slug.'/dashboard','available'=>true],
                ['key'=>'documents','label'=>'Documents','icon'=>'DOC','url'=>'/'.$slug.'/dashboard','available'=>true],
                ['key'=>'reporting','label'=>'Reporting','icon'=>'RP','url'=>'/'.$slug.'/dashboard','available'=>true],
                ['key'=>'settings','label'=>'Paramétrage','icon'=>'PR','url'=>'/'.$slug.'/dashboard','available'=>true],
            ],
            'workflow' => [
                ['title'=>'Référentiel paramétrable','text'=>'Les statuts, priorités, catégories, sites et canaux sont isolés en tables dédiées.'],
                ['title'=>'Traçabilité métier','text'=>'Chaque interaction importante est historisée pour conserver la lecture opérationnelle.'],
                ['title'=>'Évolution clean code','text'=>'Le dashboard passe par service/repository et les vues restent sans SQL métier.'],
            ],
        ];
    }
}
