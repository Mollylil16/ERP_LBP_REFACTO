<?php

declare(strict_types=1);

namespace App\View\Pages\Finance;

final class DashboardPage
{
    /** @var array<int,array{label:mixed,value:mixed,meta?:mixed,tone?:string,href?:string}> */
    public readonly array $kpis;

    /** @var array<int,array<string,mixed>> */
    public readonly array $recentFactures;

    /** @var array<int,array<string,mixed>> */
    public readonly array $recentEcritures;

    /** @var array<int,array<string,mixed>> */
    public readonly array $recentEtats;

    /** @var array<int,array{label:string,href:string,icon:string,variant?:string}> */
    public readonly array $quickActions;

    public function __construct(
        public readonly array $stats,
        array $recentFactures,
        array $recentEcritures,
        array $recentEtats
    ) {
        $this->kpis = [
            [
                'label' => 'Total Facturé',
                'value' => number_format($stats['facture_xof'] ?? 0, 0, ',', ' ') . ' XOF',
                'meta' => number_format($stats['facture_eur'] ?? 0, 2, ',', ' ') . ' EUR',
                'tone' => 'primary',
                'href' => 'finance/factures'
            ],
            [
                'label' => 'Fonds Encaissés',
                'value' => number_format($stats['encaisse_xof'] ?? 0, 0, ',', ' ') . ' XOF',
                'meta' => number_format($stats['encaisse_eur'] ?? 0, 2, ',', ' ') . ' EUR',
                'tone' => 'success',
                'href' => 'finance/factures'
            ],
            [
                'label' => 'Reste à Recouvrer',
                'value' => number_format($stats['restant_xof'] ?? 0, 0, ',', ' ') . ' XOF',
                'meta' => number_format($stats['restant_eur'] ?? 0, 2, ',', ' ') . ' EUR',
                'tone' => 'warning',
                'href' => 'finance/factures'
            ],
            [
                'label' => 'Décaissements Prestataires',
                'value' => (string) ($stats['pending_payouts'] ?? 0),
                'meta' => 'Demandes en attente',
                'tone' => 'danger',
                'href' => 'finance/depenses'
            ],
            [
                'label' => 'Clôtures Caisse d\'Agences',
                'value' => (string) ($stats['pending_closures'] ?? 0),
                'meta' => 'Rapports à consolider',
                'tone' => 'info',
                'href' => 'finance/clotures'
            ]
        ];

        $this->recentFactures = array_map(static function (array $f): array {
            $f['formatted_date'] = $f['date_emission'] ? date('d/m/Y', strtotime($f['date_emission'])) : '—';
            $f['client_name_display'] = $f['client_name'] ?: 'Client inconnu';
            $f['montant_total_formatted'] = number_format((float)$f['montant_total'], 2, ',', ' ') . ' ' . $f['devise'];
            $f['montant_restant_formatted'] = number_format((float)$f['montant_restant'], 2, ',', ' ') . ' ' . $f['devise'];
            
            $f['status_display'] = str_replace('_', ' ', ucfirst($f['statut']));
            $f['status_tone'] = match($f['statut']) {
                'payee' => 'success',
                'partiellement_payee' => 'warning',
                'emise' => 'info',
                'en_retard' => 'danger',
                default => 'neutral'
            };
            return $f;
        }, $recentFactures);

        $this->recentEcritures = array_map(static function (array $e): array {
            $e['formatted_date'] = date('d/m/Y', strtotime($e['date_ecriture']));
            $e['montant_formatted'] = number_format((float)$e['montant'], 2, ',', ' ') . ' ' . $e['devise'];
            return $e;
        }, $recentEcritures);

        $this->recentEtats = array_map(static function (array $et): array {
            $et['formatted_date'] = date('d/m/Y', strtotime($et['date_jour']));
            $et['solde_xof_formatted'] = number_format((float)$et['solde_caisse_agence_xof'], 0, ',', ' ') . ' XOF';
            $et['solde_eur_formatted'] = number_format((float)$et['solde_caisse_agence_eur'], 2, ',', ' ') . ' EUR';
            $et['status_display'] = ucfirst($et['statut']);
            $et['status_tone'] = match($et['statut']) {
                'consolide' => 'success',
                'soumis' => 'info',
                default => 'neutral'
            };
            return $et;
        }, $recentEtats);

        $this->quickActions = [
            ['label' => 'Nouvelle Facture', 'href' => 'finance/factures/nouveau', 'icon' => '<svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>', 'variant' => 'accent'],
            ['label' => 'Factures', 'href' => 'finance/factures', 'icon' => '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>', 'variant' => 'secondary'],
            ['label' => 'Nouvelle Dépense', 'href' => 'finance/depenses/nouveau', 'icon' => '<svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>', 'variant' => 'primary'],
            ['label' => 'Dépenses', 'href' => 'finance/depenses', 'icon' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>', 'variant' => 'secondary'],
            ['label' => 'Grand Livre', 'href' => 'finance/comptabilite', 'icon' => '<svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>', 'variant' => 'secondary'],
            ['label' => 'Clôtures Caisse', 'href' => 'finance/clotures', 'icon' => '<svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>', 'variant' => 'secondary', 'count' => (int) ($stats['pending_closures'] ?? 0)],
        ];
    }
}
