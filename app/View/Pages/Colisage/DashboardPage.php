<?php

declare(strict_types=1);

namespace App\View\Pages\Colisage;

final class DashboardPage
{
    /** @var array<int,array{label:mixed,value:mixed,meta?:mixed,tone?:string,href?:string}> */
    public readonly array $kpis;

    /** @var array<int,array<string,mixed>> */
    public readonly array $recentParcels;

    /** @var array<int,array<string,mixed>> */
    public readonly array $recentExpeditions;

    /** @var array<int,array{label:string,href:string,icon:string,variant?:string}> */
    public readonly array $quickActions;

    public function __construct(array $moduleData)
    {
        $this->kpis = $moduleData['kpis'] ?? [];

        $this->recentParcels = array_map(static function (array $p): array {
            $p['status_tone'] = match($p['statut']) {
                'RETIRÉ', 'LIVRÉ' => 'success',
                'RÉCEPTIONNÉ' => 'info',
                'EN_PRÉPARATION' => 'warning',
                'EN_TRANSIT' => 'primary',
                default => 'neutral'
            };
            return $p;
        }, $moduleData['recentParcels'] ?? []);

        $this->recentExpeditions = array_map(static function (array $e): array {
            $e['status_tone'] = match($e['statut']) {
                'ARRIVÉ' => 'success',
                'EN_TRANSIT' => 'primary',
                default => 'neutral'
            };
            return $e;
        }, $moduleData['recentExpeditions'] ?? []);

        $this->quickActions = [
            ['label' => 'Enregistrer un colis', 'href' => 'colisage/parcels/nouveau', 'icon' => '<svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>', 'variant' => 'accent'],
            ['label' => 'Planifier un groupage (manifeste)', 'href' => 'colisage/groupage/nouveau', 'icon' => '<svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>', 'variant' => 'primary'],
            ['label' => 'Suivi des manifestes', 'href' => 'colisage/groupage', 'icon' => '<svg viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>', 'variant' => 'secondary'],
        ];
    }
}
