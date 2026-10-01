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

    /** Ce que chaque agence a enregistré ce mois-ci. @var array<int,array<string,mixed>> */
    public readonly array $activiteAgences;

    /** Et sur quelles lignes ces colis partent. @var array<int,array<string,mixed>> */
    public readonly array $activiteTrafics;

    public readonly string $moisLibelle;

    public function __construct(array $moduleData)
    {
        $this->kpis = $moduleData['kpis'] ?? [];

        $this->recentParcels = array_map(static function (array $p): array {
            $statut = (string) ($p['statut'] ?? '');
            // La table ecrit « enregistre », « en_transit », « arrive »,
            // « livre », « retire » : les libelles accentues ne tombaient
            // jamais, et toutes les lignes sortaient en gris.
            $p['status_tone'] = match (strtolower($statut)) {
                'retire', 'livre' => 'success',
                'enregistre' => 'info',
                'facture' => 'warning',
                'en_transit' => 'primary',
                'arrive' => 'accent',
                default => 'neutral'
            };
            return $p;
        }, $moduleData['recentParcels'] ?? []);

        $this->recentExpeditions = array_map(static function (array $e): array {
            $statut = (string) ($e['statut'] ?? '');
            $e['status_tone'] = match (strtoupper($statut)) {
                'ARRIVE', 'CLOTURE' => 'success',
                'EN_TRANSIT' => 'primary',
                'EN_PREPARATION' => 'warning',
                default => 'neutral'
            };
            return $e;
        }, $moduleData['recentExpeditions'] ?? []);

        $this->quickActions = $moduleData['quickActions'] ?? [];
        $this->activiteAgences = $moduleData['activiteAgences'] ?? [];
        $this->activiteTrafics = $moduleData['activiteTrafics'] ?? [];
        $this->moisLibelle = (string) ($moduleData['moisLibelle'] ?? '');
    }
}
