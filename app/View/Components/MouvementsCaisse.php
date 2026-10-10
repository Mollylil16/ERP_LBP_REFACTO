<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\Csrf;
use App\Helpers\View;

/**
 * Finance > Mouvements de caisse : la journée d'une caisse, et son historique.
 *
 * L'écran reprend celui que la direction employait dans son ancien logiciel —
 * trois cartes du jour, trois cumuls, deux boutons de saisie, deux historiques.
 * L'ordre de lecture est conservé parce que c'est lui qui est connu ; seul
 * l'habillage change.
 *
 * Dans l'original, les trois cartes étaient des aplats cyan, rose et vert. Un
 * fond saturé ne laisse aucune place pour dire « attention » : le jour où le
 * solde passait sous zéro, la carte restait verte. Ici le fond est blanc, la
 * couleur ne sert plus qu'à signaler, et le chiffre de la veille est posé en
 * pied plutôt que noyé dans la masse colorée.
 *
 * Deux règles viennent du métier et se lisent partout dans ce fichier :
 *  - une variation nulle (« variation » à null) veut dire que la veille était à
 *    zéro. Aucun pourcentage n'est alors affiché : « -100 % » depuis rien ne
 *    renseigne sur rien et affole la lecture ;
 *  - une ligne de source « auto » remonte d'une facture, d'un appro ou d'une
 *    demande de fonds. Elle se corrige là où elle est née, pas ici : elle ne
 *    porte donc aucune action, et le dit.
 */
final class MouvementsCaisse
{
    /** Les cadres que la direction propose à l'encaissement, dans son vocabulaire. */
    public const CADRES_VERSEMENT = [
        'FACTURE_DOSSIER' => 'Règlements de factures Dossier',
        'FACTURE_COLIS' => 'Règlements de factures Colis',
        'AUTRE' => 'Autres versements',
    ];

    /** Les cadres du décaissement. */
    public const CADRES_RETRAIT = [
        'DOSSIER' => 'Traitement de dossier',
        'COLIS' => 'Traitement de colis',
        'FONCTIONNEMENT' => 'Fonctionnement',
    ];

    /**
     * Les modes de règlement déjà employés ailleurs dans Finance.
     *
     * La valeur postée est le libellé lui-même : c'est ce que la colonne
     * mode_paiement contient dans tout le reste du module, et inventer un code
     * ici obligerait à traduire dans les deux sens.
     */
    public const MODES = ['Espèces', 'Wave', 'Orange Money', 'MTN MoMo', 'Virement bancaire', 'Chèque'];

    /**
     * Les colonnes de chaque historique, dans l'ordre de la maquette.
     *
     * En-tête, cellules et ligne de total se construisent depuis cette seule
     * liste : une colonne ajoutée ne peut plus décaler le pied du tableau.
     *
     * @var array<string, array<int, array{0: string, 1: string}>>
     */
    private const COLONNES = [
        'versements' => [
            ['N°', 'n'], ['Date', 'date'], ['Cadre', 'cadre'], ['N° Dossier', 'dossier'],
            ['Libellé', 'libelle'], ['Référence', 'reference'], ['Montant', 'montant'],
            ['Déposant', 'tiers'], ['Caisse', 'caisse'], ['Caissier', 'caissier'],
        ],
        'retraits' => [
            ['N°', 'n'], ['Date', 'date'], ['Cadre', 'cadre'], ['N° Dossier', 'dossier'],
            ['Libellé', 'libelle'], ['Montant', 'montant'], ['Bénéficiaire', 'tiers'],
            ['Caisse', 'caisse'], ['Caissier', 'caissier'],
        ],
    ];

    /** Tracés des icônes. Aucun emoji dans l'interface : un emoji ne suit ni la police ni la couleur. */
    private const ICONES = [
        'entree' => '<path d="M12 3v12"></path><polyline points="7 10 12 15 17 10"></polyline><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>',
        'sortie' => '<path d="M12 15V3"></path><polyline points="7 8 12 3 17 8"></polyline><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>',
        'solde' => '<path d="M12 3v18"></path><path d="M5 7h14"></path><path d="M5 7 2 14h6z"></path><path d="M19 7l-3 7h6z"></path>',
        'hausse' => '<polyline points="3 17 9 11 13 15 21 7"></polyline><polyline points="15 7 21 7 21 13"></polyline>',
        'baisse' => '<polyline points="3 7 9 13 13 9 21 17"></polyline><polyline points="15 17 21 17 21 11"></polyline>',
        'stable' => '<line x1="5" y1="12" x2="19" y2="12"></line>',
        'plus' => '<line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line>',
        'moins' => '<line x1="5" y1="12" x2="19" y2="12"></line>',
        'auto' => '<path d="M3 12a9 9 0 0 1 15.36-6.36L21 8"></path><polyline points="21 3 21 9 15 9"></polyline>',
        'filtrer' => '<path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"></path>',
        'reinitialiser' => '<path d="M3 2v6h6"></path><path d="M3.51 15a9 9 0 1 0 2.13-9.36L3 8"></path>',
        'imprimer' => '<polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect>',
        'telecharger' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line>',
    ];

    // ------------------------------------------------------------------
    // L'écran
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $p */
    public static function page(array $p): string
    {
        $jour = (string) ($p['date'] ?? date('Y-m-d'));
        $peutSaisir = !empty($p['peutSaisir']);

        $html = Ui::pageHeader(
            'Mouvements de caisse',
            "Ce qui est entré et sorti des caisses sur la journée, et le cumul depuis l'origine. "
            . "Les lignes reprises des factures et des demandes de fonds se corrigent sur leur pièce d'origine.",
            ['eyebrow' => 'Finance', 'class' => 'rh-hero-white']
        );

        $html .= self::filtres($p)
            . self::cartesDuJour($p, $jour)
            . self::cumuls($p)
            . self::operations($p);

        $html .= self::historique('versements', $p, $jour, $peutSaisir)
            . self::historique('retraits', $p, $jour, $peutSaisir);

        return self::styles()
            . '<div class="finea-shell lbp-mvt"><div class="finea-container">' . $html . '</div></div>';
    }

    /**
     * La barre de filtres, posée avant les cartes.
     *
     * Les cartes parlent d'une journée et d'une caisse : le sélecteur qui les
     * désigne doit être lu avant elles, sinon le chiffre s'affiche sans qu'on
     * sache de quoi il est le chiffre.
     *
     * @param array<string, mixed> $p
     */
    private static function filtres(array $p): string
    {
        $f = is_array($p['filtres'] ?? null) ? $p['filtres'] : [];
        $action = View::e(View::url('finance/mouvements-caisse'));

        $caisses = [['value' => '', 'label' => 'Toutes les caisses']];
        foreach (self::liste($p, 'caisses') as $c) {
            $caisses[] = ['value' => (string) ($c['id'] ?? ''), 'label' => self::nomCaisse($c)];
        }

        $agences = [['value' => '', 'label' => 'Toutes les agences']];
        foreach (self::liste($p, 'agences') as $a) {
            $agences[] = ['value' => (string) ($a['id'] ?? ''), 'label' => (string) ($a['name'] ?? '')];
        }

        return '<form method="get" action="' . $action . '" class="rh-personnel-filters lbp-mvt-filtres">'
            . '<div class="lbp-mvt-filtres-grille">'
            . Form::input('date', ['label' => 'Journée', 'type' => 'date', 'value' => (string) ($f['date'] ?? $p['date'] ?? ''), 'id' => 'mvt-date'])
            . Form::select('caisse_id', $caisses, (string) ($f['caisse_id'] ?? '' ?: ''), ['label' => 'Caisse', 'id' => 'mvt-caisse'])
            . Form::select('agence_id', $agences, (string) ($f['agence_id'] ?? '' ?: ''), ['label' => 'Agence', 'id' => 'mvt-agence'])
            . Form::input('q', [
                'label' => 'Recherche dans les historiques',
                'value' => (string) ($f['q'] ?? ''),
                'id' => 'mvt-q',
                'placeholder' => 'Libellé, référence, nom, n° de dossier',
            ])
            . '</div>'
            . '<div class="rh-personnel-filter-actions">'
            . '<button type="submit" class="rh-filter-btn rh-filter-btn--primary">' . self::icone('filtrer') . 'Filtrer</button>'
            . '<a href="' . $action . '" class="rh-filter-btn rh-filter-btn--reset">' . self::icone('reinitialiser') . 'Réinitialiser</a>'
            . '</div></form>';
    }

    // ------------------------------------------------------------------
    // Les trois cartes du jour
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $p */
    private static function cartesDuJour(array $p, string $jour): string
    {
        $k = is_array($p['kpis'] ?? null) ? $p['kpis'] : [];
        $entrees = is_array($k['entrees'] ?? null) ? $k['entrees'] : [];
        $sorties = is_array($k['sorties'] ?? null) ? $k['sorties'] : [];
        $solde = is_array($k['solde'] ?? null) ? $k['solde'] : [];

        // Le solde n'a pas de ventilation Dossiers / Colis / Autres : il est une
        // différence. Montrer ses deux termes apprend plus qu'une colonne vide.
        $termesSolde = [
            ['Entrées', (float) ($entrees['total'] ?? 0), (float) ($entrees['total_eur'] ?? 0)],
            ['Sorties', (float) ($sorties['total'] ?? 0), (float) ($sorties['total_eur'] ?? 0)],
        ];

        return '<div class="lbp-mvt-titre-groupe">'
            . '<span>' . View::e('Journée du ' . self::date($jour)) . '</span>'
            . '<span class="lbp-mvt-titre-note">' . View::e('Comparée à la veille, pour la caisse et l\'agence sélectionnées.') . '</span>'
            . '</div>'
            . '<div class="lbp-mvt-cartes">'
            . self::carte('Entrées caisse', 'entree', 'entrees', $entrees, self::ventilation($entrees))
            . self::carte('Sorties caisse', 'sortie', 'sorties', $sorties, self::ventilation($sorties))
            . self::carte('Solde caisse', 'solde', 'solde', $solde, $termesSolde, true)
            . '</div>';
    }

    /**
     * @param array<string, mixed> $kpi
     * @return array<int, array{0: string, 1: float, 2: float}>
     */
    private static function ventilation(array $kpi): array
    {
        return [
            ['Dossiers', (float) ($kpi['dossiers'] ?? 0), (float) ($kpi['dossiers_eur'] ?? 0)],
            ['Colis', (float) ($kpi['colis'] ?? 0), (float) ($kpi['colis_eur'] ?? 0)],
            ['Autres', (float) ($kpi['autres'] ?? 0), (float) ($kpi['autres_eur'] ?? 0)],
        ];
    }

    /**
     * Cette carte a-t-elle quelque chose à dire en euros ?
     *
     * La quasi-totalité des agences encaisse en francs : un « 0,00 EUR »
     * imprimé sous chaque carte serait un bruit permanent qu'on finirait par
     * ne plus lire — y compris le jour où il cesse d'être nul. La ligne
     * n'apparaît donc que lorsqu'elle porte un chiffre.
     *
     * Les clés en euros arrivent du service : tant qu'elles manquent, tout
     * vaut zéro et rien ne s'affiche, plutôt que de tomber.
     *
     * @param array<string, mixed> $kpi
     */
    private static function enEuros(array $kpi): bool
    {
        return self::nonNul($kpi['total_eur'] ?? null) || self::nonNul($kpi['veille_eur'] ?? null);
    }

    /** Un montant est tenu pour nul sous le centime : l'euro ne descend pas plus bas. */
    private static function nonNul(mixed $valeur): bool
    {
        return $valeur !== null && abs((float) $valeur) >= 0.005;
    }

    /**
     * Une carte du jour.
     *
     * @param array<string, mixed> $kpi
     * @param array<int, array{0: string, 1: float, 2: float}> $detail
     */
    private static function carte(string $libelle, string $icone, string $ton, array $kpi, array $detail, bool $signe = false): string
    {
        $total = (float) ($kpi['total'] ?? 0);
        $euros = self::enEuros($kpi);

        $cellules = '';
        foreach ($detail as [$nom, $valeur, $valeurEur]) {
            /*
             * Dans une carte qui parle euros, un « 0,00 EUR » sur l'une des
             * trois colonnes est un renseignement, pas du bruit : il dit que
             * rien n'est entré par cette porte-là. Les trois valeurs
             * apparaissent donc ensemble, ou pas du tout.
             */
            $cellules .= '<div><span class="lbp-mvt-detail-nom">' . View::e($nom) . '</span>'
                . '<b>' . View::e(self::montant($valeur)) . '</b>'
                . ($euros ? '<span class="lbp-mvt-detail-eur">' . View::e(self::montant($valeurEur, 2) . ' EUR') . '</span>' : '')
                . '</div>';
        }

        // Seul le solde change de couleur, et seulement s'il est négatif : une
        // caisse dans le rouge est un fait, pas une nuance de décoration.
        $classeMontant = 'lbp-mvt-carte-montant' . ($signe && $total < 0 ? ' is-negatif' : '');

        return '<article class="lbp-mvt-carte lbp-mvt-carte--' . $ton . '">'
            . '<header><span class="lbp-mvt-carte-icone">' . self::icone($icone, 18) . '</span>'
            . '<h3 class="lbp-mvt-carte-libelle">' . View::e($libelle) . '</h3></header>'
            . '<p class="' . $classeMontant . '">' . View::e(self::montant($total)) . '<span>FCFA</span></p>'
            . ($euros ? self::ligneEuros($kpi, $signe) : '')
            . '<div class="lbp-mvt-carte-detail lbp-mvt-carte-detail--' . count($detail) . '">' . $cellules . '</div>'
            . '<footer class="lbp-mvt-carte-pied">'
            . '<span class="lbp-mvt-carte-veille">' . View::e('Hier : ' . self::montant((float) ($kpi['veille'] ?? 0)) . ' FCFA') . '</span>'
            . self::variation($kpi['variation'] ?? null)
            . '</footer></article>';
    }

    /**
     * Le second compteur de la carte, en euros.
     *
     * Les deux monnaies ne se convertissent pas et ne s'additionnent pas :
     * elles se posent l'une sous l'autre. L'euro est écrit plus petit que le
     * franc non parce qu'il compterait moins, mais parce que l'agence qui le
     * lit sait déjà laquelle des deux la concerne — et que l'inverse,
     * deux chiffres de même taille, obligerait à vérifier l'unité à chaque
     * coup d'oeil.
     *
     * @param array<string, mixed> $kpi
     */
    private static function ligneEuros(array $kpi, bool $signe): string
    {
        $total = (float) ($kpi['total_eur'] ?? 0);
        $veille = (float) ($kpi['veille_eur'] ?? 0);

        // Une clé absente n'est pas une veille à zéro : tant que le service ne
        // l'envoie pas, aucune phrase ne se prononce sur la comparaison.
        $variation = array_key_exists('variation_eur', $kpi)
            ? self::variation($kpi['variation_eur'])
            : '';

        $classe = 'lbp-mvt-carte-euros-montant' . ($signe && $total < 0 ? ' is-negatif' : '');

        return '<div class="lbp-mvt-carte-euros">'
            . '<span class="lbp-mvt-carte-euros-nom">En euros</span>'
            . '<strong class="' . $classe . '">' . View::e(self::montant($total, 2) . ' EUR') . '</strong>'
            . '<span class="lbp-mvt-carte-euros-veille">' . View::e('hier ' . self::montant($veille, 2) . ' EUR') . '</span>'
            . $variation
            . '</div>';
    }

    /**
     * La variation par rapport à la veille.
     *
     * À null, la veille était à zéro : aucun pourcentage n'a de sens et aucun
     * n'est écrit. La phrase qui remplace la pastille dit pourquoi, sinon
     * l'absence passerait pour un bug d'affichage.
     *
     * La pastille ne porte pas de couleur de jugement. Une hausse des sorties
     * n'est pas une mauvaise nouvelle en soi, et c'est précisément ce verdict
     * automatique qui rendait l'écran d'origine illisible dès qu'un écart
     * apparaissait. La flèche et le signe disent le sens ; le lecteur conclut.
     */
    private static function variation(mixed $variation): string
    {
        if ($variation === null) {
            return '<span class="lbp-mvt-sansvar">' . View::e('Rien enregistré hier : pas de comparaison') . '</span>';
        }

        $valeur = (float) $variation;

        if (abs($valeur) < 0.05) {
            return '<span class="lbp-mvt-var">' . self::icone('stable', 13) . View::e('Au niveau d\'hier') . '</span>';
        }

        $sens = $valeur > 0 ? 'hausse' : 'baisse';
        // Le moins typographique (U+2212) a la largeur des chiffres : la colonne
        // des pourcentages reste alignée d'une carte à l'autre.
        $texte = ($valeur > 0 ? '+' : "\u{2212}") . number_format(abs($valeur), 1, ',', ' ') . ' %';

        return '<span class="lbp-mvt-var is-' . $sens . '">' . self::icone($sens, 13) . View::e($texte) . '</span>';
    }

    // ------------------------------------------------------------------
    // Les trois cumuls
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $p */
    private static function cumuls(array $p): string
    {
        $c = is_array($p['cumuls'] ?? null) ? $p['cumuls'] : [];
        $solde = (float) ($c['solde'] ?? 0);

        return '<div class="lbp-mvt-titre-groupe">'
            . '<span>Depuis l\'origine</span>'
            . '<span class="lbp-mvt-titre-note">' . View::e('Toutes journées confondues, hors filtre de date.') . '</span>'
            . '</div>'
            . '<div class="lbp-mvt-cumuls">'
            . self::cumul('Total versements', (float) ($c['versements'] ?? 0), false, $c['versements_eur'] ?? null)
            . self::cumul('Total retraits', (float) ($c['retraits'] ?? 0), false, $c['retraits_eur'] ?? null)
            . self::cumul('Solde', $solde, $solde < 0, $c['solde_eur'] ?? null)
            . '</div>';
    }

    /**
     * Un cumul, et son second compteur en euros s'il porte un chiffre.
     *
     * Même règle que sur les cartes du jour : une agence qui n'a jamais vu un
     * euro n'a pas à lire trois « 0,00 EUR » en permanence.
     */
    private static function cumul(string $libelle, float $valeur, bool $negatif = false, mixed $valeurEur = null): string
    {
        $euros = self::nonNul($valeurEur)
            ? '<span class="lbp-mvt-cumul-eur">' . View::e(self::montant((float) $valeurEur, 2) . ' EUR') . '</span>'
            : '';

        return '<div class="lbp-mvt-cumul' . ($negatif ? ' is-negatif' : '') . '">'
            . '<span>' . View::e($libelle) . '</span>'
            . '<strong>' . View::e(self::montant($valeur) . ' FCFA') . '</strong>'
            . $euros
            . '</div>';
    }

    // ------------------------------------------------------------------
    // Les deux fenêtres de saisie
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $p */
    private static function operations(array $p): string
    {
        if (empty($p['peutSaisir'])) {
            // Taire la bande entière laisserait croire que l'écran est en panne.
            // Mieux vaut dire qui saisit, et où s'adresser.
            return '<section class="finea-section-card lbp-mvt-operations lbp-mvt-operations--lecture">'
                . '<div class="lbp-mvt-operations-texte"><strong>Écran en lecture seule</strong>'
                . '<span>' . View::e('La saisie des versements et des retraits appartient au caissier de l\'agence. Les lignes ci-dessous restent consultables et exportables.') . '</span>'
                . '</div></section>';
        }

        return '<section class="finea-section-card lbp-mvt-operations">'
            . '<div class="lbp-mvt-operations-texte"><strong>Enregistrer une opération</strong>'
            . '<span>' . View::e('Le mouvement est daté, rattaché à une caisse et à un cadre : c\'est ce rattachement qui alimente les trois cartes du jour.') . '</span>'
            . '</div>'
            . '<div class="lbp-mvt-operations-boutons">'
            . Modal::render(
                'mvt-versement',
                'Effectuer un versement',
                self::formulaireVersement($p),
                self::icone('plus') . 'Effectuer un versement (encaissement)',
                ['variant' => 'primary', 'eyebrow' => 'Encaissement']
            )
            . Modal::render(
                'mvt-retrait',
                'Effectuer un retrait',
                self::formulaireRetrait($p),
                self::icone('moins') . 'Effectuer un retrait (décaissement)',
                ['variant' => 'secondary', 'eyebrow' => 'Décaissement']
            )
            . '</div></section>';
    }

    /** @param array<string, mixed> $p */
    private static function formulaireVersement(array $p): string
    {
        return '<form method="post" action="' . View::e(View::url('finance/mouvements-caisse/versement')) . '" class="lbp-mvt-formulaire">'
            . Csrf::field() . self::filtresCaches($p)
            . '<div class="lbp-mvt-champs">'
            . self::champDate('vers-date', $p)
            . Form::select('agence_id', self::optionsAgences($p), '', ['label' => 'Agence', 'id' => 'vers-agence', 'required' => true, 'hint' => 'La caisse de cette agence, celle du point de caisse du soir.'])
            . self::champCaisse($p, 'vers')
            . Form::select('cadre', self::optionsCadres(self::CADRES_VERSEMENT), '', ['label' => 'Cadre de l\'encaissement', 'id' => 'vers-cadre', 'required' => true])
            . self::champDossier('vers-dossier')
            . Form::input('tiers', ['label' => 'Nom du déposant', 'id' => 'vers-deposant', 'placeholder' => 'Qui remet l\'argent'])
            . Form::select('mode_reglement', self::optionsModes(), 'Espèces', ['label' => 'Mode de règlement', 'id' => 'vers-mode'])
            . Form::input('reference', ['label' => 'N° de reçu, pièce ou chèque', 'id' => 'vers-reference', 'placeholder' => 'Ex. REC-2026-0148'])
            . self::champsMontant('vers')
            . '</div>'
            . '<div class="lbp-mvt-champ-large">'
            /*
             * « libelle » et non « designation » : c'est la clé que le service
             * relit, et il la refuse vide. L'astérisque le dit avant l'envoi
             * plutôt qu'après — un refus serveur ramène ici une fenêtre fermée
             * et une saisie à refaire.
             */
            . Form::textarea('libelle', ['label' => 'Désignation', 'id' => 'vers-designation', 'rows' => 2, 'required' => true, 'placeholder' => 'D\'où vient cet argent : la ligne sera relue au comptage du soir'])
            . '</div>'
            . self::piedFormulaire('Enregistrer le versement')
            . '</form>';
    }

    /** @param array<string, mixed> $p */
    private static function formulaireRetrait(array $p): string
    {
        return '<form method="post" action="' . View::e(View::url('finance/mouvements-caisse/retrait')) . '" class="lbp-mvt-formulaire">'
            . Csrf::field() . self::filtresCaches($p)
            . '<div class="lbp-mvt-champs">'
            . self::champDate('retr-date', $p)
            . Form::select('agence_id', self::optionsAgences($p), '', ['label' => 'Agence', 'id' => 'retr-agence', 'required' => true, 'hint' => 'La caisse de cette agence, celle du point de caisse du soir.'])
            . self::champCaisse($p, 'retr')
            . Form::select('cadre', self::optionsCadres(self::CADRES_RETRAIT), '', ['label' => 'Cadre du décaissement', 'id' => 'retr-cadre', 'required' => true])
            . self::champDossier('retr-dossier')
            . Form::input('tiers', ['label' => 'Bénéficiaire', 'id' => 'retr-beneficiaire', 'placeholder' => 'Qui reçoit l\'argent'])
            . Form::select('mode_reglement', self::optionsModes(), 'Espèces', ['label' => 'Mode de règlement', 'id' => 'retr-mode'])
            . Form::input('reference', ['label' => 'N° de pièce', 'id' => 'retr-reference', 'placeholder' => 'Ex. BS-2026-0074'])
            . self::champsMontant('retr')
            . '</div>'
            . '<div class="lbp-mvt-champ-large">'
            . Form::textarea('libelle', ['label' => 'Désignation', 'id' => 'retr-designation', 'rows' => 2, 'required' => true, 'placeholder' => 'À quoi sert cette sortie : la ligne sera relue au comptage du soir'])
            . '</div>'
            . self::piedFormulaire('Enregistrer le retrait')
            . '</form>';
    }

    /**
     * La date du mouvement.
     *
     * Le nom du champ est « date_mouvement », celui que le service relit, et
     * non « date ». Le « max » reprend la règle du serveur — un tiroir ne
     * contient pas l'argent de demain — pour que le refus arrive sous le doigt
     * plutôt qu'après un aller-retour et un formulaire vidé.
     *
     * @param array<string, mixed> $p
     */
    private static function champDate(string $id, array $p): string
    {
        return Form::input('date_mouvement', [
            'label' => 'Date',
            'type' => 'date',
            'value' => (string) ($p['date'] ?? date('Y-m-d')),
            'id' => $id,
            'required' => true,
            'max' => date('Y-m-d'),
        ]);
    }

    /**
     * Le dossier auquel rattacher le mouvement.
     *
     * Facultatif : un versement de fonctionnement ne tient à aucun dossier. Mais
     * renseigné, il remplit la colonne « N° Dossier » des deux historiques —
     * et c'est par cette colonne qu'on retrouve, des mois plus tard, tout ce
     * qu'un dossier a coûté et rapporté. L'aide le dit, sinon le champ passe
     * pour une formalité qu'on saute.
     */
    /**
     * Le montant et sa monnaie, qui ne vont jamais l'un sans l'autre.
     *
     * Sans champ « devise », toute saisie manuelle partait en francs — y
     * compris à Paris. Les lignes reprises de LBP, elles, portaient bien leurs
     * euros : l'écran aurait donc affiché des historiques en euros et des
     * compteurs faux, précisément pour l'agence à qui l'on venait d'ajouter la
     * seconde monnaie.
     *
     * Le franc reste proposé en premier et par défaut : la quasi-totalité des
     * agences encaisse en francs, et le pas de saisie autorise désormais les
     * centimes, sans quoi aucun montant en euros n'aurait pu être frappé.
     */
    private static function champsMontant(string $prefixe): string
    {
        return Form::input('montant', [
            'label' => 'Montant',
            'type' => 'number',
            'id' => $prefixe . '-montant',
            'required' => true,
            'min' => '0.01',
            'step' => '0.01',
            'inputmode' => 'decimal',
            'hint' => 'Sans séparateur de milliers. Les centimes ne servent qu\'à l\'euro.',
        ])
        . Form::select('devise', [
            ['value' => 'XOF', 'label' => 'FCFA (franc CFA)'],
            ['value' => 'EUR', 'label' => 'EUR (euro)'],
        ], 'XOF', ['label' => 'Devise', 'id' => $prefixe . '-devise', 'required' => true]);
    }

    private static function champDossier(string $id): string
    {
        return Form::input('dossier_numero', [
            'label' => 'N° de dossier',
            'id' => $id,
            'placeholder' => 'Ex. DOS-2026-01488',
            'maxlength' => '60',
            'hint' => 'Facultatif. Renseigné, le mouvement se retrouve depuis le dossier.',
        ]);
    }

    /**
     * La journée et les filtres regardés, reportés dans tout ce qui écrit.
     *
     * Le contrôleur relit « f_date », « f_caisse_id », « f_agence_id » et
     * « f_q » pour ramener le caissier là où il était. Sans ces champs, chaque
     * enregistrement le renvoyait sur la journée du jour, toutes caisses
     * confondues — et il devait refiltrer entre deux saisies.
     *
     * @param array<string, mixed> $p
     */
    private static function filtresCaches(array $p): string
    {
        $f = is_array($p['filtres'] ?? null) ? $p['filtres'] : [];
        $html = '';

        foreach (['date', 'caisse_id', 'agence_id', 'q'] as $champ) {
            $valeur = trim((string) ($f[$champ] ?? ''));

            if ($valeur !== '' && $valeur !== '0') {
                $html .= '<input type="hidden" name="f_' . $champ . '" value="' . View::e($valeur) . '">';
            }
        }

        return $html;
    }

    private static function piedFormulaire(string $libelle): string
    {
        return '<div class="lbp-mvt-modale-pied">'
            /*
             * Aucun recalcul n'a lieu : l'écriture ne touche ni le solde de la
             * caisse ni l'état journalier de l'agence. Le promettre laissait
             * croire au caissier que son point du soir tiendrait compte de
             * cette ligne — il y trouvera l'écart à la place.
             */
            . '<span class="lbp-mvt-modale-note">' . View::e("La ligne entre dans la journée affichée. Le point de caisse du soir, lui, ne compte que les espèces.") . '</span>'
            . '<div class="lbp-mvt-modale-boutons">'
            . Ui::button('Annuler', ['variant' => 'secondary', 'type' => 'button', 'data-modal-close' => true])
            . Ui::button($libelle, ['variant' => 'primary', 'type' => 'submit'])
            . '</div></div>';
    }

    /**
     * @param array<string, mixed> $p
     * @return array<int, array{value: string, label: string}>
     */
    /**
     * Les agences ou ce compte a le droit d enregistrer.
     *
     * L agence est l unite de cet ecran depuis le 10/10/2026, comme partout
     * ailleurs dans le logiciel. La caisse nommee venait d un autre outil, ou
     * plusieurs tiroirs coexistaient dans une meme agence ; chez LBP il n y en
     * a qu un par agence, et l exiger rendait la saisie impossible.
     *
     * @param array<string, mixed> $p
     * @return array<int, array{value: string, label: string}>
     */
    private static function optionsAgences(array $p): array
    {
        $options = [['value' => '', 'label' => 'Choisir une agence']];

        foreach (self::liste($p, 'agences') as $agence) {
            $options[] = [
                'value' => (string) ($agence['id'] ?? ''),
                'label' => (string) ($agence['name'] ?? ''),
            ];
        }

        return $options;
    }

    /**
     * Le choix d une caisse nommee, quand il y en a.
     *
     * Rien ne s affiche tant qu aucune caisse n est declaree : un selecteur
     * vide et obligatoire bloquait la saisie dans les cinq agences, sans rien
     * dire de la raison.
     *
     * @param array<string, mixed> $p
     */
    private static function champCaisse(array $p, string $prefixe): string
    {
        if (self::liste($p, 'caisses') === []) {
            return '';
        }

        return Form::select('caisse_id', self::optionsCaisses($p), '', [
            'label' => 'Caisse (facultatif)',
            'id' => $prefixe . '-caisse',
            'hint' => "Laissez vide si l'agence n'en tient qu'une.",
        ]);
    }

    private static function optionsCaisses(array $p): array
    {
        $options = [['value' => '', 'label' => "Toute l'agence"]];

        foreach (self::liste($p, 'caisses') as $c) {
            // Le solde entre dans le libellé : avant un décaissement, le caissier
            // doit savoir ce que le tiroir contient sans quitter la fenêtre.
            $options[] = [
                'value' => (string) ($c['id'] ?? ''),
                'label' => self::nomCaisse($c) . ' — ' . self::montant((float) ($c['solde'] ?? 0)) . ' F',
            ];
        }

        return $options;
    }

    /**
     * @param array<string, string> $cadres
     * @return array<int, array{value: string, label: string}>
     */
    private static function optionsCadres(array $cadres): array
    {
        $options = [['value' => '', 'label' => 'Choisir un cadre']];

        foreach ($cadres as $code => $libelle) {
            $options[] = ['value' => $code, 'label' => $libelle];
        }

        return $options;
    }

    /** @return array<int, array{value: string, label: string}> */
    private static function optionsModes(): array
    {
        $options = [];

        foreach (self::MODES as $mode) {
            $options[] = ['value' => $mode, 'label' => $mode];
        }

        return $options;
    }

    // ------------------------------------------------------------------
    // Les deux historiques du jour
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $p */
    private static function historique(string $sens, array $p, string $jour, bool $peutSaisir): string
    {
        $lignes = self::liste($p, $sens);
        $colonnes = self::COLONNES[$sens];
        $titre = ($sens === 'versements' ? 'Versements du ' : 'Retraits du ') . self::date($jour);

        $totaux = self::totaux($lignes);

        // Le tiret sépare le compte du total, le point médian les monnaies
        // entre elles : avec le même signe partout, « 4 ligne(s) · 7 137 500
        // FCFA · 4 820,00 EUR » se lisait comme trois rubriques de même rang.
        $resume = count($lignes) === 0
            ? 'Aucune ligne'
            : count($lignes) . ' ligne(s) — ' . self::totauxEnTexte($totaux);

        $requete = self::requete($p);

        $entete = '<div class="lbp-mvt-entete">'
            . '<div><h2 class="finea-section-title">' . View::e($titre) . '</h2>'
            . '<span class="lbp-mvt-entete-resume">' . View::e($resume) . '</span></div>'
            . '<div class="lbp-mvt-entete-actions">'
            . Ui::button(self::icone('imprimer') . 'Exporter en PDF', [
                'href' => 'finance/mouvements-caisse/' . $sens . '/pdf' . $requete,
                'variant' => 'secondary', 'target' => '_blank',
            ])
            . Ui::button(self::icone('telecharger') . 'Exporter en Excel', [
                'href' => 'finance/mouvements-caisse/' . $sens . '/excel' . $requete,
                'variant' => 'secondary',
            ])
            . '</div></div>';

        if ($lignes === []) {
            $corps = Ui::emptyState(
                $sens === 'versements'
                    ? 'Aucun versement ce jour-là'
                    : 'Aucun retrait ce jour-là',
                'Changez de journée, de caisse, ou videz la recherche. Les règlements de factures apparaissent ici dès qu\'ils sont encaissés.'
            );

            return '<section class="finea-section-card lbp-mvt-section">' . $entete . $corps . '</section>';
        }

        return '<section class="finea-section-card lbp-mvt-section">' . $entete
            . '<div class="finea-table-wrap">'
            . '<table class="finea-table lbp-mvt-table">'
            . self::enTete($colonnes, $peutSaisir)
            . '<tbody>' . self::corps($sens, $lignes, $colonnes, $peutSaisir, $p) . '</tbody>'
            . self::pied($colonnes, $peutSaisir, $totaux)
            . '</table></div></section>';
    }

    /** @param array<int, array{0: string, 1: string}> $colonnes */
    private static function enTete(array $colonnes, bool $peutSaisir): string
    {
        $cellules = '';
        foreach ($colonnes as [$titre, $cle]) {
            $cellules .= '<th class="' . self::classeColonne($cle) . '">' . View::e($titre) . '</th>';
        }

        if ($peutSaisir) {
            $cellules .= '<th class="lbp-mvt-c-actions">Actions</th>';
        }

        return '<thead><tr>' . $cellules . '</tr></thead>';
    }

    /**
     * @param array<int, array<string, mixed>> $lignes
     * @param array<int, array{0: string, 1: string}> $colonnes
     * @param array<string, mixed> $p
     */
    private static function corps(string $sens, array $lignes, array $colonnes, bool $peutSaisir, array $p): string
    {
        $html = '';
        $n = 0;

        foreach ($lignes as $ligne) {
            $n++;
            $auto = (string) ($ligne['source'] ?? 'saisie') === 'auto';

            $cellules = '';
            foreach ($colonnes as [, $cle]) {
                $cellules .= '<td class="' . self::classeColonne($cle) . '">' . self::cellule($cle, $ligne, $n, $auto) . '</td>';
            }

            if ($peutSaisir) {
                $cellules .= '<td class="lbp-mvt-c-actions">' . self::actions($sens, $ligne, $auto, $p) . '</td>';
            }

            $html .= '<tr class="lbp-mvt-ligne' . ($auto ? ' is-auto' : '') . '">' . $cellules . '</tr>';
        }

        return $html;
    }

    /** @param array<string, mixed> $ligne */
    private static function cellule(string $cle, array $ligne, int $n, bool $auto): string
    {
        switch ($cle) {
            case 'n':
                return (string) $n;

            case 'date':
                return View::e(self::date($ligne['date'] ?? null));

            case 'cadre':
                // Le code brut ne sert que de filet : c'est le libellé que la
                // direction lit, et le code ne lui dirait rien.
                $libelleCadre = trim((string) ($ligne['cadre_libelle'] ?? ''));

                return View::e($libelleCadre !== '' ? $libelleCadre : (string) ($ligne['cadre'] ?? '—'));

            case 'libelle':
                /*
                 * L'indice d'origine vit sous le libellé plutôt que dans une
                 * colonne à lui : il est lu au moment où l'oeil cherche à
                 * comprendre la ligne, et il reste une phrase — un lecteur
                 * d'écran l'annonce, ce qu'une simple teinte ne ferait pas.
                 */
                return '<strong>' . View::e((string) ($ligne['libelle'] ?? '—')) . '</strong>'
                    . ($auto
                        ? '<span class="lbp-mvt-origine">' . self::icone('auto', 12) . 'Reprise automatique</span>'
                        : '');

            case 'montant':
                return View::e(self::montant((float) ($ligne['montant'] ?? 0))
                    . ' ' . self::devise($ligne['devise'] ?? null));

            case 'dossier':
                return self::valeur($ligne['dossier'] ?? null);

            case 'reference':
                return self::valeur($ligne['reference'] ?? null);

            default:
                return self::valeur($ligne[$cle] ?? null);
        }
    }

    /**
     * Les actions d'une ligne.
     *
     * Une ligne « auto » est le reflet d'une facture, d'un appro ou d'une
     * demande de fonds : la supprimer ici laisserait la pièce d'origine en
     * place et le solde faux. Elle ne porte donc aucun bouton, et la cellule
     * dit où aller.
     *
     * Le formulaire est écrit ici plutôt que repris de Ui::deleteForm : celui-ci
     * ne poste que son jeton, et le contrôleur a besoin des filtres pour
     * ramener le caissier sur la journée qu'il regardait.
     *
     * @param array<string, mixed> $ligne
     * @param array<string, mixed> $p
     */
    private static function actions(string $sens, array $ligne, bool $auto, array $p): string
    {
        if ($auto) {
            return '<span class="lbp-mvt-nonmodifiable">' . View::e('Se corrige sur la pièce d\'origine') . '</span>';
        }

        $id = (int) ($ligne['id'] ?? 0);

        $question = ($sens === 'versements' ? 'Supprimer ce versement' : 'Supprimer ce retrait')
            . ' de ' . self::montant((float) ($ligne['montant'] ?? 0))
            . ' FCFA ? La ligne quitte la journée et les cumuls ; elle reste au journal.';

        // L'apostrophe délimite la chaîne JS du onsubmit : elle est neutralisée
        // avant échappement, comme le fait Ui::deleteForm.
        $confirmation = View::e(str_replace("'", '’', $question));

        return '<form method="post" action="'
            . View::e(View::url('finance/mouvements-caisse/' . $id . '/supprimer'))
            . '" style="display:inline;" onsubmit="return confirm(\'' . $confirmation . '\');">'
            . Csrf::field() . self::filtresCaches($p)
            . Ui::button('Supprimer', ['type' => 'submit', 'variant' => 'danger', 'class' => 'lbp-mvt-supprimer'])
            . '</form>';
    }

    /**
     * @param array<int, array{0: string, 1: string}> $colonnes
     * @param array<string, float> $totaux
     */
    private static function pied(array $colonnes, bool $peutSaisir, array $totaux): string
    {
        $avant = 0;
        $apres = 0;
        $vu = false;

        foreach ($colonnes as [, $cle]) {
            if ($cle === 'montant') {
                $vu = true;
                continue;
            }
            $vu ? $apres++ : $avant++;
        }

        if ($peutSaisir) {
            $apres++;
        }

        return '<tfoot><tr class="lbp-mvt-total">'
            . '<td colspan="' . $avant . '">Total des lignes affichées</td>'
            // Le pied porte un total par devise presente : additionner un
            // euro et un franc ne donne un resultat juste dans aucune des deux.
            . '<td class="lbp-mvt-c-montant">' . View::e(self::totauxEnTexte($totaux)) . '</td>'
            . ($apres > 0 ? '<td colspan="' . $apres . '"></td>' : '')
            . '</tr></tfoot>';
    }

    private static function classeColonne(string $cle): string
    {
        return 'lbp-mvt-c-' . $cle;
    }

    // ------------------------------------------------------------------
    // Petits outils de présentation
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $p
     * @return array<int, array<string, mixed>>
     */
    private static function liste(array $p, string $cle): array
    {
        $valeur = $p[$cle] ?? [];

        if (!is_array($valeur)) {
            return [];
        }

        $propres = [];
        foreach ($valeur as $element) {
            if (is_array($element)) {
                $propres[] = $element;
            }
        }

        return $propres;
    }

    /** @param array<string, mixed> $caisse */
    private static function nomCaisse(array $caisse): string
    {
        $nom = trim((string) ($caisse['nom'] ?? ''));
        $agence = trim((string) ($caisse['agence'] ?? ''));

        if ($nom === '') {
            return $agence === '' ? 'Caisse' : $agence;
        }

        return $agence === '' || $agence === $nom ? $nom : $nom . ' · ' . $agence;
    }

    private static function montant(float $valeur, int $decimales = 0): string
    {
        $texte = number_format(abs($valeur), $decimales, ',', ' ');

        // Le moins typographique a la largeur d'un chiffre : les colonnes de
        // montants restent alignées même quand une ligne est négative.
        return $valeur < 0 ? "\u{2212}" . $texte : $texte;
    }

    /**
     * Un montant et sa monnaie.
     *
     * Le franc CFA n'a pas de subdivision en usage : lui écrire des centimes
     * allongerait chaque colonne de trois caractères pour deux zéros. L'euro,
     * lui, en a — et un écart d'un centime sur un règlement se discute.
     */
    private static function somme(float $valeur, mixed $devise = 'XOF'): string
    {
        $code = self::devise($devise);

        return self::montant($valeur, $code === 'FCFA' ? 0 : 2) . ' ' . $code;
    }

    /**
     * Les totaux d'une liste, monnaie par monnaie.
     *
     * Additionner des francs et des euros dans une seule case donnerait un
     * nombre qui ne veut rien dire — et il est tombé dans le pied du tableau
     * dès que les lignes de Paris sont arrivées. Chaque monnaie garde donc son
     * total, et l'ordre de la première apparition est conservé pour que le
     * pied ne change pas de forme d'une journée à l'autre.
     *
     * @param array<int, array<string, mixed>> $lignes
     * @return array<string, float>
     */
    private static function totaux(array $lignes): array
    {
        $totaux = [];

        foreach ($lignes as $ligne) {
            $code = self::devise($ligne['devise'] ?? null);
            $totaux[$code] = ($totaux[$code] ?? 0.0) + (float) ($ligne['montant'] ?? 0);
        }

        return $totaux;
    }

    /**
     * @param array<string, float> $totaux
     */
    private static function totauxEnTexte(array $totaux): string
    {
        if ($totaux === []) {
            return self::somme(0.0);
        }

        $morceaux = [];
        foreach ($totaux as $code => $valeur) {
            $morceaux[] = self::montant($valeur, $code === 'FCFA' ? 0 : 2) . ' ' . $code;
        }

        return implode(' · ', $morceaux);
    }

    /**
     * La devise, dans le mot que la maison emploie.
     *
     * La base stocke le code ISO « XOF », mais les cartes du jour, les cumuls et
     * la direction disent « FCFA ». Deux mots pour la même monnaie sur un même
     * écran font douter qu'il s'agisse de la même monnaie. Les autres devises,
     * elles, restent à leur code.
     */
    private static function devise(mixed $code): string
    {
        $texte = strtoupper(trim((string) ($code ?? '')));

        if ($texte === '' || $texte === 'XOF' || $texte === 'CFA') {
            return 'FCFA';
        }

        return $texte;
    }

    private static function valeur(mixed $valeur): string
    {
        $texte = trim((string) ($valeur ?? ''));

        return $texte === '' ? '<span class="lbp-mvt-vide">&mdash;</span>' : View::e($texte);
    }

    private static function date(mixed $valeur): string
    {
        $texte = trim((string) ($valeur ?? ''));

        if ($texte === '') {
            return '—';
        }

        $instant = strtotime($texte);

        return $instant === false ? $texte : date('d/m/Y', $instant);
    }

    /** @param array<string, mixed> $p */
    private static function requete(array $p): string
    {
        $f = is_array($p['filtres'] ?? null) ? $p['filtres'] : [];
        $params = [];

        foreach (['date', 'caisse_id', 'agence_id', 'q'] as $champ) {
            $valeur = trim((string) ($f[$champ] ?? ''));
            if ($valeur !== '' && $valeur !== '0') {
                $params[$champ] = $valeur;
            }
        }

        /*
         * Le séparateur est écrit « &amp; » et non « & » : cette chaîne finit
         * dans un attribut href, où un « & » nu est du balisage invalide que
         * chaque navigateur rattrape à sa façon. Le navigateur le redécode en
         * « & » avant d'appeler le serveur, qui reçoit bien ses paramètres.
         */
        return $params === [] ? '' : '?' . http_build_query($params, '', '&amp;');
    }

    private static function icone(string $nom, int $taille = 16): string
    {
        $trace = self::ICONES[$nom] ?? '';

        if ($trace === '') {
            return '';
        }

        return '<svg class="lbp-mvt-icone" viewBox="0 0 24 24" width="' . $taille . '" height="' . $taille . '"'
            . ' fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"'
            . ' aria-hidden="true" focusable="false">' . $trace . '</svg>';
    }

    // ------------------------------------------------------------------
    // Les deux documents que la journée produit
    //
    // Aucune bibliothèque PDF ni tableur n'est installée : le PDF est une page
    // qui s'imprime d'elle-même, l'Excel une table HTML que le tableur ouvre.
    // Les deux repartent du même tableau que l'écran, par les mêmes colonnes et
    // les mêmes valeurs — un document qui dirait autre chose que l'écran d'où
    // il sort ne tiendrait pas une minute devant une contestation.
    // ------------------------------------------------------------------

    /**
     * La journée, imprimable.
     *
     * @param array<string, mixed> $mouvements le contrat complet, déjà filtré
     * @param string $sens 'versements' ou 'retraits'
     */
    public static function exportPdf(array $mouvements, string $sens, string $editePar = ''): string
    {
        $sens = self::sens($sens);
        $lignes = self::liste($mouvements, $sens);
        $colonnes = self::COLONNES[$sens];

        $entetes = '';
        foreach ($colonnes as [$titre, $cle]) {
            $entetes .= '<th class="c-' . $cle . '">' . View::e($titre) . '</th>';
        }

        $corps = '';
        $n = 0;
        foreach ($lignes as $ligne) {
            $n++;
            $auto = self::estAuto($ligne);

            $cellules = '';
            foreach ($colonnes as [, $cle]) {
                $texte = self::texteCellule($cle, $ligne, $n);

                /*
                 * L'origine se lit sous le libellé, comme à l'écran. Sur un
                 * document qu'on oppose à quelqu'un, savoir qu'une ligne est
                 * le reflet d'une facture — et non une saisie de caisse —
                 * change ce qu'on peut en conclure.
                 */
                if ($cle === 'libelle' && $auto) {
                    $cellules .= '<td class="c-' . $cle . '">' . View::e($texte)
                        . '<span class="origine">Reprise automatique</span></td>';
                    continue;
                }

                $cellules .= '<td class="c-' . $cle . '">' . View::e($texte) . '</td>';
            }

            $corps .= '<tr>' . $cellules . '</tr>';
        }

        $totaux = self::totaux($lignes);
        $avant = self::colonnesAvantMontant($colonnes);
        $apres = count($colonnes) - $avant - 1;

        $pied = $lignes === [] ? '' : '<tfoot><tr>'
            . '<td colspan="' . $avant . '" class="total">Total des lignes</td>'
            . '<td class="c-montant total">' . View::e(self::totauxEnTexte($totaux)) . '</td>'
            . ($apres > 0 ? '<td colspan="' . $apres . '"></td>' : '')
            . '</tr></tfoot>';

        return '<!doctype html><html lang="fr"><head><meta charset="utf-8">'
            . '<title>' . View::e(self::titreDocument($sens, $mouvements)) . '</title>'
            . '<style>'
            . 'body{font-family:Arial,Helvetica,sans-serif;font-size:10px;color:#111;margin:14mm}'
            . 'h1{font-size:16px;margin:0 0 2px}'
            . '.sous{color:#555;margin:0 0 12px;font-size:10px;line-height:1.5}'
            . 'table{width:100%;border-collapse:collapse}'
            . 'th,td{border:1px solid #bbb;padding:4px 5px;text-align:left;vertical-align:top}'
            . 'th{background:#0f172a;color:#fff;font-size:9px;text-transform:uppercase;letter-spacing:.04em}'
            . '.c-montant{text-align:right;white-space:nowrap}'
            . '.c-n{text-align:right;width:22px}'
            . '.c-date,.c-dossier,.c-reference{white-space:nowrap}'
            . '.origine{display:block;margin-top:2px;color:#666;font-size:8px;font-style:italic}'
            . '.total{font-weight:bold;background:#eef1f5}'
            . 'tr:nth-child(even) td{background:#f6f7f9}'
            // Sans cela, un historique de deux pages perd ses en-têtes de
            // colonne dès la seconde, et plus personne ne sait ce qu'il lit.
            . '@media print{body{margin:8mm}thead{display:table-header-group}tfoot{display:table-row-group}}'
            . '</style></head><body>'
            . '<h1>' . View::e(self::titreDocument($sens, $mouvements)) . '</h1>'
            . '<p class="sous">' . View::e(self::sousTitreDocument($mouvements, $lignes, $totaux, $editePar)) . '</p>'
            . '<table><thead><tr>' . $entetes . '</tr></thead>'
            . '<tbody>' . $corps . '</tbody>' . $pied . '</table>'
            . ($lignes === [] ? '<p class="sous">Aucun mouvement sur cette sélection.</p>' : '')
            . '<script>window.onload=function(){window.print();};</script>'
            . '</body></html>';
    }

    /**
     * La journée, ouvrable au tableur.
     *
     * Deux colonnes s'ajoutent à celles de l'écran. La devise, parce qu'un
     * montant sans sa monnaie ne s'additionne pas — et que la colonne Montant
     * est ici un nombre, pas un texte, pour que les sommes du tableur
     * fonctionnent. L'origine, parce que celui qui retravaille le fichier doit
     * pouvoir écarter les lignes reprises avant de recouper quoi que ce soit.
     *
     * @param array<string, mixed> $mouvements le contrat complet, déjà filtré
     * @param string $sens 'versements' ou 'retraits'
     */
    public static function exportExcel(array $mouvements, string $sens): string
    {
        $sens = self::sens($sens);
        $lignes = self::liste($mouvements, $sens);
        $colonnes = self::COLONNES[$sens];

        $entetes = '';
        foreach ($colonnes as [$titre]) {
            $entetes .= '<th>' . View::e($titre) . '</th>';
        }
        $entetes .= '<th>Devise</th><th>Origine</th>';

        $corps = '';
        $n = 0;
        foreach ($lignes as $ligne) {
            $n++;

            $cellules = '';
            foreach ($colonnes as [, $cle]) {
                $cellules .= $cle === 'montant'
                    ? self::xNombre($ligne['montant'] ?? null)
                    : self::xTexte(self::texteCellule($cle, $ligne, $n, false));
            }

            $cellules .= self::xTexte(self::devise($ligne['devise'] ?? null))
                . self::xTexte(self::estAuto($ligne) ? 'Reprise automatique' : 'Saisie de caisse');

            $corps .= '<tr>' . $cellules . '</tr>';
        }

        return '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8">'
            . '<style>th{background:#0f172a;color:#fff}td,th{border:1px solid #999}</style></head><body>'
            . '<p>' . View::e(self::titreDocument($sens, $mouvements)) . '</p>'
            . '<p>' . View::e(self::portee($mouvements)) . '</p>'
            /*
             * La colonne Montant est un nombre pour que les sommes du tableur
             * fonctionnent — mais elle mêle deux monnaies, et un SUM posé
             * dessus sans regarder la colonne Devise additionnerait des francs
             * et des euros. Le total juste est rappelé ici, monnaie par monnaie.
             */
            . '<p>' . View::e(count($lignes) . ' ligne(s) — Total ' . self::totauxEnTexte(self::totaux($lignes))) . '</p>'
            . '<table><thead><tr>' . $entetes . '</tr></thead><tbody>' . $corps . '</tbody></table>'
            . '</body></html>';
    }

    // ------------------------------------------------------------------

    /** Un sens inconnu ne doit pas faire tomber l'export : il retombe sur les versements. */
    private static function sens(string $sens): string
    {
        return $sens === 'retraits' ? 'retraits' : 'versements';
    }

    /** @param array<string, mixed> $ligne */
    private static function estAuto(array $ligne): bool
    {
        return (string) ($ligne['source'] ?? 'saisie') === 'auto';
    }

    /**
     * La valeur d'une cellule, en texte nu.
     *
     * Les documents repartent d'ici, et l'écran de `cellule()` : une seule
     * table de correspondance entre les deux serait préférable, mais l'écran
     * rend du balisage et le tableur veut des nombres. Les deux suivent la
     * même liste `COLONNES`, ce qui suffit à ce qu'aucune colonne ne puisse
     * exister d'un côté sans l'autre.
     *
     * @param array<string, mixed> $ligne
     */
    private static function texteCellule(string $cle, array $ligne, int $n, bool $avecDevise = true): string
    {
        switch ($cle) {
            case 'n':
                return (string) $n;

            case 'date':
                return self::date($ligne['date'] ?? null);

            case 'cadre':
                $libelleCadre = trim((string) ($ligne['cadre_libelle'] ?? ''));

                return $libelleCadre !== '' ? $libelleCadre : trim((string) ($ligne['cadre'] ?? ''));

            case 'montant':
                $montant = (float) ($ligne['montant'] ?? 0);

                return $avecDevise
                    ? self::somme($montant, $ligne['devise'] ?? null)
                    : self::montant($montant, self::devise($ligne['devise'] ?? null) === 'FCFA' ? 0 : 2);

            default:
                return trim((string) ($ligne[$cle] ?? ''));
        }
    }

    /** @param array<int, array{0: string, 1: string}> $colonnes */
    private static function colonnesAvantMontant(array $colonnes): int
    {
        $avant = 0;

        foreach ($colonnes as [, $cle]) {
            if ($cle === 'montant') {
                break;
            }
            $avant++;
        }

        return $avant;
    }

    /** @param array<string, mixed> $mouvements */
    private static function titreDocument(string $sens, array $mouvements): string
    {
        $jour = self::date($mouvements['date'] ?? null);

        return ($sens === 'versements' ? 'Versements de caisse' : 'Retraits de caisse')
            . ' — journée du ' . $jour;
    }

    /**
     * Ce que valait la sélection au moment de l'édition.
     *
     * Un export sorti d'un écran filtré ne montre qu'une partie de la journée.
     * Sans ce rappel, le document se lit comme s'il était complet — et c'est
     * ainsi qu'on finit par opposer à quelqu'un un total qui n'en était pas un.
     *
     * @param array<string, mixed> $mouvements
     */
    private static function portee(array $mouvements): string
    {
        $f = is_array($mouvements['filtres'] ?? null) ? $mouvements['filtres'] : [];
        $morceaux = [];

        $caisseId = (string) ($f['caisse_id'] ?? '');
        if ($caisseId !== '' && $caisseId !== '0') {
            $morceaux[] = 'Caisse : ' . self::nomDepuisListe($mouvements, 'caisses', $caisseId);
        }

        $agenceId = (string) ($f['agence_id'] ?? '');
        if ($agenceId !== '' && $agenceId !== '0') {
            $morceaux[] = 'Agence : ' . self::nomDepuisListe($mouvements, 'agences', $agenceId);
        }

        $recherche = trim((string) ($f['q'] ?? ''));
        if ($recherche !== '') {
            $morceaux[] = 'Recherche : ' . $recherche;
        }

        return $morceaux === []
            ? 'Toutes les caisses, toutes les agences.'
            : implode(' · ', $morceaux);
    }

    /** @param array<string, mixed> $mouvements */
    private static function nomDepuisListe(array $mouvements, string $cle, string $id): string
    {
        foreach (self::liste($mouvements, $cle) as $element) {
            if ((string) ($element['id'] ?? '') !== $id) {
                continue;
            }

            if ($cle !== 'caisses') {
                return trim((string) ($element['name'] ?? ''));
            }

            // Entre parenthèses, et non derrière un point médian : le sous-titre
            // du document sépare déjà ses segments par ce signe, et « Caisse :
            // Caisse principale · Aéroport » se lisait comme deux rubriques.
            $nom = trim((string) ($element['nom'] ?? ''));
            $agence = trim((string) ($element['agence'] ?? ''));

            if ($nom === '') {
                return $agence === '' ? 'Caisse' : $agence;
            }

            return $agence === '' || $agence === $nom ? $nom : $nom . ' (' . $agence . ')';
        }

        // La caisse filtrée peut avoir été fermée depuis : mieux vaut son
        // numéro qu'un blanc qui laisserait croire à une absence de filtre.
        return 'n° ' . $id;
    }

    /**
     * @param array<string, mixed> $mouvements
     * @param array<int, array<string, mixed>> $lignes
     * @param array<string, float> $totaux
     */
    private static function sousTitreDocument(array $mouvements, array $lignes, array $totaux, string $editePar): string
    {
        /*
         * Deux niveaux de séparation, et pas un seul : le tiret cadratin
         * sépare les rubriques, le point médian ce qui vit à l'intérieur
         * d'une rubrique — les filtres entre eux, et les monnaies d'un total.
         * Avec un seul signe pour les deux, « Total 7 137 500 FCFA · 4 820,00
         * EUR · Édité le… » ne disait plus où s'arrêtait le total.
         */
        $edition = 'Édité le ' . date('d/m/Y à H:i');

        $rubriques = [
            self::portee($mouvements),
            count($lignes) . ' ligne(s)',
            'Total ' . self::totauxEnTexte($totaux),
            $editePar === '' ? $edition : $edition . ' par ' . $editePar,
        ];

        return implode(' — ', $rubriques);
    }

    /** Sans ce format, le tableur transforme « 483-20428520 » en date. */
    private static function xTexte(string $valeur): string
    {
        return '<td style="mso-number-format:\'\\@\'">' . View::e($valeur) . '</td>';
    }

    private static function xNombre(mixed $valeur): string
    {
        return $valeur === null ? '<td></td>' : '<td>' . number_format((float) $valeur, 2, '.', '') . '</td>';
    }

    // ------------------------------------------------------------------
    // Habillage
    // ------------------------------------------------------------------

    private static function styles(): string
    {
        return '<style>'
            . '.lbp-mvt{--mvt-encre:#0f172a;--mvt-trait:#e3e6ea;--mvt-gris:#5b6472;'
            . '--mvt-entree:#027a48;--mvt-sortie:#b54708;--mvt-alerte:#b42318}'

            /*
             * Le sous-titre de l'en-tête hérite d'un gris (#94a3b8) qui ne donne
             * que 4,0:1 sur le bleu du bandeau — sous le seuil AA de 4,5. La
             * règle partagée vit dans rh.css et sert d'autres écrans : elle est
             * corrigée ici, à la portée de cet écran, et signalée par ailleurs.
             */
            . '.lbp-mvt .finea-page-header p{color:#cbd5e1}'

            // Titres de groupe : petites capitales espacées, comme les libellés
            // de KPI du reste de Finance.
            . '.lbp-mvt-titre-groupe{display:flex;align-items:baseline;gap:12px;flex-wrap:wrap;margin:22px 0 10px}'
            . '.lbp-mvt-titre-groupe>span:first-child{font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--mvt-encre)}'
            . '.lbp-mvt-titre-note{font-size:12px;color:var(--mvt-gris)}'

            // Les trois cartes du jour. Fond blanc, bord fin, rayon 14 : la
            // couleur se réserve à l'icône, au signe de la variation et au
            // solde négatif.
            . '.lbp-mvt-cartes{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px}'
            . '.lbp-mvt-carte{display:flex;flex-direction:column;gap:12px;padding:16px 18px;background:#fff;'
            . 'border:1px solid var(--mvt-trait);border-radius:14px}'
            . '.lbp-mvt-carte header{display:flex;align-items:center;gap:9px}'
            . '.lbp-mvt-carte-icone{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;'
            . 'border-radius:9px;background:#f1f5f9;color:var(--mvt-encre);flex-shrink:0}'
            . '.lbp-mvt-carte--entrees .lbp-mvt-carte-icone{background:#ecfdf3;color:var(--mvt-entree)}'
            . '.lbp-mvt-carte--sorties .lbp-mvt-carte-icone{background:#fef6ee;color:var(--mvt-sortie)}'
            . '.lbp-mvt-carte-libelle{margin:0;font-size:11px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--mvt-gris)}'
            . '.lbp-mvt-carte-montant{margin:0;font-size:30px;font-weight:700;line-height:1.05;letter-spacing:-.02em;'
            . 'color:var(--mvt-encre);font-variant-numeric:tabular-nums;white-space:nowrap}'
            . '.lbp-mvt-carte-montant span{margin-left:6px;font-size:12px;font-weight:700;letter-spacing:.04em;color:var(--mvt-gris)}'
            . '.lbp-mvt-carte-montant.is-negatif{color:var(--mvt-alerte)}'
            . '.lbp-mvt-carte-detail{display:grid;gap:1px;background:var(--mvt-trait);border:1px solid var(--mvt-trait);border-radius:10px;overflow:hidden}'
            . '.lbp-mvt-carte-detail--3{grid-template-columns:repeat(3,1fr)}'
            . '.lbp-mvt-carte-detail--2{grid-template-columns:repeat(2,1fr)}'
            . '.lbp-mvt-carte-detail>div{display:flex;flex-direction:column;gap:2px;padding:8px 10px;background:#fff;min-width:0}'
            . '.lbp-mvt-detail-nom{font-size:10px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--mvt-gris)}'
            . '.lbp-mvt-carte-detail b{font-size:14px;font-weight:700;color:var(--mvt-encre);font-variant-numeric:tabular-nums}'
            // La seconde monnaie, dans la cellule : plus petite et plus pâle que
            // le franc, pour qu'un coup d'oeil ne confonde jamais les deux.
            . '.lbp-mvt-detail-eur{font-size:11px;color:var(--mvt-gris);font-variant-numeric:tabular-nums;white-space:nowrap}'

            /*
             * Le second compteur de la carte. Il se pose sous le franc, séparé
             * par un trait : les deux monnaies ne s'additionnent pas et ne se
             * convertissent pas, la frontière doit se voir.
             */
            . '.lbp-mvt-carte-euros{display:flex;align-items:baseline;gap:8px;flex-wrap:wrap;'
            . 'margin-top:-4px;padding-top:9px;border-top:1px dashed var(--mvt-trait)}'
            . '.lbp-mvt-carte-euros-nom{font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--mvt-gris)}'
            . '.lbp-mvt-carte-euros-montant{font-size:17px;font-weight:700;color:var(--mvt-encre);'
            . 'font-variant-numeric:tabular-nums;letter-spacing:-.01em}'
            . '.lbp-mvt-carte-euros-montant.is-negatif{color:var(--mvt-alerte)}'
            . '.lbp-mvt-carte-euros-veille{font-size:11px;color:var(--mvt-gris);font-variant-numeric:tabular-nums}'
            . '.lbp-mvt-carte-euros .lbp-mvt-var,.lbp-mvt-carte-euros .lbp-mvt-sansvar{font-size:11px}'
            . '.lbp-mvt-carte-pied{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;'
            . 'margin-top:auto;padding-top:10px;border-top:1px solid var(--mvt-trait)}'
            . '.lbp-mvt-carte-veille{font-size:12px;color:var(--mvt-gris);font-variant-numeric:tabular-nums}'

            // La pastille de variation : la flèche et le signe portent le sens,
            // pas un fond coloré qui décrète si la journée est bonne.
            . '.lbp-mvt-var{display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border:1px solid var(--mvt-trait);'
            . 'border-radius:999px;background:#f8fafc;font-size:12px;font-weight:700;color:var(--mvt-encre);'
            . 'font-variant-numeric:tabular-nums;white-space:nowrap}'
            . '.lbp-mvt-var .lbp-mvt-icone{color:var(--mvt-gris)}'
            . '.lbp-mvt-sansvar{font-size:11px;color:var(--mvt-gris);font-style:italic}'

            // Les cumuls : plus petits que les cartes du jour, parce qu'ils se
            // consultent et ne se surveillent pas.
            . '.lbp-mvt-cumuls{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px}'
            . '.lbp-mvt-cumul{display:flex;flex-direction:column;gap:4px;padding:12px 16px;background:#fff;'
            . 'border:1px solid var(--mvt-trait);border-radius:14px}'
            . '.lbp-mvt-cumul span{font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--mvt-gris)}'
            . '.lbp-mvt-cumul strong{font-size:19px;font-weight:700;color:var(--mvt-encre);font-variant-numeric:tabular-nums}'
            . '.lbp-mvt-cumul.is-negatif strong{color:var(--mvt-alerte)}'
            . '.lbp-mvt-cumul-eur{font-size:12px;font-weight:700;color:var(--mvt-gris);font-variant-numeric:tabular-nums;'
            . 'padding-top:5px;border-top:1px dashed var(--mvt-trait)}'

            // La bande des deux opérations.
            . '.lbp-mvt-operations{display:flex;align-items:center;justify-content:space-between;gap:18px;flex-wrap:wrap;margin:18px 0}'
            . '.lbp-mvt-operations-texte{display:flex;flex-direction:column;gap:3px;min-width:0;flex:1 1 280px}'
            . '.lbp-mvt-operations-texte strong{font-size:15px;color:var(--mvt-encre)}'
            . '.lbp-mvt-operations-texte span{font-size:12px;color:var(--mvt-gris);max-width:68ch}'
            . '.lbp-mvt-operations-boutons{display:flex;gap:10px;flex-wrap:wrap}'
            . '.lbp-mvt-operations .finea-action-btn{min-height:44px!important;padding:11px 18px!important}'
            . '.lbp-mvt-operations--lecture{border-style:dashed}'

            // Les fenêtres de saisie.
            . '.lbp-mvt-champs{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:4px 18px}'
            /*
             * Sans hauteur de libellé commune, un libellé sur deux lignes
             * (« N° de reçu, pièce ou chèque ») descendait son champ d'une
             * ligne : les champs d'une même rangée ne partageaient plus aucune
             * ligne de base, et le formulaire donnait l'impression d'avoir été
             * posé de travers. Le libellé occupe donc toujours deux lignes et
             * s'aligne par le bas, ce qui pose tous les champs à la même
             * hauteur sans recourir à subgrid.
             */
            . '.lbp-mvt-champs>.finea-field{display:flex;flex-direction:column;margin-bottom:14px}'
            // Le « gap » remplace l'espace avant l'astérisque, qu'un conteneur
            // flex avale : sans lui, le libellé se lisait « Date* ».
            . '.lbp-mvt-champs>.finea-field>label{display:flex;align-items:flex-end;gap:3px;min-height:2.6em;line-height:1.3}'
            . '.lbp-mvt-champs>.finea-field .finea-field-hint{margin-top:5px}'
            . '.lbp-mvt-champ-large{margin-top:4px}'
            . '.lbp-mvt-champ-large .finea-field{margin-bottom:0}'
            . '.lbp-mvt-modale-pied{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;'
            . 'margin-top:18px;padding-top:14px;border-top:1px solid var(--mvt-trait)}'
            . '.lbp-mvt-modale-note{font-size:12px;color:var(--mvt-gris);flex:1 1 200px}'
            . '.lbp-mvt-modale-boutons{display:flex;gap:10px;flex-wrap:wrap}'
            . '.lbp-mvt-modale-boutons .finea-action-btn{min-height:44px!important;padding:11px 18px!important}'

            // Les deux historiques.
            . '.lbp-mvt-section{margin-top:18px}'
            . '.lbp-mvt-entete{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:14px}'
            . '.lbp-mvt-entete .finea-section-title{margin:0 0 2px}'
            . '.lbp-mvt-entete-resume{font-size:12px;color:var(--mvt-gris);font-variant-numeric:tabular-nums}'
            . '.lbp-mvt-entete-actions{display:flex;gap:8px;flex-wrap:wrap}'
            /*
             * Onze colonnes débordaient de 227 px sur un écran de 1500 :
             * « Caissier » et « Actions » tombaient hors de vue alors que la
             * place existait. La cause est finance.css, qui impose
             * « padding: 14px 18px !important » à toutes les cellules de
             * Finance — 36 px de gouttière par colonne, soit 198 px rien que
             * pour ce tableau. Un !important ne se reprend qu'avec un
             * !important ; il reste borné à cet écran.
             */
            . '.lbp-mvt-table{width:100%}'
            . '.lbp-mvt-table th{white-space:nowrap;font-size:11px!important}'
            . '.lbp-mvt-table th,.lbp-mvt-table td{padding:10px 9px!important}'
            . '.lbp-mvt-table td{vertical-align:middle;font-size:12.5px}'
            . '.lbp-mvt-c-n{width:30px;text-align:right;color:var(--mvt-gris);font-variant-numeric:tabular-nums}'
            . '.lbp-mvt-c-date,.lbp-mvt-c-dossier,.lbp-mvt-c-reference{font-family:Consolas,"SF Mono",monospace;'
            . 'font-size:11.5px;font-variant-numeric:tabular-nums;white-space:nowrap}'
            . '.lbp-mvt-c-montant{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap;font-weight:700}'
            . 'th.lbp-mvt-c-montant{text-align:right}'
            . '.lbp-mvt-c-libelle{min-width:170px;max-width:280px}'
            . '.lbp-mvt-c-libelle strong{font-weight:650}'
            . '.lbp-mvt-vide{color:var(--mvt-gris)}'

            /*
             * Une ligne reprise d'ailleurs : un liseré gris très clair à gauche,
             * et la phrase sous le libellé. Pas de pastille criarde — ces lignes
             * sont la majorité d'une journée, les signaler fort reviendrait à
             * tout surligner.
             */
            . '.lbp-mvt-ligne.is-auto>td:first-child{box-shadow:inset 3px 0 0 #cbd5e1}'
            . '.lbp-mvt-origine{display:flex;align-items:center;gap:5px;margin-top:2px;font-size:11px;color:var(--mvt-gris)}'
            . '.lbp-mvt-nonmodifiable{font-size:11px;color:var(--mvt-gris)}'

            // Supprimer reste une action rare : contour rouge plutôt qu'aplat,
            // pour ne pas peindre la colonne en rouge sur toute sa hauteur.
            . '.lbp-mvt-table .lbp-mvt-supprimer{color:var(--mvt-alerte);background:#fff;border:1px solid #fecdca;'
            . 'font-size:11.5px!important;padding:5px 11px!important;min-height:32px!important}'
            . '.lbp-mvt-table .lbp-mvt-supprimer:hover{background:#fef3f2}'

            /*
             * Au doigt, une cible de 32 px se rate. La largeur de l'écran ne dit
             * pas comment on le touche — une tablette de 1024 px se manipule au
             * doigt elle aussi : c'est « hover: none » qui le dit, pas le
             * nombre de pixels. Les deux conditions sont gardées, la seconde
             * pour les navigateurs qui ne répondent pas à la première.
             */
            . '@media(hover:none),(max-width:720px){'
            . '.lbp-mvt-table .lbp-mvt-supprimer,.lbp-mvt-entete-actions .finea-action-btn'
            . '{min-height:44px!important}'
            . '}'

            . '.lbp-mvt-total>td{background:#f8fafc;border-top:1px solid var(--mvt-trait);border-bottom:0;'
            . 'font-weight:700;color:var(--mvt-encre);font-size:13px}'
            . '.lbp-mvt-total>td:first-child{text-transform:uppercase;letter-spacing:.05em;font-size:11px;color:var(--mvt-gris)}'

            . '.lbp-mvt-filtres-grille{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px}'
            . '.lbp-mvt-icone{flex-shrink:0}'

            // Le focus doit rester visible partout : c'est le seul repère d'une
            // navigation au clavier dans un écran qui compte autant de champs.
            . '.lbp-mvt a:focus-visible,.lbp-mvt button:focus-visible,.lbp-mvt input:focus-visible,'
            . '.lbp-mvt select:focus-visible,.lbp-mvt textarea:focus-visible,'
            . '.finea-modal button:focus-visible,.finea-modal input:focus-visible,'
            . '.finea-modal select:focus-visible,.finea-modal textarea:focus-visible'
            . '{outline:2px solid #1d2b57;outline-offset:2px}'

            /*
             * Onze colonnes ne tiendront jamais sur un téléphone : le tableau
             * défile dans son enveloppe, et seule la numérotation de ligne —
             * qui n'apprend rien — disparaît pour gagner la place utile.
             */
            . '@media(max-width:640px){'
            . '.lbp-mvt-table{min-width:940px}'
            . '.lbp-mvt-c-n{display:none}'
            /*
             * finea-ui coupe les mots « n'importe où » sous 640 px pour qu'un
             * numéro de facture ne pousse pas le tableau hors de sa carte. Ici
             * les codes ne débordent déjà plus — ils sont en nowrap — et la
             * règle s'appliquait aux libellés : « Règlements de factures
             * Dossier » descendait en colonne d'une lettre par ligne.
             */
            . '.lbp-mvt-table th,.lbp-mvt-table td{overflow-wrap:normal}'
            . '.lbp-mvt-c-cadre{min-width:118px}'
            . '.lbp-mvt-c-tiers{min-width:104px}'
            . '.lbp-mvt-c-caisse,.lbp-mvt-c-caissier{min-width:88px}'
            . '.lbp-mvt-carte-montant{font-size:26px}'
            . '.lbp-mvt-entete-actions{width:100%}'
            . '.lbp-mvt-entete-actions .finea-action-btn{flex:1 1 140px;min-height:44px!important}'
            . '.lbp-mvt-operations-boutons{width:100%}'
            . '.lbp-mvt-operations-boutons>*{flex:1 1 100%}'
            . '.lbp-mvt-operations .finea-action-btn{width:100%}'
            . '.lbp-mvt-table .lbp-mvt-supprimer{min-height:44px!important}'
            . '}'

            /*
             * La fenêtre arrive par le haut en 140 ms : ce court trajet dit d'où
             * elle sort et où le regard doit se poser. Elle est purement
             * explicative, donc entièrement supprimée pour qui demande moins de
             * mouvement — rien de l'écran n'en dépend.
             */
            . '@media(prefers-reduced-motion:no-preference){'
            . '#mvt-versement[open] .finea-modal-dialog,#mvt-retrait[open] .finea-modal-dialog'
            . '{animation:lbp-mvt-entree .14s ease-out}'
            . '@keyframes lbp-mvt-entree{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:none}}'
            . '}'
            . '</style>';
    }
}
