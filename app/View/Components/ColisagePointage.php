<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\Csrf;
use App\Helpers\View;
use App\Services\Colisage\PointageColisService;
use App\Services\Colisage\ReceptionColisRegles as Regles;
use App\Support\HeureAgence;

/**
 * Écrans du pointage des colis : réceptionner, suivre. Le départ se prépare
 * dans les dossiers d'envoi (ColisageEnvois), sans cases à cocher.
 *
 * À la réception, chaque colis reçoit l'un des trois choix — Reçu, Reçu
 * endommagé, Non reçu — enregistré tout de suite, sans rechargement : un
 * agent qui pointe 1 000 colis ne doit rien perdre s'il est interrompu. La
 * liste se filtre (en attente, reçus, endommagés, non reçus) et se cherche
 * par numéro sans quitter la page ; la douchette emprunte le même chemin.
 * Pensé pour un téléphone : à 360 px, chaque bouton fait au moins 44 px.
 *
 * Les heures sont celles de l'agence où le geste a eu lieu, avec celle de
 * l'autre agence entre parenthèses quand elles diffèrent (Paris, Abidjan).
 * Les messages renvoyés par le serveur sont insérés en texte, jamais en HTML.
 */
final class ColisagePointage
{
    /** @var array<string, array{0:string, 1:string}> */
    public const ETATS = [
        PointageColisService::ETAT_EN_ROUTE => ['En route', 'info'],
        PointageColisService::ETAT_EN_COURS => ['Réception en cours', 'warning'],
        PointageColisService::ETAT_MANQUANTS => ['Colis manquants', 'danger'],
        PointageColisService::ETAT_COMPLET => ['Complet', 'success'],
    ];

    /** @var array<string, array{0:string, 1:string}> */
    public const ETATS_COLIS = [
        'RECU' => ['Reçu', 'success'],
        'ENDOMMAGE' => ['Reçu endommagé', 'warning'],
        'NON_RECU' => ['Non reçu', 'danger'],
        'HORS_LISTE' => ['Reçu hors liste', 'warning'],
        'RETIRE' => ['Retiré', 'neutral'],
        'MANQUANT' => ['Manquant', 'danger'],
        'ATTENDU' => ['En attente', 'info'],
    ];

    private const ACTIONS = [
        'DEPART' => 'Marqué parti',
        'RECU' => 'Pointé reçu',
        'ENDOMMAGE' => 'Pointé reçu endommagé',
        'NON_RECU' => 'Déclaré non reçu',
        'ANNULE_RECU' => 'Remis en attente',
        'HORS_LISTE' => 'Reçu hors liste',
    ];

    private const SOURCES = [
        'CASE' => 'à l’écran',
        'TOUT' => 'tout cocher',
        'DOUCHETTE' => 'douchette',
        'REPRISE' => "reprise de l'existant",
        'ENVOI' => "départ préparé par l'agent export",
    ];

    /** Filtres de la liste de réception, dans l'ordre des boutons. */
    private const FILTRES = [
        'TOUS' => 'Tous',
        Regles::EN_ATTENTE => 'En attente',
        Regles::RECU => 'Reçus',
        Regles::ENDOMMAGE => 'Endommagés',
        Regles::NON_RECU => 'Non reçus',
    ];

    private const ICONES = [
        'recu' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>',
        'endommage' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
        'non_recu' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>',
        'commentaire' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>',
        'recherche' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>',
    ];

    // ------------------------------------------------------------------
    // Réception
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $p
     */
    public static function receptionPage(array $p): string
    {
        $agenceId = $p['agence_id'] ?? null;
        $peutChoisir = !empty($p['peut_choisir']);

        $html = self::styles() . Ui::pageHeader(
            'Réception des colis',
            "Pour chaque colis : Reçu, Endommagé ou Non reçu. Un colis dit non reçu est signalé manquant tout de suite à l'agence d'envoi ; "
                . 'un colis jamais pointé l’est ' . PointageColisService::DELAI_MANQUANT_HEURES . ' h après le premier colis pointé.',
            [
                'eyebrow' => 'Pointage des colis',
                'class' => 'rh-hero-white',
                'actions' => [
                    Ui::button('Rapport de réception', ['href' => 'colisage/rapport-reception', 'variant' => 'secondary']),
                    Ui::button('Suivi des départs', ['href' => 'colisage/suivi-departs', 'variant' => 'secondary']),
                ],
            ]
        );

        if ($peutChoisir) {
            $html .= self::choixAgence('colisage/reception', $p['agences'] ?? [], $agenceId, 'Agence qui réceptionne', true);
        } elseif ((int) $agenceId <= 0) {
            return $html . Ui::emptyState('Aucune agence de rattachement', "Votre compte n'est rattaché à aucune agence : demandez à l'administration de le compléter.");
        }

        $scanActif = $agenceId !== null && (int) $agenceId > 0;
        $scan = '<div class="lbp-pointage-scan">'
            . '<label for="lbp-reception-scan"><strong>Douchette</strong> : scannez chaque colis reçu, il est marqué « Reçu »</label>'
            . '<input type="text" id="lbp-reception-scan" autocomplete="off" inputmode="text" enterkeyhint="done" placeholder="Code colis"'
            . ($scanActif ? ' data-agence="' . (int) $agenceId . '"' : ' disabled') . '>'
            . ($scanActif ? '' : '<small>Choisissez l\'agence qui réceptionne pour utiliser la douchette.</small>')
            . '</div>';

        $blocs = '';
        foreach (($p['departs'] ?? []) as $depart) {
            $blocs .= self::blocReception($depart, $peutChoisir ? $agenceId : null);
        }

        if ($blocs === '') {
            $blocs = Ui::emptyState('Aucun départ en attente', "Aucune agence n'a encore marqué de colis partis vers cette agence, ou tout a déjà été reçu.");
        }

        return $html
            . '<div id="lbp-reception" data-csrf="' . View::e(Csrf::token()) . '"'
            . ' data-url-pointer="' . View::e(View::url('colisage/reception/pointer')) . '"'
            . ' data-url-scanner="' . View::e(View::url('colisage/reception/scanner')) . '">'
            . Ui::section('Scanner', $scan . '<div id="lbp-reception-message" class="lbp-pointage-message" role="status" aria-live="polite"></div>')
            . $blocs
            . '</div>'
            . self::scriptReception();
    }

    /**
     * @param array<string, mixed> $depart
     */
    private static function blocReception(array $depart, ?int $agenceFiltre): string
    {
        $departId = (int) $depart['id'];
        $fuseauIci = (string) ($depart['fuseau_arrivee'] ?? HeureAgence::SERVEUR);
        $fuseauAutre = (string) ($depart['fuseau_depart'] ?? HeureAgence::SERVEUR);

        $lignes = '';
        foreach (($depart['colis'] ?? []) as $colis) {
            $lignes .= self::ligneReception($colis, $fuseauIci, $fuseauAutre);
        }

        [$libelle, $ton] = self::ETATS[(string) $depart['etat']] ?? ['—', 'neutral'];

        $delai = '';
        if (!empty($depart['echeance']) && empty($depart['delai_depasse']) && (int) ($depart['en_attente'] ?? 0) > 0) {
            $delai = '<span class="lbp-pointage-note">Les colis encore en attente seront signalés manquants à partir du '
                . View::e(HeureAgence::double($depart['echeance'], $fuseauIci, $fuseauAutre)) . '.</span>';
        } elseif ((int) $depart['manquants'] > 0) {
            $delai = '<span class="lbp-pointage-note is-alerte"><span data-role="manquants">' . (int) $depart['manquants'] . "</span> colis manquant(s) signalé(s) à l'agence d'envoi.</span>";
        }

        $filtre = $agenceFiltre !== null ? Form::hidden('agence_filtre', (string) $agenceFiltre) : '';

        $actions = '<div class="lbp-pointage-actions">'
            . '<form method="post" action="' . View::e(View::url('colisage/reception/' . $departId . '/tout-pointer')) . '"'
            . ' data-confirmer="' . View::e('Marquer « Reçu » tous les colis encore en attente de ce départ ? Les colis déjà dits endommagés ou non reçus ne changent pas.') . '">'
            . Form::hidden('_csrf_token', Csrf::token()) . $filtre
            . Ui::button('Tout cocher reçu', ['type' => 'submit', 'variant' => 'secondary'])
            . '</form>'
            . '<form method="post" action="' . View::e(View::url('colisage/reception/' . $departId . '/prevenir-clients')) . '">'
            . Form::hidden('_csrf_token', Csrf::token()) . $filtre
            . Ui::button('Prévenir les clients', ['type' => 'submit', 'variant' => 'secondary'])
            . '</form>'
            . Ui::button('Détail et historique', ['href' => 'colisage/departs/' . $departId, 'variant' => 'secondary'])
            . '</div>';

        $compteurs = '<div class="lbp-rc-compteurs" aria-live="polite">'
            . self::compteur('recus', (int) $depart['recus'], 'reçu(s) sur ' . (int) $depart['envoyes'])
            . self::compteur('endommages', (int) ($depart['endommages'] ?? 0), 'dont endommagé(s)')
            . self::compteur('non_recus', (int) ($depart['non_recus'] ?? 0), 'non reçu(s)')
            . self::compteur('en_attente', (int) ($depart['en_attente'] ?? $depart['restants']), 'en attente')
            . '</div>';

        $entete = '<div class="lbp-pointage-entete">'
            . '<div>' . Ui::badge($libelle, $ton, ['class' => 'lbp-rc-etat-depart']) . ' ' . $delai . '</div>'
            . $actions
            . '</div>' . $compteurs;

        $filtres = '';
        foreach (self::FILTRES as $cle => $libelleFiltre) {
            $filtres .= '<button type="button" class="lbp-rc-filtre" data-filtre="' . $cle . '" aria-pressed="' . ($cle === 'TOUS' ? 'true' : 'false') . '">'
                . View::e($libelleFiltre) . ' <span class="lbp-rc-nb" data-nb="' . $cle . '">0</span></button>';
        }

        $outils = '<div class="lbp-rc-outils">'
            . '<label class="lbp-rc-recherche">' . self::ICONES['recherche']
            . '<span class="lbp-rc-sr">Rechercher un numéro de colis</span>'
            . '<input type="search" class="lbp-rc-cherche" autocomplete="off" placeholder="Rechercher un numéro"></label>'
            . '<div class="lbp-rc-filtres" role="group" aria-label="Filtrer les colis">' . $filtres . '</div>'
            . '</div>';

        $liste = $lignes === ''
            ? Ui::emptyState('Aucun colis dans ce départ')
            : '<ul class="lbp-rc-liste">' . $lignes . '</ul><p class="lbp-rc-vide" hidden>Aucun colis ne correspond à ce filtre.</p>';

        $titre = 'De ' . (string) ($depart['agence_depart'] ?? '—')
            . ($agenceFiltre === null && !empty($depart['agence_arrivee']) ? ' vers ' . $depart['agence_arrivee'] : '')
            . ', parti le ' . HeureAgence::double($depart['date_depart'] ?? null, $fuseauAutre, $fuseauIci);

        return '<div class="lbp-reception-depart" data-depart-id="' . $departId . '">'
            . Ui::section(
                $titre,
                $entete . $outils . $liste,
                'Réf. ' . (string) $depart['reference'] . (!empty($depart['est_reprise']) ? " (reprise de l'existant)" : '')
            )
            . '</div>';
    }

    /**
     * Un colis à pointer : ses trois boutons, son commentaire, qui l'a pointé.
     *
     * @param array<string, mixed> $colis
     */
    private static function ligneReception(array $colis, string $fuseauIci, string $fuseauAutre): string
    {
        $id = (int) $colis['id'];
        $code = (string) $colis['numero_tracking'];
        $termine = in_array((string) $colis['statut'], ['retire', 'livre'], true);
        $etat = $termine ? 'RETIRE' : Regles::etatDe($colis);
        $commentaire = (string) ($colis['commentaire_reception'] ?? '');
        $manquant = ($colis['etat'] ?? '') === 'MANQUANT';

        $pointeLe = $colis['pointe_le'] ?? $colis['date_reception'] ?? null;
        $trace = $etat === Regles::EN_ATTENTE || empty($pointeLe)
            ? ($manquant ? 'Jamais pointé : compté manquant.' : '')
            : 'Pointé le ' . HeureAgence::double($pointeLe, $fuseauIci, $fuseauAutre) . ' par ' . ((string) ($colis['pointe_par'] ?? $colis['recu_par'] ?? '') ?: '—');

        $client = trim((string) ($colis['expediteur'] ?? '—') . ' → ' . (string) ($colis['destinataire'] ?? '—'))
            . ' · ' . (int) ($colis['nombre_colis'] ?? 1) . ' pièce(s) · ' . number_format((float) ($colis['poids_total'] ?? 0), 2, ',', ' ') . ' kg';

        $info = '<div class="lbp-rc-info"><strong class="lbp-rc-code">' . View::e($code) . '</strong>'
            . '<span class="lbp-rc-client">' . View::e($client) . '</span>'
            . '<span class="lbp-rc-trace" data-role="trace">' . View::e($trace) . '</span>'
            . '<span class="lbp-rc-com" data-role="commentaire"' . ($commentaire === '' ? ' hidden' : '') . '>' . View::e($commentaire) . '</span>'
            . '</div>';

        if ($termine) {
            return '<li class="lbp-rc is-termine" data-colis-id="' . $id . '" data-etat="RETIRE" data-code="' . View::e(mb_strtolower($code)) . '">'
                . $info . '<div class="lbp-rc-choix">' . Ui::badge('Retiré', 'neutral') . '</div></li>';
        }

        $bouton = static fn (string $valeur, string $icone, string $texte): string => '<button type="button" class="lbp-rc-btn lbp-rc-btn--' . strtolower($valeur) . '" data-etat="' . $valeur . '"'
            . ' aria-pressed="' . ($etat === $valeur ? 'true' : 'false') . '">' . self::ICONES[$icone] . '<span>' . View::e($texte) . '</span></button>';

        return '<li class="lbp-rc' . ($manquant ? ' is-manquant' : '') . '" data-colis-id="' . $id . '" data-etat="' . $etat . '"'
            . ' data-code="' . View::e(mb_strtolower($code)) . '" data-commentaire="' . View::e($commentaire) . '">'
            . $info
            . '<div class="lbp-rc-choix" role="group" aria-label="' . View::e('État du colis ' . $code) . '">'
            . $bouton(Regles::RECU, 'recu', 'Reçu')
            . $bouton(Regles::ENDOMMAGE, 'endommage', 'Endommagé')
            . $bouton(Regles::NON_RECU, 'non_recu', 'Non reçu')
            . '<button type="button" class="lbp-rc-plus" data-action="commentaire" aria-label="' . View::e('Commentaire du colis ' . $code) . '" title="Commentaire">' . self::ICONES['commentaire'] . '</button>'
            . '</div>'
            . '</li>';
    }

    private static function compteur(string $role, int $valeur, string $libelle): string
    {
        return '<span class="lbp-rc-compteur lbp-rc-compteur--' . $role . '"><strong data-role="' . $role . '">' . $valeur . '</strong> ' . View::e($libelle) . '</span>';
    }

    // ------------------------------------------------------------------
    // Suivi des départs
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $p
     */
    public static function suiviPage(array $p): string
    {
        $suivi = $p['suivi'] ?? [];
        $totaux = ($suivi['totaux'] ?? []) + ['departs' => 0, 'envoyes' => 0, 'recus' => 0, 'endommages' => 0, 'non_recus' => 0, 'manquants' => 0, 'en_attente' => 0];

        $html = self::styles() . Ui::pageHeader(
            'Suivi des départs',
            "Colis envoyés, reçus, endommagés et manquants, agence par agence.",
            [
                'eyebrow' => 'Pointage des colis',
                'class' => 'rh-hero-white',
                'actions' => [
                    Ui::button('Rapport de réception', ['href' => 'colisage/rapport-reception', 'variant' => 'secondary']),
                    Ui::button('Réception des colis', ['href' => 'colisage/reception', 'variant' => 'secondary']),
                ],
            ]
        );

        $options = '';
        if (!empty($p['peut_choisir'])) {
            $options = '<option value="">Toutes les agences</option>';
            foreach (($p['agences'] ?? []) as $a) {
                $options .= '<option value="' . (int) $a['id'] . '"' . ((int) ($p['agence_id'] ?? 0) === (int) $a['id'] ? ' selected' : '') . '>'
                    . View::e((string) $a['name']) . '</option>';
            }
        }

        $html .= '<form method="get" action="' . View::e(View::url('colisage/suivi-departs')) . '" class="lbp-pointage-filtre">'
            . '<label>Du <input type="date" name="du" value="' . View::e((string) ($p['du'] ?? '')) . '"></label>'
            . '<label>Au <input type="date" name="au" value="' . View::e((string) ($p['au'] ?? '')) . '"></label>'
            . ($options !== '' ? '<label>Agence <select name="agence">' . $options . '</select></label>' : '')
            . Ui::button('Afficher', ['type' => 'submit', 'variant' => 'primary'])
            . '</form>';

        $html .= '<div class="lbp-pointage-kpis">'
            . self::kpi('Départs', (int) $totaux['departs'])
            . self::kpi('Colis envoyés', (int) $totaux['envoyes'])
            . self::kpi('Reçus', (int) $totaux['recus'])
            . self::kpi('dont endommagés', (int) $totaux['endommages'], (int) $totaux['endommages'] > 0)
            . self::kpi('En attente', (int) $totaux['en_attente'])
            . self::kpi('Manquants', (int) $totaux['manquants'], (int) $totaux['manquants'] > 0)
            . '</div>';

        $lignesTrajets = [];
        foreach (($suivi['trajets'] ?? []) as $t) {
            $taux = PointageColisService::taux((int) $t['recus'], (int) $t['envoyes']);
            $lignesTrajets[] = [
                View::e((string) $t['agence_depart']),
                View::e((string) $t['agence_arrivee']),
                (string) (int) $t['departs'],
                (string) (int) $t['envoyes'],
                (string) (int) $t['recus'],
                (string) (int) ($t['endommages'] ?? 0),
                (int) $t['manquants'] > 0 ? '<strong class="lbp-alerte">' . (int) $t['manquants'] . '</strong>' : '0',
                self::jaugeTaux($taux, (int) $t['manquants'] > 0),
            ];
        }

        $html .= Ui::section('Par trajet', ModuleTable::render([
            ['label' => "Agence d'envoi"], ['label' => "Agence d'arrivée"], ['label' => 'Départs', 'align' => 'right'],
            ['label' => 'Envoyés', 'align' => 'right'], ['label' => 'Reçus', 'align' => 'right'], ['label' => 'Endommagés', 'align' => 'right'],
            ['label' => 'Manquants', 'align' => 'right'], ['label' => 'Taux de réception'],
        ], $lignesTrajets, 'Aucun départ sur la période', 'Élargissez la période, ou vérifiez que les agences marquent bien leurs départs.'));

        // null : l'utilisateur n'a pas accès aux dossiers d'envoi, la colonne n'existe pas.
        $dossiersParDepart = $p['dossiers_par_depart'] ?? null;

        $lignesDeparts = [];
        foreach (($suivi['departs'] ?? []) as $d) {
            [$libelle, $ton] = self::ETATS[(string) $d['etat']] ?? ['—', 'neutral'];
            $ligne = [
                self::lienDepart($d),
                View::e(HeureAgence::double($d['date_depart'] ?? null, (string) ($d['fuseau_depart'] ?? ''), (string) ($d['fuseau_arrivee'] ?? ''))),
                View::e((string) ($d['agence_depart'] ?? '—')) . ' → ' . View::e((string) ($d['agence_arrivee'] ?? '—')),
                (string) (int) $d['envoyes'],
                (string) (int) $d['recus'],
                (string) (int) ($d['endommages'] ?? 0),
                (int) $d['manquants'] > 0 ? '<strong class="lbp-alerte">' . (int) $d['manquants'] . '</strong>' : '0',
                Ui::badge($libelle, $ton),
            ];
            if (is_array($dossiersParDepart)) {
                $ligne[] = self::liensDossiers($dossiersParDepart[(int) $d['id']] ?? []);
            }
            $lignesDeparts[] = $ligne;
        }

        $colonnesDeparts = [
            ['label' => 'Départ'], ['label' => 'Parti le'], ['label' => 'Trajet'], ['label' => 'Envoyés', 'align' => 'right'],
            ['label' => 'Reçus', 'align' => 'right'], ['label' => 'Endommagés', 'align' => 'right'], ['label' => 'Manquants', 'align' => 'right'], ['label' => 'État'],
        ];
        if (is_array($dossiersParDepart)) {
            $colonnesDeparts[] = ['label' => "Dossier d'envoi"];
        }

        $html .= Ui::section('Départs', ModuleTable::render($colonnesDeparts, $lignesDeparts, 'Aucun départ sur la période'));

        $lignesHors = [];
        foreach (($suivi['hors_liste'] ?? []) as $h) {
            $lignesHors[] = [
                View::e(HeureAgence::double($h['created_at'] ?? null, (string) ($h['fuseau_reception'] ?? ''), HeureAgence::SERVEUR)),
                '<strong>' . View::e((string) $h['numero_tracking']) . '</strong>',
                View::e((string) ($h['agence_reception'] ?? '—')),
                View::e((string) ($h['agence_depart'] ?? '—')),
                View::e((string) ($h['agence_prevue'] ?? '—')),
                View::e((string) ($h['par'] ?? '—')),
            ];
        }

        $html .= Ui::section('Colis reçus hors liste', ModuleTable::render([
            ['label' => 'Reçu le'], ['label' => 'Code colis'], ['label' => 'Reçu à'], ['label' => 'Envoyé par'],
            ['label' => 'Attendu à'], ['label' => 'Pointé par'],
        ], $lignesHors, 'Aucun colis reçu hors liste sur la période'));

        return $html;
    }

    // ------------------------------------------------------------------
    // Détail d'un départ
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $p
     */
    public static function detailPage(array $p): string
    {
        $depart = $p['depart'];
        $departId = (int) $depart['id'];
        [$libelle, $ton] = self::ETATS[(string) $depart['etat']] ?? ['—', 'neutral'];
        $fuseauArrivee = (string) ($depart['fuseau_arrivee'] ?? HeureAgence::SERVEUR);
        $fuseauDepart = (string) ($depart['fuseau_depart'] ?? HeureAgence::SERVEUR);

        $html = self::styles() . Ui::pageHeader(
            'Départ ' . (string) $depart['reference'],
            (string) ($depart['agence_depart'] ?? '—') . ' → ' . (string) ($depart['agence_arrivee'] ?? '—')
                . ', parti le ' . HeureAgence::double($depart['date_depart'] ?? null, $fuseauDepart, $fuseauArrivee),
            [
                'eyebrow' => 'Pointage des colis',
                'class' => 'rh-hero-white',
                'actions' => [
                    Ui::badge($libelle, $ton, ['class' => 'finea-badge--large']),
                    Ui::button('Rapport de réception', ['href' => 'colisage/rapport-reception?' . http_build_query(['periode' => 'jour', 'depart' => $departId]), 'variant' => 'secondary']),
                    Ui::button('Manifeste', ['href' => 'colisage/groupage/' . $departId . '/manifeste', 'variant' => 'secondary', 'target' => '_blank']),
                    Ui::button('Réception des colis', ['href' => 'colisage/reception', 'variant' => 'secondary']),
                    Ui::button('Suivi des départs', ['href' => 'colisage/suivi-departs', 'variant' => 'secondary']),
                ],
            ]
        );

        $html .= '<div class="lbp-pointage-kpis">'
            . self::kpi('Colis envoyés', (int) $depart['envoyes'])
            . self::kpi('Reçus', (int) $depart['recus'])
            . self::kpi('dont endommagés', (int) ($depart['endommages'] ?? 0), (int) ($depart['endommages'] ?? 0) > 0)
            . self::kpi('Non reçus', (int) ($depart['non_recus'] ?? 0), (int) ($depart['non_recus'] ?? 0) > 0)
            . self::kpi('En attente', (int) ($depart['en_attente'] ?? $depart['restants']))
            . self::kpi('Manquants', (int) $depart['manquants'], (int) $depart['manquants'] > 0)
            . '</div>';

        if (!empty($depart['echeance'])) {
            $html .= '<p class="lbp-pointage-note">Premier colis pointé : délai des manquants atteint le ' . View::e(HeureAgence::double($depart['echeance'], $fuseauArrivee, $fuseauDepart)) . '.</p>';
        }

        $lignes = [];
        foreach (($p['colis'] ?? []) as $c) {
            [$etat, $tonEtat] = self::ETATS_COLIS[(string) $c['etat']] ?? ['—', 'neutral'];
            $pointeLe = $c['pointe_le'] ?? $c['date_reception'] ?? null;
            $lignes[] = [
                '<strong>' . View::e((string) $c['numero_tracking']) . '</strong>',
                View::e((string) ($c['expediteur'] ?? '—')),
                View::e((string) ($c['destinataire'] ?? '—')),
                View::e((string) (int) ($c['nombre_colis'] ?? 1)),
                View::e(number_format((float) ($c['poids_total'] ?? 0), 2, ',', ' ')) . ' kg',
                Ui::badge($etat, $tonEtat),
                View::e((string) ($c['commentaire_reception'] ?? '') ?: '—'),
                View::e(!empty($pointeLe) && !in_array((string) $c['etat'], ['ATTENDU', 'MANQUANT'], true)
                    ? HeureAgence::double($pointeLe, $fuseauArrivee, $fuseauDepart) . ' par ' . ((string) ($c['pointe_par'] ?? $c['recu_par'] ?? '') ?: '—')
                    : '—'),
            ];
        }

        $html .= Ui::section('Colis du départ', ModuleTable::render([
            ['label' => 'Code colis'], ['label' => 'Expéditeur'], ['label' => 'Destinataire'], ['label' => 'Pièces', 'align' => 'right'],
            ['label' => 'Poids', 'align' => 'right'], ['label' => 'État'], ['label' => 'Commentaire'], ['label' => 'Pointé'],
        ], $lignes, 'Aucun colis dans ce départ'));

        // Absent pour qui n'a pas accès aux dossiers d'envoi.
        if (array_key_exists('dossiers_envoi', $p)) {
            $dossiers = $p['dossiers_envoi'] ?? [];
            $contenu = $dossiers === []
                ? '<p class="lbp-pointage-note">Ce départ a été marqué avant « Préparer un départ » : il n\'a pas de document de compagnie enregistré.</p>'
                : '<p>' . self::liensDossiers($dossiers) . '</p>';

            $html .= Ui::section('Document de la compagnie', $contenu);
        }

        $historique = [];
        foreach (($p['historique'] ?? []) as $h) {
            $changement = '';
            if (!empty($h['etat_avant']) || !empty($h['etat_apres'])) {
                $changement = (Regles::LIBELLES[(string) ($h['etat_avant'] ?? '')] ?? '—') . ' → ' . (Regles::LIBELLES[(string) ($h['etat_apres'] ?? '')] ?? '—');
            }
            $historique[] = [
                View::e(HeureAgence::double($h['created_at'] ?? null, (string) ($h['fuseau'] ?? ''), (string) ($h['fuseau'] ?? '') === $fuseauArrivee ? $fuseauDepart : $fuseauArrivee)),
                View::e(self::ACTIONS[(string) $h['action']] ?? (string) $h['action']),
                View::e($changement !== '' ? $changement : '—'),
                View::e(self::SOURCES[(string) $h['source']] ?? (string) $h['source']),
                '<strong>' . View::e((string) $h['numero_tracking']) . '</strong>',
                View::e((string) ($h['commentaire'] ?? '') ?: '—'),
                View::e((string) ($h['agence'] ?? '—')),
                View::e((string) ($h['par'] ?? '—')),
            ];
        }

        return $html . Ui::section('Historique des pointages', ModuleTable::render([
            ['label' => 'Quand'], ['label' => 'Geste'], ['label' => 'État'], ['label' => 'Par'], ['label' => 'Code colis'],
            ['label' => 'Commentaire'], ['label' => 'Agence'], ['label' => 'Utilisateur'],
        ], $historique, 'Aucun pointage enregistré'));
    }

    // ------------------------------------------------------------------
    // Éléments communs
    // ------------------------------------------------------------------

    /**
     * @param array<int, array{id:int, name:string}> $agences
     */
    private static function choixAgence(string $chemin, array $agences, ?int $choisie, string $libelle, bool $toutes): string
    {
        $options = '<option value="">' . ($toutes ? 'Toutes les agences' : 'Choisir une agence') . '</option>';

        foreach ($agences as $agence) {
            $options .= '<option value="' . (int) $agence['id'] . '"' . ($choisie === (int) $agence['id'] ? ' selected' : '') . '>'
                . View::e((string) $agence['name']) . '</option>';
        }

        return '<form method="get" action="' . View::e(View::url($chemin)) . '" class="lbp-pointage-filtre">'
            . '<label>' . View::e($libelle) . ' <select name="agence" class="lbp-envoi-auto">' . $options . '</select></label>'
            . Ui::button('Afficher', ['type' => 'submit', 'variant' => 'secondary'])
            . '</form>';
    }

    /**
     * @param array<string, mixed> $depart
     */
    public static function lienDepart(array $depart): string
    {
        return '<a href="' . View::e(View::url('colisage/departs/' . (int) $depart['id'])) . '"><strong>'
            . View::e((string) $depart['reference']) . '</strong></a>';
    }

    /**
     * @param array<int, array<string, mixed>> $dossiers
     */
    private static function liensDossiers(array $dossiers): string
    {
        if ($dossiers === []) {
            return Ui::badge('Sans dossier', 'warning');
        }

        return implode(' ', array_map(
            static fn (array $d): string => '<a href="' . View::e(View::url('colisage/envois/' . (int) $d['id'])) . '"><strong>'
                . View::e((string) $d['numero']) . '</strong></a>',
            $dossiers
        ));
    }

    /** « 99,9 % » avec sa jauge ; la jauge s'arrondit vers le bas, comme le taux. */
    public static function jaugeTaux(float $taux, bool $alerte): string
    {
        $ton = $taux >= 100.0 ? 'success' : ($alerte ? 'danger' : 'warning');

        return '<span class="lbp-taux">' . ModuleTable::jauge((int) floor($taux), $ton)
            . '<small>' . View::e(number_format($taux, 1, ',', '') . ' %') . '</small></span>';
    }

    public static function kpi(string $libelle, int|string $valeur, bool $alerte = false): string
    {
        return '<div class="lbp-pointage-kpi' . ($alerte ? ' is-alerte' : '') . '"><span>' . View::e($libelle) . '</span><strong>' . View::e((string) $valeur) . '</strong></div>';
    }

    public static function styles(): string
    {
        return <<<'CSS'
<style>
.lbp-pointage-filtre{display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;margin:0 0 1rem}
.lbp-pointage-filtre label,.lbp-pointage-champ{display:flex;flex-direction:column;gap:.25rem;font-size:.8rem;font-weight:600;color:#475569}
.lbp-pointage-filtre select,.lbp-pointage-filtre input,.lbp-pointage-champ select{padding:.45rem .6rem;border:1px solid #cbd5e1;border-radius:8px;background:#fff;min-height:44px}
.lbp-pointage-scan{display:flex;flex-direction:column;gap:.35rem}
.lbp-pointage-scan input{font-family:Consolas,Monaco,monospace;font-size:1.1rem;font-weight:700;padding:.7rem 1rem;border:2px solid #2563eb;border-radius:10px;max-width:26rem;min-height:48px}
.lbp-pointage-scan input:disabled{border-color:#cbd5e1;background:#f1f5f9}
.lbp-pointage-message{display:none;margin:.75rem 0 0;padding:.6rem .9rem;border-radius:8px;font-weight:600}
.lbp-pointage-message.is-ok{display:block;background:#f0fdf4;color:#166534;border:1px solid #bbf7d0}
.lbp-pointage-message.is-erreur{display:block;background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
.lbp-pointage-entete{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.75rem;margin-bottom:.75rem}
.lbp-pointage-note{display:block;font-size:.82rem;color:#475569;margin-top:.25rem}
.lbp-pointage-note.is-alerte,.lbp-alerte{color:#b91c1c}
.lbp-pointage-actions{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center}
.lbp-pointage-actions form{margin:0}
.lbp-pointage-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(9rem,1fr));gap:.75rem;margin:0 0 1rem}
.lbp-pointage-kpi{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:.75rem 1rem}
.lbp-pointage-kpi span{display:block;font-size:.72rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#64748b}
.lbp-pointage-kpi strong{font-size:1.4rem;font-variant-numeric:tabular-nums;color:#0f172a}
.lbp-pointage-kpi.is-alerte strong{color:#b91c1c}
.lbp-taux{display:inline-flex;flex-direction:column;gap:2px}.lbp-taux small{color:#475569;font-variant-numeric:tabular-nums}
.lbp-rc-compteurs{position:sticky;top:0;z-index:4;display:flex;flex-wrap:wrap;gap:.35rem 1rem;padding:.6rem .75rem;margin:0 0 .75rem;background:#fff;border:1px solid #e2e8f0;border-radius:10px;box-shadow:0 4px 12px rgba(15,23,42,.05);font-size:.9rem;color:#334155}
.lbp-rc-compteur strong{font-size:1.15rem;font-variant-numeric:tabular-nums;color:#0f172a}
.lbp-rc-compteur--endommages strong{color:#b45309}.lbp-rc-compteur--non_recus strong{color:#b91c1c}
.lbp-rc-outils{display:flex;flex-wrap:wrap;gap:.6rem;align-items:center;margin:0 0 .75rem}
.lbp-rc-recherche{display:flex;align-items:center;gap:.4rem;flex:1 1 14rem;min-height:44px;padding:0 .7rem;border:1px solid #cbd5e1;border-radius:10px;background:#fff;color:#64748b}
.lbp-rc-recherche input{flex:1;min-width:0;border:0;outline:0;font-size:1rem;min-height:42px;background:transparent;color:#0f172a}
.lbp-rc-recherche:focus-within{border-color:#2563eb;box-shadow:0 0 0 2px rgba(37,99,235,.2)}
.lbp-rc-sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
.lbp-rc-filtres{display:flex;gap:.4rem;overflow-x:auto;-webkit-overflow-scrolling:touch;padding-bottom:2px}
.lbp-rc-filtre{flex:0 0 auto;min-height:44px;padding:0 .8rem;border:1px solid #cbd5e1;border-radius:999px;background:#fff;color:#334155;font-weight:600;cursor:pointer}
.lbp-rc-filtre[aria-pressed="true"]{background:#0f172a;border-color:#0f172a;color:#fff}
.lbp-rc-nb{display:inline-block;min-width:1.4em;padding:0 .35em;border-radius:999px;background:rgba(100,116,139,.18);font-variant-numeric:tabular-nums;font-size:.8rem}
.lbp-rc-liste{list-style:none;margin:0;padding:0;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden}
.lbp-rc{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:.5rem 1rem;align-items:center;padding:.6rem .75rem;border-top:1px solid #e2e8f0;border-left:4px solid transparent;background:#fff}
.lbp-rc:first-child{border-top:0}
.lbp-rc[data-etat="RECU"]{border-left-color:#16a34a;background:#f7fdf9}
.lbp-rc[data-etat="ENDOMMAGE"]{border-left-color:#d97706;background:#fffbeb}
.lbp-rc[data-etat="NON_RECU"],.lbp-rc.is-manquant{border-left-color:#dc2626;background:#fef6f6}
.lbp-rc.is-occupe{opacity:.6}
.lbp-rc-info{display:flex;flex-direction:column;gap:2px;min-width:0}
.lbp-rc-code{font-family:Consolas,Monaco,monospace;font-size:1rem;color:#0f172a;overflow-wrap:anywhere}
.lbp-rc-client,.lbp-rc-trace{font-size:.8rem;color:#64748b;overflow-wrap:anywhere}
.lbp-rc-trace:empty{display:none}
.lbp-rc-com{font-size:.82rem;color:#92400e;font-style:italic;overflow-wrap:anywhere}
.lbp-rc-choix{display:flex;gap:.35rem;align-items:center}
.lbp-rc-btn,.lbp-rc-plus{display:inline-flex;align-items:center;justify-content:center;gap:.3rem;min-height:44px;min-width:44px;padding:0 .7rem;border:1px solid #cbd5e1;border-radius:10px;background:#fff;color:#334155;font-weight:700;font-size:.85rem;cursor:pointer;touch-action:manipulation}
.lbp-rc-btn:focus-visible,.lbp-rc-plus:focus-visible,.lbp-rc-filtre:focus-visible{outline:2px solid #2563eb;outline-offset:2px}
.lbp-rc-btn--recu[aria-pressed="true"]{background:#16a34a;border-color:#16a34a;color:#fff}
.lbp-rc-btn--endommage[aria-pressed="true"]{background:#d97706;border-color:#d97706;color:#fff}
.lbp-rc-btn--non_recu[aria-pressed="true"]{background:#dc2626;border-color:#dc2626;color:#fff}
.lbp-rc[data-etat="EN_ATTENTE"] .lbp-rc-plus{visibility:hidden}
.lbp-rc-saisie{grid-column:1/-1;display:flex;flex-wrap:wrap;gap:.5rem;align-items:flex-end;padding:.5rem 0 0}
.lbp-rc-saisie label{flex:1 1 100%;display:flex;flex-direction:column;gap:.25rem;font-size:.8rem;font-weight:700;color:#475569}
.lbp-rc-saisie textarea{min-height:64px;padding:.5rem .6rem;border:1px solid #cbd5e1;border-radius:8px;font:inherit;font-size:1rem;resize:vertical}
.lbp-rc-saisie button{min-height:44px;padding:0 .9rem;border-radius:10px;border:1px solid #cbd5e1;background:#fff;font-weight:700;cursor:pointer}
.lbp-rc-saisie button[data-action="valider"]{background:#2563eb;border-color:#2563eb;color:#fff}
.lbp-rc-vide{padding:1rem;color:#64748b;text-align:center}
@media (max-width:640px){
  .lbp-rc{grid-template-columns:1fr}
  .lbp-rc-choix{display:grid;grid-template-columns:1fr 1fr 1fr 44px}
  .lbp-rc-btn{padding:0 .3rem;font-size:.8rem}
  .lbp-rc-btn svg{flex:0 0 auto}
  .lbp-pointage-actions{width:100%}
  .lbp-pointage-actions form,.lbp-pointage-actions .finea-action-btn{flex:1 1 auto}
}
@media (max-width:380px){.lbp-rc-btn span{font-size:.74rem}}
</style>
CSS;
    }

    private static function scriptCommun(): string
    {
        return <<<'JS'
document.querySelectorAll('form[data-confirmer]').forEach(function (formulaire) {
    formulaire.addEventListener('submit', function (evenement) {
        if (!window.confirm(formulaire.dataset.confirmer)) {
            evenement.preventDefault();
        }
    });
});
document.querySelectorAll('select.lbp-envoi-auto').forEach(function (liste) {
    liste.addEventListener('change', function () { liste.form.submit(); });
});
JS;
    }

    private static function scriptReception(): string
    {
        $etats = [];
        foreach (self::ETATS as $cle => [$libelle, $ton]) {
            $etats[$cle] = ['libelle' => $libelle, 'ton' => $ton];
        }

        return '<script>(function () {' . self::scriptCommun()
            . 'var ETATS_DEPART = ' . json_encode($etats, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . ';'
            . <<<'JS'
var racine = document.getElementById('lbp-reception');
if (!racine) { return; }
var zone = document.getElementById('lbp-reception-message');
function dire(texte, ok) {
    zone.textContent = texte;
    zone.className = 'lbp-pointage-message ' + (ok ? 'is-ok' : 'is-erreur');
}
function envoyer(url, donnees) {
    var corps = new FormData();
    corps.append('_csrf_token', racine.dataset.csrf);
    Object.keys(donnees).forEach(function (cle) { corps.append(cle, donnees[cle]); });
    return fetch(url, { method: 'POST', body: corps, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (reponse) { return reponse.json(); });
}
function deux(n) { return (n < 10 ? '0' : '') + n; }
function maintenant() {
    var d = new Date();
    return deux(d.getHours()) + ' h ' + deux(d.getMinutes());
}

// --- Compteurs, filtres, recherche -----------------------------------
function compter(bloc) {
    var nb = { TOUS: 0, EN_ATTENTE: 0, RECU: 0, ENDOMMAGE: 0, NON_RECU: 0 };
    bloc.querySelectorAll('.lbp-rc').forEach(function (ligne) {
        nb.TOUS++;
        var etat = ligne.dataset.etat === 'RETIRE' ? 'RECU' : ligne.dataset.etat;
        if (nb[etat] !== undefined) { nb[etat]++; }
    });
    bloc.querySelectorAll('[data-nb]').forEach(function (pastille) {
        pastille.textContent = nb[pastille.dataset.nb] || 0;
    });
}
function filtrer(bloc) {
    var actif = bloc.querySelector('.lbp-rc-filtre[aria-pressed="true"]');
    var filtre = actif ? actif.dataset.filtre : 'TOUS';
    var champ = bloc.querySelector('.lbp-rc-cherche');
    var cherche = champ ? champ.value.trim().toLowerCase() : '';
    var visibles = 0;
    bloc.querySelectorAll('.lbp-rc').forEach(function (ligne) {
        var etat = ligne.dataset.etat === 'RETIRE' ? 'RECU' : ligne.dataset.etat;
        var garde = (filtre === 'TOUS' || etat === filtre) && (cherche === '' || ligne.dataset.code.indexOf(cherche) !== -1);
        // Une ligne en cours de saisie reste à l'écran, même si son état ne correspond plus.
        if (ligne.querySelector('.lbp-rc-saisie')) { garde = true; }
        ligne.hidden = !garde;
        if (garde) { visibles++; }
    });
    var vide = bloc.querySelector('.lbp-rc-vide');
    if (vide) { vide.hidden = visibles > 0; }
}
racine.querySelectorAll('.lbp-reception-depart').forEach(function (bloc) {
    compter(bloc);
    var champ = bloc.querySelector('.lbp-rc-cherche');
    if (champ) { champ.addEventListener('input', function () { filtrer(bloc); }); }
});
function actualiser(depart) {
    if (!depart) { return; }
    var bloc = racine.querySelector('.lbp-reception-depart[data-depart-id="' + depart.id + '"]');
    if (!bloc) { return; }
    ['recus', 'endommages', 'non_recus', 'en_attente', 'manquants'].forEach(function (role) {
        var cible = bloc.querySelector('[data-role="' + role + '"]');
        if (cible && depart[role] !== undefined) { cible.textContent = depart[role]; }
    });
    var badge = bloc.querySelector('.lbp-rc-etat-depart');
    var etat = ETATS_DEPART[depart.etat];
    if (badge && etat) {
        badge.textContent = etat.libelle;
        badge.className = 'finea-badge finea-badge--' + etat.ton + ' lbp-rc-etat-depart';
    }
    compter(bloc);
}

// --- Une ligne ---------------------------------------------------------
function poser(ligne, etat, commentaire) {
    ligne.dataset.etat = etat;
    ligne.dataset.commentaire = commentaire || '';
    ligne.classList.remove('is-manquant');
    ligne.querySelectorAll('.lbp-rc-btn').forEach(function (bouton) {
        bouton.setAttribute('aria-pressed', bouton.dataset.etat === etat ? 'true' : 'false');
    });
    var com = ligne.querySelector('[data-role="commentaire"]');
    if (com) { com.textContent = commentaire || ''; com.hidden = !commentaire; }
    var trace = ligne.querySelector('[data-role="trace"]');
    if (trace) { trace.textContent = etat === 'EN_ATTENTE' ? '' : 'Pointé par vous à ' + maintenant(); }
}
function fermerSaisie(ligne) {
    var saisie = ligne.querySelector('.lbp-rc-saisie');
    if (saisie) { saisie.remove(); }
}
function ouvrirSaisie(ligne, etatVoulu) {
    fermerSaisie(ligne);
    var dommage = etatVoulu === 'ENDOMMAGE';
    var saisie = document.createElement('div');
    saisie.className = 'lbp-rc-saisie';
    saisie.dataset.etat = etatVoulu;
    var label = document.createElement('label');
    var titre = document.createElement('span');
    titre.textContent = dommage ? 'Décrivez le dommage (obligatoire)' : 'Commentaire (facultatif)';
    var zoneTexte = document.createElement('textarea');
    zoneTexte.maxLength = 500;
    zoneTexte.value = ligne.dataset.commentaire || '';
    zoneTexte.placeholder = dommage ? 'Carton ouvert, liquide renversé…' : 'Ce que l’agence d’envoi doit savoir';
    label.appendChild(titre);
    label.appendChild(zoneTexte);
    saisie.appendChild(label);
    [['valider', dommage ? 'Enregistrer : endommagé' : 'Enregistrer'], ['annuler', 'Annuler'], ['attente', 'Remettre en attente']].forEach(function (b) {
        if (b[0] === 'attente' && ligne.dataset.etat === 'EN_ATTENTE') { return; }
        var bouton = document.createElement('button');
        bouton.type = 'button';
        bouton.dataset.action = b[0];
        bouton.textContent = b[1];
        saisie.appendChild(bouton);
    });
    ligne.appendChild(saisie);
    zoneTexte.focus();
}
function pointer(ligne, etat, commentaire) {
    if (ligne.classList.contains('is-occupe')) { return; }
    var avant = { etat: ligne.dataset.etat, commentaire: ligne.dataset.commentaire };
    ligne.classList.add('is-occupe');
    poser(ligne, etat, commentaire);
    envoyer(racine.dataset.urlPointer, { colis_id: ligne.dataset.colisId, etat: etat, commentaire: commentaire || '' })
        .then(function (resultat) {
            ligne.classList.remove('is-occupe');
            if (!resultat.ok) {
                // La saisie, si elle était ouverte, le reste : rien de tapé n'est perdu.
                poser(ligne, avant.etat, avant.commentaire);
            } else {
                if (resultat.colis) { poser(ligne, resultat.colis.etat, resultat.colis.commentaire); }
                fermerSaisie(ligne);
            }
            dire(resultat.message, resultat.ok);
            actualiser(resultat.depart);
            filtrer(ligne.closest('.lbp-reception-depart'));
        })
        .catch(function () {
            ligne.classList.remove('is-occupe');
            poser(ligne, avant.etat, avant.commentaire);
            dire("Connexion perdue : ce pointage n'a pas été enregistré.", false);
        });
}
racine.addEventListener('click', function (evenement) {
    var cible = evenement.target.closest('button');
    if (!cible || !racine.contains(cible)) { return; }
    var bloc = cible.closest('.lbp-reception-depart');
    if (cible.classList.contains('lbp-rc-filtre')) {
        bloc.querySelectorAll('.lbp-rc-filtre').forEach(function (b) { b.setAttribute('aria-pressed', b === cible ? 'true' : 'false'); });
        filtrer(bloc);
        return;
    }
    var ligne = cible.closest('.lbp-rc');
    if (!ligne) { return; }
    if (cible.classList.contains('lbp-rc-btn')) {
        var etat = cible.dataset.etat;
        if (etat === 'ENDOMMAGE') { ouvrirSaisie(ligne, etat); return; }
        var saisieOuverte = ligne.querySelector('.lbp-rc-saisie textarea');
        pointer(ligne, etat, saisieOuverte ? saisieOuverte.value : '');
        return;
    }
    if (cible.dataset.action === 'commentaire') {
        if (ligne.querySelector('.lbp-rc-saisie')) { fermerSaisie(ligne); filtrer(bloc); } else { ouvrirSaisie(ligne, ligne.dataset.etat); }
        return;
    }
    var saisie = cible.closest('.lbp-rc-saisie');
    if (!saisie) { return; }
    if (cible.dataset.action === 'annuler') { fermerSaisie(ligne); filtrer(bloc); return; }
    if (cible.dataset.action === 'attente') { pointer(ligne, 'EN_ATTENTE', ''); return; }
    if (cible.dataset.action === 'valider') {
        var texte = saisie.querySelector('textarea').value.trim();
        if (saisie.dataset.etat === 'ENDOMMAGE' && texte.length < 3) {
            dire('Décrivez le dommage en quelques mots : l’agence d’envoi en a besoin pour prévenir le client.', false);
            saisie.querySelector('textarea').focus();
            return;
        }
        pointer(ligne, saisie.dataset.etat, texte);
    }
});

// --- Douchette ---------------------------------------------------------
var scan = document.getElementById('lbp-reception-scan');
if (scan && !scan.disabled) {
    scan.focus();
    scan.addEventListener('keydown', function (evenement) {
        if (evenement.key !== 'Enter') { return; }
        evenement.preventDefault();
        var code = scan.value.trim();
        scan.value = '';
        if (code === '') { return; }
        envoyer(racine.dataset.urlScanner, { code: code, agence_id: scan.dataset.agence || '' })
            .then(function (resultat) {
                dire(resultat.message, resultat.ok);
                if (resultat.ok && resultat.colis_id) {
                    var ligne = racine.querySelector('.lbp-rc[data-colis-id="' + resultat.colis_id + '"]');
                    if (ligne && resultat.colis) {
                        poser(ligne, resultat.colis.etat, resultat.colis.commentaire);
                        filtrer(ligne.closest('.lbp-reception-depart'));
                    }
                }
                actualiser(resultat.depart);
                scan.focus();
            })
            .catch(function () {
                dire("Connexion perdue : ce scan n'a pas été enregistré.", false);
                scan.focus();
            });
    });
}
JS . '})();</script>';
    }
}
