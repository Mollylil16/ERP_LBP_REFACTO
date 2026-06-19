<?php

declare(strict_types=1);

namespace App\View\Pages\Rh;

use App\Models\RhDashboard;

final class DashboardPage
{
    /** @var array<int,array{key:string,label:string,description:string,href:string}> */
    public readonly array $tabs;
    /** @var array<int,array<string,mixed>> */
    public readonly array $recentRows;
    /** @var array<string,mixed> */
    public readonly array $restrictedTables;

    public function __construct(
        public readonly RhDashboard $dashboard,
        public readonly string $mode,
        array $restrictedTables,
    ) {
        $this->restrictedTables = $restrictedTables;
        $this->tabs = [
            ['key' => 'classic', 'label' => 'Classique', 'description' => 'Effectifs, alertes et acces rapides', 'href' => 'rh/dashboard'],
            ['key' => 'statistique', 'label' => 'Statistique', 'description' => 'Indicateurs mensuels et repartitions', 'href' => 'rh/dashboard?view=statistique'],
            ['key' => 'analytique', 'label' => 'Analytique', 'description' => 'Lecture de pilotage et preparation des exports', 'href' => 'rh/dashboard?view=analytique'],
        ];
        $this->recentRows = array_map(static function (array $employee): array {
            $employee['employee_number'] = $employee['employee_number'] ?: 'Sans matricule';
            $employee['hire_date'] = self::date($employee['hire_date']);
            $employee['status'] = $employee['status_name'];
            return $employee;
        }, $dashboard->recentHires);
    }

    private static function date(?string $value): string
    {
        if (!$value) {
            return 'Non renseignee';
        }
        $timestamp = strtotime($value);
        return $timestamp ? date('d/m/Y', $timestamp) : 'Non renseignee';
    }
}
