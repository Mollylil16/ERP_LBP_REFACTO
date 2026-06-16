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

    public function colisageDashboard(): array
    {
        return $this->dashboard('colisage', 'Colisage', 'COL', 'colisage', '#f97316', '#ca8a04', 'linear-gradient(135deg,#ca8a04,#f97316)', 'Packing-list, colis, volumes, poids, conteneurs et contrôle documentaire des marchandises.', [
            ['label' => 'Colis', 'value' => '0', 'meta' => 'Colis créés'],
            ['label' => 'Expéditions', 'value' => '0', 'meta' => 'Manifestes en cours'],
            ['label' => 'Retraits', 'value' => '0', 'meta' => 'Colis livrés'],
        ], [
            ['label' => 'Créer Colis', 'hint' => 'Réceptionner de la marchandise', 'url' => '/colisage/colis/nouveau'],
            ['label' => 'Liste Colis', 'hint' => 'Suivre les statuts', 'url' => '/colisage/colis'],
            ['label' => 'Expéditions', 'hint' => 'Gérer les manifestes', 'url' => '/colisage/expeditions'],
        ]);
    }

    public function flotteDashboard(): array
    {
        return $this->dashboard('flotte', 'Flotte / Transport', 'TRP', 'flotte', '#3b82f6', '#1d4ed8', 'linear-gradient(135deg,#1d4ed8,#3b82f6)', 'Gestion des chauffeurs, coursiers, véhicules, disponibilités et planning des tournées.', [
            ['label' => 'Livreurs', 'value' => '0', 'meta' => 'Disponibles'],
        ], [
            ['label' => 'Voir la flotte', 'hint' => 'Lister les véhicules', 'url' => '/flotte'],
        ]);
    }

    public function trackingDashboard(): array
    {
        return $this->dashboard('tracking', 'Tracking Colis', 'TRK', 'tracking-colis', '#8b5cf6', '#4c1d95', 'linear-gradient(135deg,#4c1d95,#8b5cf6)', 'Suivi GPS, étapes de voyage, statuts en temps réel et notification clients.', [
            ['label' => 'Points GPS', 'value' => '0', 'meta' => 'Aujourd\'hui'],
        ], [
            ['label' => 'Rechercher Tracking', 'hint' => 'Saisir un numéro', 'url' => '/tracking'],
        ]);
    }

    public function entrepotsDashboard(): array
    {
        return $this->dashboard('entrepots', 'Entrepôts', 'WHS', 'entrepots', '#10b981', '#047857', 'linear-gradient(135deg,#047857,#10b981)', 'Campagnes d\'inventaire, scan de colis, détection des anomalies (manquants/endommagés).', [
            ['label' => 'Inventaires', 'value' => '0', 'meta' => 'En cours'],
        ], [
            ['label' => 'Lancer Inventaire', 'hint' => 'Nouvelle campagne', 'url' => '/entrepots'],
        ]);
    }

    public function transitDouaneDashboard(): array
    {
        return $this->dashboard('transit-douane', 'Transit Douane', 'CUS', 'transit-douane', '#f59e0b', '#b45309', 'linear-gradient(135deg,#b45309,#f59e0b)', 'Référentiel des prestataires douanes et fret aérien, gestion LTA.', [
            ['label' => 'Prestataires', 'value' => '0', 'meta' => 'Actifs'],
        ], [
            ['label' => 'Nouveau Prestataire', 'hint' => 'Douane ou Fret', 'url' => '/transit-douane/create'],
        ]);
    }

    public function facturationDashboard(): array
    {
        return $this->dashboard('facturation', 'Facturation', 'INV', 'facturation', '#ef4444', '#b91c1c', 'linear-gradient(135deg,#b91c1c,#ef4444)', 'Saisie des factures prestataires, suivi multi-devises (EUR/XOF).', [
            ['label' => 'Factures', 'value' => '0', 'meta' => 'En attente'],
        ], [
            ['label' => 'Saisir Facture', 'hint' => 'Nouvelle facture', 'url' => '/facturation/create'],
        ]);
    }

    public function financeDashboard(): array
    {
        return $this->dashboard('finance', 'Finance', 'FIN', 'finance', '#ec4899', '#be185d', 'linear-gradient(135deg,#be185d,#ec4899)', 'Retraits Hub (Caisse Centrale), Compensation et Crédits Inter-agences.', [
            ['label' => 'Retraits', 'value' => '0 XOF', 'meta' => 'Aujourd\'hui'],
        ], [
            ['label' => 'Nouveau Retrait', 'hint' => 'Caisse centrale', 'url' => '/finance/retraits/create'],
        ]);
    }

    public function logistiqueDashboard(): array
    {
        return $this->dashboard('logistique', 'Logistique', 'LOG', 'logistique', '#64748b', '#334155', 'linear-gradient(135deg,#334155,#64748b)', 'Demandes d\'approvisionnement des agences en fournitures et consommables.', [
            ['label' => 'Demandes', 'value' => '0', 'meta' => 'En attente'],
        ], [
            ['label' => 'Nouvelle Demande', 'hint' => 'Fournitures agence', 'url' => '/logistique/demandes/create'],
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
