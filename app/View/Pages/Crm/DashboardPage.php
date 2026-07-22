<?php

declare(strict_types=1);

namespace App\View\Pages\Crm;

final class DashboardPage
{
    /** @var array<int,array{label:mixed,value:mixed,meta?:mixed,tone?:string,href?:string}> */
    public readonly array $kpis;

    /** @var array<int,array{label:string,href:string,icon:string,variant?:string}> */
    public readonly array $quickActions;

    public function __construct(array $moduleData)
    {
        $this->kpis = array_map(static function (array $kpi): array {
            $kpi['tone'] = 'warning';
            return $kpi;
        }, $moduleData['kpis'] ?? []);

        $this->quickActions = [
            ['label' => 'Nouveau client', 'href' => 'crm/dashboard', 'icon' => '<svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5c-2.2 0-4 1.8-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>', 'variant' => 'accent'],
            ['label' => 'Pipeline commercial', 'href' => 'crm/dashboard', 'icon' => '<svg viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>', 'variant' => 'primary'],
            ['label' => 'Interactions clients', 'href' => 'crm/dashboard', 'icon' => '<svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>', 'variant' => 'secondary'],
        ];
    }
}
