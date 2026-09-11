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

    // ==================================================================
    // Fiche d'une demande
    // ==================================================================

    /**
     * @param array<int, array<string, mixed>> $historique
     */
    public static function fichePage(
        DemandeFonds $demande,
        array $historique,
        bool $peutValider,
        bool $peutDecaisser,
        bool $peutImputer
    ): string {
        $statut = self::statuts()[$demande->statut] ?? [
            'label' => ucfirst($demande->statut),
            'tone' => 'neutral',
            'icone' => '',
        ];

        $actions = Ui::button('Retour à la liste', ['href' => 'finance/fonds', 'variant' => 'ghost']);
        if (in_array($demande->statut, ['validee', 'decaissee', 'imputee'], true)) {
            $actions .= Ui::button(
                'Bon de sortie (PDF)',
                ['href' => 'finance/fonds/' . $demande->id . '/bon-caisse-pdf', 'variant' => 'primary', 'target' => '_blank']
            );
        }

        $entete = Ui::pageHeader(
            'Demande ' . $demande->numeroDemande,
            $demande->motif,
            [
                'eyebrow' => 'Trésorerie & décaissements',
                'class' => 'rh-hero-white',
                'badge' => Ui::badge($statut['label'], $statut['tone'], ['icon' => $statut['icone']]),
                'actions' => $actions,
            ]
        );

        $colonneGauche = self::recapitulatif($demande)
            . self::blocValidation($demande, $peutValider)
            . self::blocDecaissement($demande, $peutDecaisser)
            . self::blocImputation($demande, $peutImputer)
            . self::blocCloture($demande);

        return '<div class="finea-shell"><div class="finea-container">'
            . $entete
            . '<div class="fonds-fiche">'
            . '<div class="fonds-fiche-principal">' . $colonneGauche . '</div>'
            . '<aside>' . Ui::section('Journal de traçabilité', self::journal($historique)) . '</aside>'
            . '</div>'
            . self::styleFiche()
            . '</div></div>';
    }

    private static function recapitulatif(DemandeFonds $demande): string
    {
        $cadre = $demande->cadre === 'traitement_dossier'
            ? 'Traitement de dossier (transit)'
            : 'Fonctionnement général';

        $faits = ModuleTable::render(
            [['label' => 'Information'], ['label' => 'Valeur']],
            [
                ['Cadre de la dépense', Ui::badge($cadre, $demande->cadre === 'traitement_dossier' ? 'info' : 'neutral')],
                [
                    'N° dossier / transit',
                    $demande->dossierNum
                        ? '<code>' . View::e($demande->dossierNum) . '</code>'
                        : '<small>Non rattaché (fonctionnement)</small>',
                ],
                ['Montant sollicité', '<strong>' . ModuleTable::montant($demande->montant, $demande->devise) . '</strong>'],
                ['Agence', View::e($demande->agenceNom ?? 'Agence LBP')],
                ['Déposée le', View::e(self::dateHeure($demande->createdAt))],
                ['Demandeur', '<strong>' . View::e($demande->demandeurNom ?? '—') . '</strong>'],
                [
                    'Validateur (direction)',
                    $demande->validateurNom
                        ? '<strong>' . View::e($demande->validateurNom) . '</strong>'
                        : '<small>En attente</small>',
                ],
                [
                    'Décaissement caisse',
                    $demande->caissiereNom
                        ? '<strong>' . View::e($demande->caissiereNom) . '</strong>'
                        : '<small>Non décaissé</small>',
                ],
            ]
        );

        // Le motif peut être saisi sur plusieurs lignes : on les conserve.
        $motif = '<div class="fonds-motif">'
            . '<span class="fonds-motif-titre">Motif & libellé de la dépense</span>'
            . '<p>' . nl2br(View::e($demande->motif)) . '</p>'
            . '</div>';

        $rejet = '';
        if ($demande->statut === 'rejetee' && $demande->motifRejet) {
            $rejet = '<div class="fonds-encart fonds-encart--rejet">'
                . '<strong>Motif du rejet :</strong> ' . View::e($demande->motifRejet)
                . '</div>';
        }

        return Ui::section('Informations sur la demande', $faits . $motif . $rejet);
    }

    private static function blocValidation(DemandeFonds $demande, bool $peutValider): string
    {
        if ($demande->statut !== 'en_attente' || !$peutValider) {
            return '';
        }

        $valider = '<form method="post" action="' . View::url('finance/fonds/' . $demande->id . '/valider') . '"'
            . ' onsubmit="return confirm(\'Confirmer la validation de cette demande de fonds pour décaissement ?\');">'
            . Csrf::field()
            . Form::hidden('commentaire', 'Validé par la Direction')
            . Ui::button('Valider et autoriser le décaissement', ['variant' => 'success', 'type' => 'submit'])
            . '</form>';

        $rejeter = '<form method="post" action="' . View::url('finance/fonds/' . $demande->id . '/rejeter') . '"'
            . ' onsubmit="return confirm(\'Êtes-vous sûr de vouloir rejeter cette demande ?\');">'
            . Csrf::field()
            . '<div class="fonds-rejet">'
            . Form::input('motif_rejet', [
                'label' => 'Motif du rejet',
                'required' => true,
                'placeholder' => 'Indiquez pourquoi la demande est refusée',
            ])
            . Ui::button('Rejeter', ['variant' => 'danger', 'type' => 'submit'])
            . '</div></form>';

        return Ui::section(
            'Décision de la direction',
            '<p>Vous pouvez autoriser le décaissement en caisse, ou rejeter cette demande en motivant le refus.</p>'
                . '<div class="fonds-decision">' . $valider . $rejeter . '</div>',
            'Assistante DG / DG / Administrateur'
        );
    }

    private static function blocDecaissement(DemandeFonds $demande, bool $peutDecaisser): string
    {
        if ($demande->statut !== 'validee' || !$peutDecaisser) {
            return '';
        }

        $modes = [
            ['value' => 'Espèces', 'label' => 'Espèces (caisse agence)'],
            ['value' => 'Mobile Money (Wave / Orange)', 'label' => 'Mobile Money (Wave / Orange)'],
            ['value' => 'Chèque', 'label' => 'Chèque bancaire'],
            ['value' => 'Virement', 'label' => 'Virement bancaire'],
        ];

        $confirmation = 'Confirmer le décaissement de '
            . number_format($demande->montant, 0, ',', ' ') . ' FCFA ?';

        return Ui::section(
            'Prise en compte caisse & décaissement',
            '<p>La demande est validée par la direction. La caisse peut enregistrer le paiement '
                . 'et éditer le bon de sortie.</p>'
                . '<form method="post" action="' . View::url('finance/fonds/' . $demande->id . '/decaisser') . '"'
                . ' onsubmit="return confirm(\'' . View::e($confirmation) . '\');">'
                . Csrf::field()
                . '<div class="rh-form-grid" style="gap:1rem; align-items:end;">'
                . Form::select('mode_paiement', $modes, 'Espèces', ['label' => 'Mode de paiement'])
                . '<div class="finea-field">'
                . Ui::button('Enregistrer le décaissement', ['variant' => 'accent', 'type' => 'submit'])
                . '</div></div></form>'
        );
    }

    private static function blocImputation(DemandeFonds $demande, bool $peutImputer): string
    {
        if ($demande->statut !== 'decaissee' || !$peutImputer) {
            return '';
        }

        $champs = Form::input('montant_reel_depense', [
                'label' => 'Montant réel dépensé (FCFA)',
                'type' => 'number',
                'step' => '100',
                'min' => '0',
                'max' => (string) $demande->montant,
                'value' => (string) $demande->montant,
                'required' => true,
                'id' => 'fonds-montant-reel',
                'data-fonds-engage' => (string) $demande->montant,
            ])
            . Form::input('reliquat_affichage', [
                'label' => 'Reliquat restitué en caisse (FCFA)',
                'value' => '0 FCFA',
                'readonly' => true,
                'id' => 'fonds-reliquat',
                'hint' => 'Calculé automatiquement, non enregistré tel quel.',
            ]);

        return Ui::section(
            'Imputation comptable & décharge',
            '<p>Renseignez le montant réellement dépensé, pièces à l\'appui. Le reliquat est calculé '
                . 'et réintégré en caisse.</p>'
                . '<form method="post" action="' . View::url('finance/fonds/' . $demande->id . '/imputer') . '"'
                . ' onsubmit="return confirm(\'Valider définitivement cette imputation ?\');">'
                . Csrf::field()
                . '<div class="rh-form-grid" style="gap:1.25rem;">' . $champs . '</div>'
                . Form::input('pieces_justificatives', [
                    'label' => 'Références des pièces justificatives',
                    'placeholder' => 'Quittance douane n° 849204, reçu transport du 12/09',
                    'hint' => 'Quittances de douane, reçus, factures.',
                ])
                . Form::textarea('commentaires', [
                    'label' => 'Observations de clôture',
                    'rows' => 2,
                    'placeholder' => 'Observations comptables sur la dépense',
                ])
                . '<div style="text-align:right; margin-top:1rem;">'
                . Ui::button('Valider l\'imputation et clôturer', ['variant' => 'accent', 'type' => 'submit'])
                . '</div></form>'
                . self::scriptReliquat()
        );
    }

    private static function blocCloture(DemandeFonds $demande): string
    {
        if ($demande->statut !== 'imputee' || $demande->imputation === null) {
            return '';
        }

        $imputation = $demande->imputation;

        $table = ModuleTable::render(
            [
                ['label' => 'Montant initial', 'align' => 'right'],
                ['label' => 'Montant réel dépensé', 'align' => 'right'],
                ['label' => 'Reliquat reversé', 'align' => 'right'],
            ],
            [[
                ModuleTable::montant((float) $imputation->montantEngage),
                '<strong>' . ModuleTable::montant((float) $imputation->montantReelDepense) . '</strong>',
                '<strong>' . ModuleTable::montant((float) $imputation->montantReliquatRestitue) . '</strong>',
            ]]
        );

        $pieces = $imputation->piecesJustificatives
            ? '<p><strong>Pièces :</strong> ' . View::e($imputation->piecesJustificatives) . '</p>'
            : '';

        return Ui::section('Dossier imputé et clôturé', $table . $pieces);
    }

    /**
     * @param array<int, array<string, mixed>> $historique
     */
    private static function journal(array $historique): string
    {
        if ($historique === []) {
            return Ui::emptyState('Aucun historique', 'Aucun événement n\'a encore été consigné sur cette demande.');
        }

        $icones = [
            'CREATION' => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>',
            'VALIDATION' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>',
            'REJET' => '<line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>',
            'DECAISSEMENT' => '<rect x="2" y="6" width="20" height="12" rx="2"></rect><circle cx="12" cy="12" r="2"></circle>',
            'IMPUTATION' => '<polyline points="20 6 9 17 4 12"></polyline>',
            'MODIFICATION' => '<path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>',
        ];

        $html = '<ol class="fonds-journal">';
        foreach ($historique as $evenement) {
            $action = (string) ($evenement['action'] ?? '');
            $trace = $icones[$action] ?? '<circle cx="12" cy="12" r="4"></circle>';

            $html .= '<li>'
                . '<span class="fonds-journal-puce" aria-hidden="true">' . self::icone($trace, 10) . '</span>'
                . '<span class="fonds-journal-action">' . View::e($action) . '</span>'
                . '<strong>' . View::e((string) ($evenement['user_nom'] ?? 'Utilisateur système')) . '</strong>'
                . '<span class="fonds-journal-texte">' . View::e((string) ($evenement['commentaire'] ?? '')) . '</span>'
                . '<time>' . View::e(self::dateHeure((string) ($evenement['created_at'] ?? ''))) . '</time>'
                . '</li>';
        }

        return $html . '</ol>';
    }

    /**
     * Reliquat calculé côté navigateur, en écoutant le champ plutôt qu'avec un
     * gestionnaire inline : la valeur engagée voyage dans un attribut de données
     * au lieu d'être interpolée dans du JavaScript.
     */
    private static function scriptReliquat(): string
    {
        return '<script>'
            . '(function(){'
            . 'var champ=document.getElementById("fonds-montant-reel");'
            . 'var sortie=document.getElementById("fonds-reliquat");'
            . 'if(!champ||!sortie)return;'
            . 'var engage=parseFloat(champ.dataset.fondsEngage)||0;'
            . 'function calculer(){'
            . 'var reel=parseFloat(champ.value)||0;'
            . 'var reliquat=Math.max(0,engage-reel);'
            . 'sortie.value=new Intl.NumberFormat("fr-FR").format(reliquat)+" FCFA";'
            . '}'
            . 'champ.addEventListener("input",calculer);calculer();'
            . '})();'
            . '</script>';
    }

    private static function styleFiche(): string
    {
        return '<style>'
            . '.fonds-fiche{display:grid;grid-template-columns:minmax(0,2fr) minmax(260px,1fr);'
            . 'gap:1.5rem;align-items:start;}'
            . '@media (max-width:900px){.fonds-fiche{grid-template-columns:1fr;}}'
            . '.fonds-fiche-principal{display:flex;flex-direction:column;gap:1.5rem;}'
            . '.fonds-motif{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;'
            . 'padding:1rem;margin-top:1rem;}'
            . '.fonds-motif-titre{font-size:.75rem;font-weight:700;color:#64748b;'
            . 'text-transform:uppercase;letter-spacing:.03em;}'
            . '.fonds-motif p{margin:.35rem 0 0;line-height:1.5;font-weight:600;}'
            . '.fonds-encart{margin-top:1rem;border-radius:8px;padding:1rem;}'
            . '.fonds-encart--rejet{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;}'
            . '.fonds-decision{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem;}'
            . '.fonds-rejet{display:flex;gap:.5rem;align-items:end;}'
            . '.fonds-rejet .finea-field{flex:1;}'
            . '.fonds-journal{list-style:none;margin:0;padding:0 0 0 1.5rem;'
            . 'border-left:2px solid #e2e8f0;display:flex;flex-direction:column;gap:1.5rem;}'
            . '.fonds-journal li{position:relative;}'
            . '.fonds-journal-puce{position:absolute;left:-2.05rem;top:0;background:#fff;'
            . 'border:2px solid #cbd5e1;border-radius:50%;width:20px;height:20px;'
            . 'display:flex;align-items:center;justify-content:center;}'
            . '.fonds-journal-action{display:block;font-size:.75rem;font-weight:700;'
            . 'color:#0284c7;text-transform:uppercase;letter-spacing:.03em;}'
            . '.fonds-journal strong{display:block;margin:2px 0;}'
            . '.fonds-journal-texte{display:block;font-size:.85rem;color:#64748b;line-height:1.4;}'
            . '.fonds-journal time{display:block;font-size:.72rem;color:#94a3b8;margin-top:4px;}'
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

    private static function dateHeure(?string $valeur): string
    {
        if ($valeur === null || $valeur === '') {
            return '—';
        }

        $horodatage = strtotime($valeur);

        return $horodatage !== false ? date('d/m/Y à H:i', $horodatage) : '—';
    }

    private static function icone(string $trace, int $taille = 12): string
    {
        return '<svg width="' . $taille . '" height="' . $taille . '" viewBox="0 0 24 24"'
            . ' fill="none" stroke="currentColor" stroke-width="2.2"'
            . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . $trace . '</svg>';
    }
}
