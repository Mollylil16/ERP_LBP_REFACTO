<?php

namespace App\Services\Rh;

use App\Security\PermissionEntityRegistry;
use App\Models\RhDashboard;
use App\Repositories\Rh\RhDashboardRepository;
use App\Services\Support\DataVisibilityService;

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
                'description' => 'Absences, conges, prets, avances, heures sup et regularisations a traiter.',
                'count' => $counts['legalRequests'],
                'tone' => 'success',
                'href' => 'rh/cycle-vie?section=workflows',
            ],
            [
                'label' => 'Contrats a parametrer',
                'description' => 'Employes actifs sans contrat RH exploitable par la paie.',
                'count' => $counts['contractsMissing'],
                'tone' => 'warning',
                'href' => 'rh/cycle-vie?section=contracts',
            ],
            [
                'label' => 'Reclamations salariales',
                'description' => 'Dossiers en attente de validation RH dans le circuit de paie.',
                'count' => $counts['salaryClaims'],
                'tone' => 'danger',
                'href' => 'rh/validations?type=salary',
            ],
            [
                'label' => 'Explications a suivre',
                'description' => 'Demandes ouvertes ou en attente de reponse employe.',
                'count' => $counts['explanations'],
                'tone' => 'pink',
                'href' => 'rh/cycle-vie?section=discipline',
            ],
            [
                'label' => 'Soldes conges a initialiser',
                'description' => 'Collaborateurs actifs sans solde initial renseigne.',
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
            pendingRequests: $this->repository->getRecentPendingRequests(),
            dailyAttendance: $canViewEmployees ? $this->repository->getDailyAttendance() : [],
            monthlyTrend: $canViewEmployees ? $this->repository->getMonthlyTrend() : [],
            employeeList: $canViewEmployees ? $this->repository->getEmployeeList() : [],
        );
    }
}
