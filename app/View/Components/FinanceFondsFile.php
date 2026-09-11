<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\Csrf;
use App\Helpers\View;
use App\Models\Finance\DemandeFonds;

/**
 * Files d'attente des demandes de fonds : guichet de caisse et imputation.
 *
 * Les deux ecrans posent la meme question sous deux angles : qu'est-ce qui
 * attend mon geste. Ils partagent donc la meme structure — filtres, tableau,
 * bouton d'action — et ne different que par la colonne de date, le libelle du
 * bouton et les onglets d'etat.
 *
 * Le formulaire de creation vit ici aussi : c'est l'entree de la meme file.
 */
final class FinanceFondsFile
{
    /** Lignes par page, aligne sur les requetes du controleur. */
    private const PAR_PAGE = 25;

    // ==================================================================
    // Guichet de caisse
    // ==================================================================

    /**
     * @param array<int, DemandeFonds> $items
     * @param array<string, mixed> $filters
     * @param array<int, array{id:int, name:string, code:string}> $agences
     */
    public static function priseEnComptePage(
        array $items,
        int $total,
        int $page,
        array $filters,
        array $agences
    ): string {
        $entete = Ui::pageHeader(
            'Prise en compte',
            'Demandes validées par la direction, en attente de paiement par la caisse.',
            ['eyebrow' => 'Guichet de caisse & décaissements', 'class' => 'rh-hero-white']
        );

        return '<div class="finea-shell"><div class="finea-container">'
            . $entete
            . '<div style="margin-bottom:1.5rem;">'
            . self::filtres('finance/fonds/prise-en-compte', $filters, $agences)
            . '</div>'
            . Ui::section(
                'File d\'attente de décaissement',
                self::table($items, $page, 'decaissement'),
                $total . ' demande(s)'
            )
            . '</div></div>';
    }

    // ==================================================================
    // Imputation comptable
    // ==================================================================

    /**
     * @param array<int, DemandeFonds> $items
     * @param array<string, mixed> $filters
     * @param array<int, array{id:int, name:string, code:string}> $agences
     */
    public static function imputationPage(
        array $items,
        int $total,
        int $page,
        array $filters,
        array $agences,
        string $statutCourant
    ): string {
        $entete = Ui::pageHeader(
            'Imputation des fonds',
            'Fonds décaissés à justifier, et dossiers déjà clôturés.',
            ['eyebrow' => 'Comptabilité & justification', 'class' => 'rh-hero-white']
        );

        $onglets = Tabs::render(
            [
                ['key' => 'decaissee', 'label' => 'À justifier', 'href' => 'finance/fonds/imputation?statut=decaissee'],
                ['key' => 'imputee', 'label' => 'Clôturées', 'href' => 'finance/fonds/imputation?statut=imputee'],
            ],
            $statutCourant
        );

        return '<div class="finea-shell"><div class="finea-container">'
            . $entete
            . '<div style="margin-bottom:1.5rem;">' . $onglets . '</div>'
            . '<div style="margin-bottom:1.5rem;">'
            . self::filtres('finance/fonds/imputation', $filters, $agences, $statutCourant)
            . '</div>'
            . Ui::section(
                $statutCourant === 'imputee' ? 'Dossiers clôturés' : 'Fonds à justifier',
                self::table($items, $page, $statutCourant === 'imputee' ? 'consultation' : 'imputation'),
                $total . ' demande(s)'
            )
            . '</div></div>';
    }

    // ==================================================================
    // Creation
    // ==================================================================

    /**
     * @param array<int, array{id:int, name:string, code:string}> $agences
     * @param array<int, string> $dossiersRecents
     */
    public static function creationPage(
        array $agences,
        array $dossiersRecents,
        string $numeroPropose,
        int $agenceUtilisateur
    ): string {
        $entete = Ui::pageHeader(
            'Nouvelle demande de décaissement',
            'La demande part en validation à la direction, puis en caisse pour paiement.',
            [
                'eyebrow' => 'Référence ' . $numeroPropose,
                'class' => 'rh-hero-white',
                'actions' => Ui::button('Retour aux demandes', ['href' => 'finance/fonds', 'variant' => 'ghost']),
            ]
        );

        $optionsAgences = [];
        foreach ($agences as $agence) {
            $optionsAgences[] = [
                'value' => (string) $agence['id'],
                'label' => $agence['name'] . ' (' . $agence['code'] . ')',
            ];
        }

        // La liste des dossiers recents alimente une saisie assistee : l'agent
        // tape librement, mais retrouve ce qui a deja servi.
        $suggestions = '<datalist id="fonds-dossiers-recents">';
        foreach ($dossiersRecents as $dossier) {
            $suggestions .= '<option value="' . View::e($dossier) . '"></option>';
        }
        $suggestions .= '</datalist>';

        $formulaire = '<form method="post" action="' . View::url('finance/fonds/enregistrer') . '">'
            . Csrf::field()
            . self::choixCadre()
            . '<div class="rh-form-grid" style="gap:1.25rem;">'
            . Form::select('agence_id', $optionsAgences, (string) $agenceUtilisateur, [
                'label' => 'Agence de rattachement',
                'required' => true,
            ])
            . Form::input('dossier_num', [
                'label' => 'N° de dossier / tracking',
                'required' => true,
                'id' => 'fonds-dossier',
                'list' => 'fonds-dossiers-recents',
                'placeholder' => 'S-IM00379/26, ou le numéro de suivi du colis',
                'hint' => 'Saisissez le dossier de transit, ou choisissez dans l\'historique.',
                'fieldClass' => 'fonds-champ-dossier',
            ])
            . Form::input('montant', [
                'label' => 'Montant sollicité',
                'type' => 'number',
                'step' => '100',
                'min' => '100',
                'required' => true,
                'placeholder' => '50000',
            ])
            . Form::select(
                'devise',
                [
                    ['value' => 'XOF', 'label' => 'FCFA (XOF)'],
                    ['value' => 'EUR', 'label' => 'Euros (EUR)'],
                    ['value' => 'USD', 'label' => 'Dollars (USD)'],
                ],
                'XOF',
                ['label' => 'Devise']
            )
            . '</div>'
            . Form::textarea('motif', [
                'label' => 'Motif précis de la demande',
                'rows' => 3,
                'required' => true,
                'placeholder' => 'Transport pour la visite / VJ Liquor / 3 conteneurs 40 pieds',
                'hint' => 'Indiquez l\'objet exact des fonds : c\'est sur cette ligne que la direction décide.',
            ])
            . $suggestions
            . '<div class="fonds-circuit">'
            . self::iconeInfo()
            . '<p><strong>Circuit de validation :</strong> la demande part à la direction '
            . '(assistante DG ou DG). Une fois validée, la caisse décaisse et édite le '
            . 'bon de sortie de caisse.</p>'
            . '</div>'
            . '<div class="fonds-actions-formulaire">'
            . Ui::button('Annuler', ['href' => 'finance/fonds', 'variant' => 'secondary'])
            . Ui::button('Soumettre la demande', ['variant' => 'accent', 'type' => 'submit'])
            . '</div>'
            . '</form>';

        return '<div class="finea-shell"><div class="finea-container" style="max-width:900px;">'
            . $entete
            . Ui::section('Formulaire de demande', $formulaire)
            . self::styles()
            . self::scriptCadre()
            . '</div></div>';
    }

    /**
     * Le cadre commande la suite du formulaire : une depense de fonctionnement
     * n'est rattachee a aucun dossier de transit.
     */
    private static function choixCadre(): string
    {
        $options = [
            [
                'valeur' => 'traitement_dossier',
                'titre' => 'Traitement de dossier',
                'texte' => 'Dépense rattachée à un dossier de transit : visite douane, tirage BL, manutention, transport.',
            ],
            [
                'valeur' => 'fonctionnement',
                'titre' => 'Fonctionnement général',
                'texte' => 'Dépense courante de l\'agence : carburant, fournitures, petit matériel, assurances.',
            ],
        ];

        $html = '<fieldset class="fonds-cadre"><legend>Cadre de la dépense</legend><div class="fonds-cadre-choix">';
        foreach ($options as $index => $option) {
            $html .= '<label class="fonds-cadre-option' . ($index === 0 ? ' is-active' : '') . '">'
                . '<input type="radio" name="cadre" value="' . View::e($option['valeur']) . '"'
                . ($index === 0 ? ' checked' : '') . ' data-fonds-cadre>'
                . '<span><strong>' . View::e($option['titre']) . '</strong>'
                . '<small>' . View::e($option['texte']) . '</small></span>'
                . '</label>';
        }

        return $html . '</div></fieldset>';
    }

    // ==================================================================
    // Briques communes
    // ==================================================================

    /**
     * @param array<string, mixed> $filters
     * @param array<int, array{id:int, name:string, code:string}> $agences
     */
    private static function filtres(
        string $action,
        array $filters,
        array $agences,
        string $statutCourant = ''
    ): string {
        $optionsAgences = [['value' => '', 'label' => 'Toutes les agences']];
        foreach ($agences as $agence) {
            $optionsAgences[] = [
                'value' => (string) $agence['id'],
                'label' => $agence['name'] . ' (' . $agence['code'] . ')',
            ];
        }

        // L'onglet actif doit survivre au filtrage, sinon le formulaire ramene
        // l'utilisateur sur la file precedente.
        $etat = $statutCourant !== '' ? Form::hidden('statut', $statutCourant) : '';

        $champs = Form::select('agence_id', $optionsAgences, (string) ($filters['agence_id'] ?? ''), ['label' => 'Agence'])
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
            . Form::input('date_from', ['label' => 'Du', 'type' => 'date', 'value' => (string) ($filters['date_from'] ?? '')])
            . Form::input('date_to', ['label' => 'Au', 'type' => 'date', 'value' => (string) ($filters['date_to'] ?? '')])
            . Form::input('q', [
                'label' => 'Recherche',
                'value' => (string) ($filters['q'] ?? ''),
                'placeholder' => 'Numéro, dossier ou motif',
            ]);

        return Ui::section(
            'Filtres',
            '<form method="get" action="' . View::url($action) . '">'
                . $etat
                . '<div class="rh-form-grid" style="gap:1rem;">'
                . $champs
                . '<div class="finea-field" style="display:flex; gap:.5rem; align-items:end;">'
                . Ui::button('Filtrer', ['variant' => 'primary', 'type' => 'submit'])
                . Ui::button('Réinitialiser', ['href' => $action, 'variant' => 'secondary'])
                . '</div></div></form>'
        );
    }

    /**
     * @param array<int, DemandeFonds> $items
     * @param string $geste decaissement, imputation ou consultation
     */
    private static function table(array $items, int $page, string $geste): string
    {
        [$libelleAction, $variante] = match ($geste) {
            'decaissement' => ['Décaisser', 'accent'],
            'imputation' => ['Imputer', 'primary'],
            default => ['Consulter', 'secondary'],
        };

        $lignes = [];
        foreach (array_values($items) as $index => $item) {
            $lignes[] = [
                (string) (($page - 1) * self::PAR_PAGE + $index + 1),
                '<a href="' . View::url('finance/fonds/' . $item->id) . '"><strong>'
                    . View::e($item->numeroDemande) . '</strong></a>',
                View::e(self::date($geste === 'decaissement' ? $item->createdAt : ($item->dateDecaissement ?? $item->createdAt))),
                Ui::badge(
                    $item->cadre === 'traitement_dossier' ? 'Traitement de dossier' : 'Fonctionnement',
                    $item->cadre === 'traitement_dossier' ? 'info' : 'neutral'
                ),
                $item->dossierNum ? '<code>' . View::e($item->dossierNum) . '</code>' : '<small>—</small>',
                '<span title="' . View::e($item->motif) . '">' . View::e($item->motif) . '</span>',
                '<strong>' . ModuleTable::montant($item->montant, $item->devise) . '</strong>',
                View::e($item->demandeurNom ?? '—'),
                View::e($item->validateurNom ?? '—'),
                Ui::button($libelleAction, ['href' => 'finance/fonds/' . $item->id, 'variant' => $variante]),
            ];
        }

        return ModuleTable::render(
            [
                ['label' => 'N°', 'align' => 'center'],
                ['label' => 'Numéro'],
                ['label' => $geste === 'decaissement' ? 'Demandée le' : 'Décaissée le'],
                ['label' => 'Cadre', 'align' => 'center'],
                ['label' => 'Dossier'],
                ['label' => 'Motif'],
                ['label' => 'Montant', 'align' => 'right'],
                ['label' => 'Demandeur'],
                ['label' => 'Validateur'],
                ['label' => 'Action', 'align' => 'center'],
            ],
            $lignes,
            self::titreVide($geste),
            self::texteVide($geste)
        );
    }

    private static function titreVide(string $geste): string
    {
        return match ($geste) {
            'decaissement' => 'Rien à décaisser',
            'imputation' => 'Rien à justifier',
            default => 'Aucun dossier clôturé',
        };
    }

    private static function texteVide(string $geste): string
    {
        return match ($geste) {
            'decaissement' => 'Toutes les demandes validées ont déjà été payées par la caisse.',
            'imputation' => 'Tous les fonds décaissés ont été justifiés.',
            default => 'Aucune demande n\'a encore été imputée et clôturée.',
        };
    }

    private static function date(?string $valeur): string
    {
        if ($valeur === null || $valeur === '') {
            return '—';
        }

        $horodatage = strtotime($valeur);

        return $horodatage !== false ? date('d/m/Y', $horodatage) : '—';
    }

    private static function iconeInfo(): string
    {
        return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"'
            . ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . '<circle cx="12" cy="12" r="10"></circle>'
            . '<line x1="12" y1="16" x2="12" y2="12"></line>'
            . '<line x1="12" y1="8" x2="12.01" y2="8"></line></svg>';
    }

    /**
     * Le champ « dossier » n'a de sens que pour une dépense rattachée à un
     * dossier de transit. Il est masqué et rendu facultatif dans l'autre cas,
     * sans quoi le navigateur bloquerait la soumission sur un champ invisible.
     */
    private static function scriptCadre(): string
    {
        return '<script>'
            . '(function(){'
            . 'var choix=document.querySelectorAll("[data-fonds-cadre]");'
            . 'var champ=document.querySelector(".fonds-champ-dossier");'
            . 'var saisie=document.getElementById("fonds-dossier");'
            . 'if(!choix.length||!champ||!saisie)return;'
            . 'function appliquer(){'
            . 'var dossier=document.querySelector("[data-fonds-cadre]:checked").value==="traitement_dossier";'
            . 'champ.hidden=!dossier;'
            . 'if(dossier){saisie.setAttribute("required","required");}'
            . 'else{saisie.removeAttribute("required");saisie.value="";}'
            . 'choix.forEach(function(c){'
            . 'c.closest(".fonds-cadre-option").classList.toggle("is-active",c.checked);});'
            . '}'
            . 'choix.forEach(function(c){c.addEventListener("change",appliquer);});'
            . 'appliquer();'
            . '})();'
            . '</script>';
    }

    private static function styles(): string
    {
        return '<style>'
            . '.fonds-cadre{border:none;margin:0 0 1.5rem;padding:0;}'
            . '.fonds-cadre legend{font-weight:700;font-size:.9rem;color:#1e293b;padding:0 0 .5rem;}'
            . '.fonds-cadre-choix{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem;}'
            . '.fonds-cadre-option{display:flex;gap:10px;align-items:flex-start;padding:1rem;'
            . 'border:1px solid #cbd5e1;background:#f8fafc;border-radius:8px;cursor:pointer;}'
            . '.fonds-cadre-option.is-active{border:2px solid #2563eb;background:#eff6ff;}'
            . '.fonds-cadre-option small{display:block;color:#64748b;font-size:.8rem;margin-top:.2rem;line-height:1.4;}'
            . '.fonds-cadre-option:focus-within{outline:2px solid #2563eb;outline-offset:2px;}'
            . '.fonds-circuit{display:flex;gap:10px;align-items:flex-start;background:#f0fdf4;'
            . 'border:1px solid #bbf7d0;border-radius:8px;padding:1rem;margin:1.5rem 0;color:#166534;}'
            . '.fonds-circuit p{margin:0;font-size:.85rem;line-height:1.45;}'
            . '.fonds-circuit svg{flex-shrink:0;color:#16a34a;}'
            . '.fonds-actions-formulaire{display:flex;justify-content:flex-end;gap:1rem;'
            . 'align-items:center;border-top:1px solid #f1f5f9;padding-top:1.25rem;}'
            . '</style>';
    }
}
