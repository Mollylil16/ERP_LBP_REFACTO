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
            $statut = (string) ($p['statut'] ?? '');
            $p['status_tone'] = match($statut) {
                'RETIRÉ', 'LIVRÉ' => 'success',
                'RÉCEPTIONNÉ' => 'info',
                'EN_PRÉPARATION' => 'warning',
                'EN_TRANSIT' => 'primary',
                default => 'neutral'
            };
            return $p;
        }, $moduleData['recentParcels'] ?? []);

        $this->recentExpeditions = array_map(static function (array $e): array {
            $statut = (string) ($e['statut'] ?? '');
            $e['status_tone'] = match($statut) {
                'ARRIVÉ' => 'success',
                'EN_TRANSIT' => 'primary',
                default => 'neutral'
            };
            return $e;
        }, $moduleData['recentExpeditions'] ?? []);

        $this->quickActions = $moduleData['quickActions'] ?? [];
    }
}
