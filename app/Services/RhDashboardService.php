<?php

namespace App\Services;

use App\Models\RhDashboard;
use App\Repositories\RhDashboardRepository;

class RhDashboardService
{
    public function __construct(private RhDashboardRepository $repository) {}

    public function build(): RhDashboard
    {
        $counts = $this->repository->getOperationalCounts();
        $alerts = [
            [
                'label' => 'Demandes employes',
                'description' => 'Conges, absences, avances et regularisations a traiter.',
                'count' => $counts['legalRequests'],
                'tone' => 'success',
            ],
            [
                'label' => 'Contrats a parametrer',
                'description' => 'Collaborateurs actifs sans contrat exploitable.',
                'count' => $counts['contractsMissing'],
                'tone' => 'warning',
            ],
            [
                'label' => 'Explications a suivre',
                'description' => 'Demandes ouvertes en attente de reponse.',
                'count' => $counts['explanations'],
                'tone' => 'danger',
            ],
            [
                'label' => 'Soldes conges a initialiser',
                'description' => 'Soldes de depart restant a renseigner.',
                'count' => $counts['leaveOpeningMissing'],
                'tone' => 'info',
            ],
        ];

        return new RhDashboard(
            stats: $this->repository->getStats(),
            services: $this->repository->getServiceDistribution(),
            functions: $this->repository->getFunctionDistribution(),
            statuses: $this->repository->getStatusDistribution(),
            recentHires: $this->repository->getRecentHires(),
            alerts: $alerts,
            analytics: $this->repository->getAnalytics(),
            pendingTotal: array_sum($counts),
        );
    }
}
