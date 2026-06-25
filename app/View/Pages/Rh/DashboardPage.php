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
    /** @var array<int,array{label:string,href:string,icon:string,variant?:string}> */
    public readonly array $quickActions;
    /** @var array<int,array{type:string,employee:string,status:string,date:string}> */
    public readonly array $pendingRequests;

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

        $this->quickActions = [
            ['label' => 'Liste du personnel', 'href' => 'rh/personnel', 'icon' => '<svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>', 'variant' => 'secondary'],
            ['label' => 'Integrer un collaborateur', 'href' => 'rh/personnel/nouveau', 'icon' => '<svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="19" y1="8" x2="19" y2="14"></line><line x1="22" y1="11" x2="16" y2="11"></line></svg>', 'variant' => 'accent'],
            ['label' => 'Ordres de mission', 'href' => 'rh/missions', 'icon' => '<svg viewBox="0 0 24 24"><polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"></polygon><line x1="9" y1="3" x2="9" y2="18"></line><line x1="15" y1="6" x2="15" y2="21"></line></svg>', 'variant' => 'secondary'],
            ['label' => 'Validations', 'href' => 'rh/validations', 'icon' => '<svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>', 'variant' => 'secondary', 'count' => $dashboard->alerts[0]['count'] ?? 0],
            ['label' => 'Explications', 'href' => 'rh/explications', 'icon' => '<svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>', 'variant' => 'secondary'],
            ['label' => 'Pointage journalier', 'href' => 'rh/pointage', 'icon' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>', 'variant' => 'secondary'],
            ['label' => 'Paie', 'href' => 'rh/paie', 'icon' => '<svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>', 'variant' => 'secondary'],
            ['label' => 'Contrats employes', 'href' => 'rh/cycle-vie?section=contracts', 'icon' => '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>', 'variant' => 'secondary'],
            ['label' => 'Parametrage RH', 'href' => 'rh/parametrage', 'icon' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>', 'variant' => 'secondary'],
            ['label' => 'Regles contrats', 'href' => 'rh/regles-contrats', 'icon' => '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="3" x2="9" y2="21"></line><line x1="15" y1="3" x2="15" y2="21"></line><line x1="3" y1="9" x2="21" y2="9"></line><line x1="3" y1="15" x2="21" y2="15"></line></svg>', 'variant' => 'secondary'],
            ['label' => 'Signataires RH', 'href' => 'rh/signataires', 'icon' => '<svg viewBox="0 0 24 24"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>', 'variant' => 'secondary'],
            ['label' => 'Feries', 'href' => 'rh/feries', 'icon' => '<svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>', 'variant' => 'secondary'],
            ['label' => 'Portail', 'href' => 'selection_portail', 'icon' => '<svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>', 'variant' => 'secondary'],
        ];

        $typeLabels = [
            'absence' => 'Absence', 'conge' => 'Conge', 'pret' => 'Pret',
            'avance_salaire' => 'Avance salaire', 'heures_supplementaires' => 'Heures supplementaires',
            'regularisation' => 'Regularisation',
        ];
        $this->pendingRequests = array_map(static function (array $request) use ($typeLabels): array {
            $rawType = (string) ($request['request_type'] ?? '');
            return [
                'type' => $typeLabels[$rawType] ?? ucfirst(str_replace('_', ' ', $rawType)),
                'employee' => (string) ($request['employee_name'] ?? ''),
                'status' => 'En attente',
                'date' => self::date($request['submitted_at'] ?? $request['created_at'] ?? null),
            ];
        }, $dashboard->pendingRequests);
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
