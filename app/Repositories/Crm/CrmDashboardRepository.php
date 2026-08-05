<?php

declare(strict_types=1);

namespace App\Repositories\Crm;

final class CrmDashboardRepository extends \App\Repositories\Shared\ModuleDashboardRepository
{
    /**
     * @return array<string,mixed>
     */
    public function dashboard(): array
    {
        $data = $this->dashboardFor('crm');

        $stats = (new CrmRepository($this->pdo))->dashboardStats();

        $data['kpis'] = [
            ['label' => 'Clients actifs', 'value' => (string) $stats['clientsCount'], 'meta' => (string) $stats['prospectsCount'] . ' prospect(s) en cours', 'href' => 'crm/clients?crm_status=actif'],
            ['label' => 'Relances à venir', 'value' => (string) $stats['relancesCount'], 'meta' => 'Actions commerciales planifiées'],
            ['label' => 'Opportunités ouvertes', 'value' => (string) $stats['opportunitesCount'], 'meta' => 'Dossiers commerciaux actifs'],
            ['label' => 'Interactions', 'value' => (string) $stats['interactionsCount'], 'meta' => 'Historique relation client'],
        ];

        return $data;
    }
}
