<?php

namespace App\Services;

use App\Security\PermissionEntityRegistry;
use App\Models\RhDashboard;
use App\Repositories\RhDashboardRepository;

class RhDashboardService
{
    public function __construct(
        private RhDashboardRepository $repository,
        private ?DataVisibilityService $visibility = null,
    ) {
        $this->visibility ??= new DataVisibilityService();
    }

    public function build(): RhDashboard
    {
        $counts = $this->repository->getOperationalCounts();
        $alerts = [
            [
                'label' => 'Demandes employes',
                'description' => 'Conges, absences, avances et regularisations a traiter.',
                'count' => $counts['legalRequests'],
                'tone' => 'success',
                'href' => 'rh/cycle-vie?section=workflows',
            ],
            [
                'label' => 'Contrats a parametrer',
                'description' => 'Collaborateurs actifs sans contrat exploitable.',
                'count' => $counts['contractsMissing'],
                'tone' => 'warning',
                'href' => 'rh/cycle-vie?section=contracts',
            ],
            [
                'label' => 'Explications a suivre',
                'description' => 'Demandes ouvertes en attente de reponse.',
                'count' => $counts['explanations'],
                'tone' => 'danger',
                'href' => 'rh/cycle-vie?section=discipline',
            ],
            [
                'label' => 'Soldes conges a initialiser',
                'description' => 'Soldes de depart restant a renseigner.',
                'count' => $counts['leaveOpeningMissing'],
                'tone' => 'info',
                'href' => 'rh/parametrage',
            ],
        ];

        $canViewEmployees = $this->visibility->canView(PermissionEntityRegistry::RH_EMPLOYEES);
        $stats = $canViewEmployees ? $this->repository->getStats() : [
            'total' => 0,
            'active' => 0,
            'inactive' => 0,
            'currentYearHires' => 0,
            'services' => 0,
        ];
        if (!$this->visibility->canView(PermissionEntityRegistry::RH_SERVICES)) {
            $stats['services'] = 0;
        }

        return new RhDashboard(
            stats: $stats,
            services: $canViewEmployees && $this->visibility->canView(PermissionEntityRegistry::RH_SERVICES) ? $this->repository->getServiceDistribution() : [],
            functions: $canViewEmployees && $this->visibility->canView(PermissionEntityRegistry::RH_FUNCTIONS) ? $this->repository->getFunctionDistribution() : [],
            statuses: $canViewEmployees && $this->visibility->canView(PermissionEntityRegistry::RH_STATUSES) ? $this->repository->getStatusDistribution() : [],
            recentHires: $canViewEmployees ? $this->visibility->employeeRows($this->repository->getRecentHires()) : [],
            alerts: $alerts,
            analytics: $this->repository->getAnalytics(),
            pendingTotal: array_sum($counts),
        );
    }
}
