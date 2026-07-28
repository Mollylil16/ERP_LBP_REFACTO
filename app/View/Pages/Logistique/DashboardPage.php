<?php

declare(strict_types=1);

namespace App\View\Pages\Logistique;

final class DashboardPage
{
    /** @var array<int,array{label:mixed,value:mixed,meta?:mixed,tone?:string,href?:string}> */
    public readonly array $kpis;

    /** @var array<int,array{label:string,href:string,icon:string,variant?:string}> */
    public readonly array $quickActions;

    public function __construct(array $moduleData)
    {
        $this->kpis = array_map(static function (array $kpi): array {
            $kpi['tone'] = 'primary';
            return $kpi;
        }, $moduleData['kpis'] ?? []);

        $this->quickActions = [
            ['label' => 'Enregistrer un colis', 'href' => 'colisage/parcels/nouveau', 'icon' => '<svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>', 'variant' => 'accent'],
            ['label' => 'Suivi de livraison', 'href' => 'colisage/parcels', 'icon' => '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>', 'variant' => 'primary'],
            ['label' => 'Suivi GPS', 'href' => 'colisage/exploitation/tracking', 'icon' => '<svg viewBox="0 0 24 24"><polygon points="3 11 22 2 13 21 11 13 3 11"></polygon></svg>', 'variant' => 'secondary'],
        ];
    }
}
