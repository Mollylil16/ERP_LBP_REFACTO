<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\View;
use App\Models\Finance\DemandeFonds;

/**
 * Écrans des demandes de fonds : liste, filtres et pagination.
 *
 * Les cinq statuts portent chacun une couleur et une icône fixes, définies une
 * seule fois ici. Les répéter dans chaque écran avait déjà produit des nuances
 * divergentes d'une page à l'autre pour un même état.
 */
final class FinanceFonds
{
    /** Nombre de lignes par page, aligné sur la requête du contrôleur. */
    private const PAR_PAGE = 15;

    /**
     * @return array<string, array{label:string, tone:string, icone:string}>
     */
    private static function statuts(): array
    {
        return [
            'en_attente' => [
                'label' => 'En attente de validation',
                'tone' => 'danger',
                'icone' => self::icone('<circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>'),
            ],
            'validee' => [
                'label' => 'Validée (à décaisser)',
                'tone' => 'warning',
                'icone' => self::icone('<polyline points="20 6 9 17 4 12"></polyline>'),
            ],
            'decaissee' => [
                'label' => 'Décaissée (en cours)',
                'tone' => 'info',
                'icone' => self::icone('<rect x="2" y="6" width="20" height="12" rx="2"></rect><circle cx="12" cy="12" r="2"></circle>'),
            ],
            'imputee' => [
                'label' => 'Imputée & clôturée',
                'tone' => 'success',
                'icone' => self::icone('<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline>'),
            ],
            'rejetee' => [
                'label' => 'Rejetée',
                'tone' => 'neutral',
                'icone' => self::icone('<line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>'),
            ],
        ];
    }

    /**
     * @param array<int, DemandeFonds> $items
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $stats
     * @param array<int, array{id:int, name:string, code:string}> $agences
     */
    public static function listePage(
        array $items,
        int $total,
        int $page,
        int $totalPages,
        array $filters,
        array $stats,
        array $agences
    ): string {
        $entete = Ui::pageHeader(
            'Demandes de fonds',
            'Gestion, traçabilité et validation des décaissements sur dossiers de transit et de fonctionnement.',
            [
                'eyebrow' => 'Trésorerie & décaissements',
                'class' => 'rh-hero-white',
                'actions' => Ui::button(
                    'Ajouter une demande de décaissement',
                    ['href' => 'finance/fonds/nouveau', 'variant' => 'primary']
                ),
            ]
        );

        return '<div class="finea-shell"><div class="finea-container">'
            . $entete
            . Dashboard::kpis(self::kpis($stats))
            . '<div style="margin-bottom:1.5rem;">' . self::filtres($filters, $agences) . '</div>'
            . Ui::section(
                'Liste des demandes enregistrées',
                self::table($items, $page) . self::pagination($page, $totalPages, $total, $filters),
                $total . ' demande(s) au total'
            )
            . '</div></div>';
    }

    /**
     * @param array<string, mixed> $stats
     * @return array<int, array<string, mixed>>
     */
    private static function kpis(array $stats): array
    {
        $decaissees = (int) ($stats['total_decaissees'] ?? 0) + (int) ($stats['total_imputees'] ?? 0);

        return [
            [
                'label' => 'Total demandes',
                'value' => number_format((float) ($stats['total_demandes'] ?? 0), 0, ',', ' '),
                'meta' => number_format((float) ($stats['montant_total_demande'] ?? 0), 0, ',', ' ') . ' FCFA engagés',
            ],
            [
                'label' => 'En attente de validation',
                'value' => number_format((float) ($stats['total_en_attente'] ?? 0), 0, ',', ' '),
                'meta' => 'À valider par la direction',
                'tone' => 'danger',
            ],
            [
                'label' => 'Validées (à décaisser)',
                'value' => number_format((float) ($stats['total_validees'] ?? 0), 0, ',', ' '),
                'meta' => 'En attente de caisse',
                'tone' => 'warning',
            ],
            [
                'label' => 'Décaissées / imputées',
                'value' => number_format((float) $decaissees, 0, ',', ' '),
                'meta' => number_format((float) ($stats['montant_total_decaisse'] ?? 0), 0, ',', ' ') . ' FCFA décaissés',
                'tone' => 'success',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<int, array{id:int, name:string, code:string}> $agences
     */
    private static function filtres(array $filters, array $agences): string
    {
        $optionsAgences = [['value' => '', 'label' => 'Toutes les agences']];
        foreach ($agences as $agence) {
            $optionsAgences[] = [
                'value' => (string) $agence['id'],
                'label' => $agence['name'] . ' (' . $agence['code'] . ')',
            ];
        }

        $optionsStatuts = [['value' => '', 'label' => 'Tous les états']];
        foreach (self::statuts() as $code => $statut) {
            $optionsStatuts[] = ['value' => $code, 'label' => $statut['label']];
        }

        $champs = Form::input('date_from', [
                'label' => 'Période du',
                'type' => 'date',
                'value' => (string) ($filters['date_from'] ?? ''),
            ])
            . Form::input('date_to', [
                'label' => 'Au',
                'type' => 'date',
                'value' => (string) ($filters['date_to'] ?? ''),
            ])
            . Form::select('statut', $optionsStatuts, (string) ($filters['statut'] ?? ''), ['label' => 'État'])
            . Form::select('agence_id', $optionsAgences, (string) ($filters['agence_id'] ?? ''), ['label' => 'Agence'])
            . Form::select(
                'cadre',
                [
                    ['value' => '', 'label' => 'Tous les cadres'],
                    ['value' => 'traitement_dossier', 'label' => 'Traitement de dossier'],
                    ['value' => 'fonctionnement', 'label' => 'Fonctionnement'],
                ],
                (string) ($filters['cadre'] ?? ''),
                ['label' => 'Cadre']
            )
            . Form::input('q', [
                'label' => 'Recherche',
                'value' => (string) ($filters['q'] ?? ''),
                'placeholder' => 'N° de demande, motif, dossier',
            ]);

        $boutons = '<div class="finea-field" style="display:flex; gap:.5rem; align-items:end;">'
            . Ui::button('Filtrer', ['variant' => 'primary', 'type' => 'submit'])
            . Ui::button('Réinitialiser', ['href' => 'finance/fonds', 'variant' => 'secondary'])
            . '</div>';

        return Ui::section(
            'Filtres',
            '<form method="get" action="' . View::url('finance/fonds') . '">'
                . '<div class="rh-form-grid" style="gap:1rem;">' . $champs . $boutons . '</div>'
                . '</form>'
        );
    }

    /**
     * @param array<int, DemandeFonds> $items
     */
    private static function table(array $items, int $page): string
    {
        $statuts = self::statuts();
        $lignes = [];

        foreach (array_values($items) as $index => $item) {
            $statut = $statuts[$item->statut] ?? [
                'label' => ucfirst($item->statut),
                'tone' => 'neutral',
                'icone' => '',
            ];

            $lignes[] = [
                (string) (($page - 1) * self::PAR_PAGE + $index + 1),
                '<a href="' . View::url('finance/fonds/' . $item->id) . '"><strong>'
                    . View::e($item->numeroDemande) . '</strong></a>',
                View::e(self::date($item->createdAt)),
                Ui::badge(
                    $item->cadre === 'traitement_dossier' ? 'Traitement de dossier' : 'Fonctionnement',
                    $item->cadre === 'traitement_dossier' ? 'info' : 'neutral'
                ),
                $item->dossierNum
                    ? '<code>' . View::e($item->dossierNum) . '</code>'
                    : '<small>—</small>',
                '<span title="' . View::e($item->motif) . '">' . View::e($item->motif) . '</span>',
                '<strong>' . ModuleTable::montant($item->montant, $item->devise) . '</strong>',
                View::e($item->demandeurNom ?? '—'),
                View::e($item->agenceNom ?? '—'),
                Ui::badge($statut['label'], $statut['tone'], ['icon' => $statut['icone']]),
                self::bonDeCaisse($item),
                self::actions($item),
            ];
        }

        return ModuleTable::render(
            [
                ['label' => 'N°', 'align' => 'center'],
                ['label' => 'Numéro'],
                ['label' => 'Date'],
                ['label' => 'Cadre', 'align' => 'center'],
                ['label' => 'Dossier'],
                ['label' => 'Libellé / motif'],
                ['label' => 'Montant', 'align' => 'right'],
                ['label' => 'Demandeur'],
                ['label' => 'Agence'],
                ['label' => 'Statut', 'align' => 'center'],
                ['label' => 'Bon de caisse', 'align' => 'center'],
                ['label' => 'Actions', 'align' => 'right'],
            ],
            $lignes,
            'Aucune demande de fonds',
            'Modifiez vos critères de recherche, ou créez une demande de décaissement.'
        );
    }

    private static function bonDeCaisse(DemandeFonds $item): string
    {
        if (!in_array($item->statut, ['validee', 'decaissee', 'imputee'], true)) {
            return '<small title="Disponible après validation">—</small>';
        }

        return '<a href="' . View::url('finance/fonds/' . $item->id . '/bon-caisse-pdf') . '"'
            . ' target="_blank" rel="noopener"'
            . ' title="Imprimer le bon de sortie de caisse"'
            . ' aria-label="Imprimer le bon de sortie de caisse">'
            . self::icone(
                '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>'
                . '<polyline points="14 2 14 8 20 8"></polyline>'
                . '<line x1="16" y1="13" x2="8" y2="13"></line>'
                . '<line x1="16" y1="17" x2="8" y2="17"></line>',
                16
            )
            . '</a>';
    }

    private static function actions(DemandeFonds $item): string
    {
        $html = Ui::button('Détail', ['href' => 'finance/fonds/' . $item->id, 'variant' => 'secondary']);

        // Une demande n'est supprimable que tant qu'elle n'a rien engagé, et
        // seulement par la direction ou par celui qui l'a émise.
        $peutSupprimer = $item->statut === 'en_attente'
            && (Auth::isAdmin()
                || Auth::hasAnyRole(['dg', 'assistant_dg'])
                || (int) Auth::id() === $item->demandeurId);

        if ($peutSupprimer) {
            $html .= Ui::deleteForm(
                'finance/fonds/' . $item->id . '/supprimer',
                'Confirmer la suppression de cette demande de fonds ?',
                ['label' => 'Supprimer']
            );
        }

        return '<div style="display:inline-flex; gap:6px; align-items:center;">' . $html . '</div>';
    }

    /**
     * @param array<string, mixed> $filters
     */
    private static function pagination(int $page, int $totalPages, int $total, array $filters): string
    {
        if ($totalPages <= 1) {
            return '';
        }

        $liens = '';
        for ($p = 1; $p <= $totalPages; $p++) {
            $parametres = array_filter(array_merge($filters, ['page' => $p]));
            $url = View::url('finance/fonds') . '?' . http_build_query($parametres);

            $liens .= '<a href="' . View::e($url) . '"'
                . ' class="finea-page-link' . ($p === $page ? ' is-current" aria-current="page"' : '"') . '>'
                . $p . '</a>';
        }

        return '<div class="finea-pagination">'
            . '<span>Page <strong>' . $page . '</strong> sur <strong>' . $totalPages . '</strong>'
            . ' — ' . $total . ' demande(s)</span>'
            . '<div class="finea-pagination-links">' . $liens . '</div>'
            . '</div>'
            . '<style>'
            . '.finea-pagination{display:flex;justify-content:space-between;align-items:center;'
            . 'gap:1rem;flex-wrap:wrap;padding-top:1rem;margin-top:1rem;border-top:1px solid #e2e8f0;'
            . 'font-size:.85rem;color:#64748b;}'
            . '.finea-pagination-links{display:flex;gap:4px;flex-wrap:wrap;}'
            . '.finea-page-link{padding:6px 12px;border-radius:6px;font-weight:700;'
            . 'text-decoration:none;color:#475569;background:#fff;border:1px solid #cbd5e1;}'
            . '.finea-page-link.is-current{background:#0f172a;color:#fff;border-color:#0f172a;}'
            . '</style>';
    }

    private static function date(?string $valeur): string
    {
        if ($valeur === null || $valeur === '') {
            return '—';
        }

        $horodatage = strtotime($valeur);

        return $horodatage !== false ? date('d/m/Y', $horodatage) : '—';
    }

    private static function icone(string $trace, int $taille = 12): string
    {
        return '<svg width="' . $taille . '" height="' . $taille . '" viewBox="0 0 24 24"'
            . ' fill="none" stroke="currentColor" stroke-width="2.2"'
            . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . $trace . '</svg>';
    }
}
