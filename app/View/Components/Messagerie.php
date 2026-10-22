<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\View;
use App\Security\MessagerieAcces;

/**
 * Messagerie interne : le cadre HTML de l'écran et du panneau latéral.
 *
 * Le PHP ne rend que la charpente — libellés, champs, boutons, dialogues — et
 * le JavaScript (public/assets/js/messagerie.js) y place les discussions et
 * les messages, qu'il reçoit de l'API JSON. Une seule charpente sert aux deux
 * usages : l'écran complet (/messagerie) et le panneau ouvert depuis
 * l'en-tête de n'importe quel module, qui en est la version compacte.
 *
 * Aucun identifiant HTML dans la charpente : elle peut exister deux fois dans
 * une page (écran + panneau) sans collision. Les éléments se retrouvent par
 * leur attribut data-msg.
 *
 * Les icônes sont des symboles SVG rendus une fois par page (socle) et
 * appelés par <use> : le JavaScript s'en sert aussi, sans dupliquer de tracé.
 */
final class Messagerie
{
    /** Tracés des icônes (24 × 24, trait). Jamais d'emoji dans l'interface. */
    private const ICONES = [
        'discussion' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'nouvelle' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M12 7v6M9 10h6"/>',
        'groupe' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'ajout-membre' => '<path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="M20 8v6M23 11h-6"/>',
        'retrait-membre' => '<path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="M23 11h-6"/>',
        'reglages' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'recherche' => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35"/>',
        'fermer' => '<path d="M18 6 6 18M6 6l12 12"/>',
        'retour' => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
        'infos' => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
        'chevron-bas' => '<path d="m6 9 6 6 6-6"/>',
        'repondre' => '<path d="M9 17 4 12l5-5"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/>',
        'modifier' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'supprimer' => '<path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M10 11v6M14 11v6"/>',
        'transferer' => '<path d="m15 17 5-5-5-5"/><path d="M4 18v-2a4 4 0 0 1 4-4h12"/>',
        'epingle' => '<path d="M12 17v5"/><path d="M9 10.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24V17h14v-1.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V6h1a2 2 0 0 0 0-4H8a2 2 0 0 0 0 4h1z"/>',
        'copier' => '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
        'coche' => '<path d="M20 6 9 17l-5-5"/>',
        'double-coche' => '<path d="M18 6 7 17l-5-5"/><path d="m22 10-7.5 7.5L13 16"/>',
        'horloge' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'alerte' => '<circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>',
        'sourdine' => '<path d="M13.73 21a2 2 0 0 1-3.46 0"/><path d="M18.63 13A17.89 17.89 0 0 1 18 8"/><path d="M6.26 6.26A5.86 5.86 0 0 0 6 8c0 7-3 9-3 9h14"/><path d="M18 8a6 6 0 0 0-9.33-5"/><path d="m1 1 22 22"/>',
        'cloche' => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        'archive' => '<rect x="2" y="3" width="20" height="5" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8M10 12h4"/>',
        'envoyer' => '<path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/>',
        'trombone' => '<path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>',
        'annonces' => '<path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
        'bouclier' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'oeil' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        'quitter' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
        'fleche-bas' => '<path d="M12 5v14M19 12l-7 7-7-7"/>',
        'plein-ecran' => '<path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/>',
        'cadenas' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'filtre-non-lu' => '<circle cx="12" cy="12" r="4"/>',
        // Lot 3 : fichiers, visionneuse, classeur
        'appareil' => '<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>',
        'image' => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/>',
        'fichier' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
        'fichier-texte' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8M16 17H8M10 9H8"/>',
        'tableur' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 12h8v6H8zM12 12v6M8 15h8"/>',
        'presentation' => '<path d="M2 3h20M4 3v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V3M12 16v5M8 21h8M9 11V9M12 11V7M15 11v-1"/>',
        'telecharger' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5M12 15V3"/>',
        'televerser' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5M12 3v12"/>',
        'etoile' => '<path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/>',
        'ouvrir' => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14 21 3"/>',
        'precedent' => '<path d="m15 18-6-6 6-6"/>',
        'suivant' => '<path d="m9 18 6-6-6-6"/>',
        'chevron-haut' => '<path d="m18 15-6-6-6 6"/>',
        'vignettes' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'liste' => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
        'aller' => '<path d="M5 12h14M12 5l7 7-7 7"/>',
        'tri' => '<path d="m7 15 5 5 5-5M7 9l5-5 5 5"/>',
        'dossier' => '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>',
        'filtre' => '<path d="M22 3H2l8 9.46V19l4 2v-8.54z"/>',
    ];

    /**
     * Ce que le sélecteur de fichiers propose : les formats que le serveur
     * accepte (MessagerieFormats), par extension et par type, pour que le
     * téléphone offre aussi l'appareil photo et la galerie.
     */
    public const ACCEPT_FICHIERS = '.pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,'
        . 'application/pdf,image/jpeg,image/png,image/webp';

    public const ACCEPT_IMAGES = 'image/jpeg,image/png,image/webp';

    /**
     * Ce que le JavaScript doit connaître de la page, ou null hors session.
     *
     * @return array{api:string,csrf:string,moi:int,moi_nom:string,connexion:string,plein:string,direction:bool}|null
     */
    public static function contexteCourant(): ?array
    {
        $utilisateur = Auth::check() ? Auth::user() : null;
        if ($utilisateur === null || $utilisateur->id === null || $utilisateur->status !== 'active') {
            return null;
        }

        return [
            'api' => View::url('messagerie/api'),
            'csrf' => Csrf::token(),
            'moi' => (int) $utilisateur->id,
            'moi_nom' => $utilisateur->fullName,
            'connexion' => View::url('login'),
            'plein' => View::url('messagerie'),
            'direction' => MessagerieAcces::estDirection(),
        ];
    }

    /**
     * Rendu une fois par page, au bas du gabarit : la configuration lue par le
     * JavaScript, les icônes, la zone des notifications et le panneau latéral
     * (vide tant qu'on ne l'ouvre pas).
     *
     * @param array{api:string,csrf:string,moi:int,moi_nom:string,connexion:string,plein:string,direction:bool} $contexte
     */
    public static function socle(array $contexte, bool $avecPanneau = true): string
    {
        $config = '<div hidden data-msg-config' . Html::attrs([
            'data-api' => $contexte['api'],
            'data-csrf' => $contexte['csrf'],
            'data-moi' => (string) $contexte['moi'],
            'data-moi-nom' => $contexte['moi_nom'],
            'data-connexion' => $contexte['connexion'],
            'data-plein' => $contexte['plein'],
            'data-direction' => $contexte['direction'] ? '1' : '0',
        ]) . '></div>';

        $panneau = '';
        if ($avecPanneau) {
            $panneau = '<section class="msg-tiroir" data-msg-tiroir hidden role="dialog" aria-modal="false" aria-label="Messagerie">'
                . '<header class="msg-tiroir-tete">'
                . '<strong class="msg-tiroir-titre">' . self::icone('discussion') . '<span>Messagerie</span></strong>'
                . '<a class="msg-icone-btn" href="' . View::e($contexte['plein']) . '" title="Ouvrir en plein écran" aria-label="Ouvrir la messagerie en plein écran">' . self::icone('plein-ecran') . '</a>'
                . '<button type="button" class="msg-icone-btn" data-msg-tiroir-fermer title="Fermer" aria-label="Fermer la messagerie">' . self::icone('fermer') . '</button>'
                . '</header>'
                . '<div class="msg-tiroir-corps" data-msg-tiroir-corps></div>'
                . '</section>'
                . '<template data-msg-gabarit>' . self::application('panneau') . '</template>';
        }

        return $config
            . self::sprite()
            . '<div class="msg-toasts" data-msg-toasts role="status" aria-live="polite"></div>'
            . $panneau;
    }

    /**
     * Bouton « Messagerie » de l'en-tête. Sans JavaScript, c'est un lien vers
     * l'écran complet ; avec, il ouvre le panneau latéral.
     */
    public static function boutonEntete(string $urlPleinEcran, bool $surLaMessagerie = false): string
    {
        return '<a class="msg-entete-btn' . ($surLaMessagerie ? ' is-active' : '') . '" href="' . View::e($urlPleinEcran) . '"'
            . ($surLaMessagerie ? ' aria-current="page"' : ' data-msg-ouvrir')
            . ' aria-label="Messagerie" title="Messagerie">'
            . self::icone('discussion')
            . '<span class="msg-entete-libelle">Messagerie</span>'
            . '<span class="msg-pastille" data-msg-total hidden>0</span>'
            . '</a>';
    }

    /** L'écran complet de /messagerie. */
    public static function ecran(): string
    {
        return '<div class="msg-page">' . self::application('plein') . '</div>';
    }

    /**
     * La charpente commune à l'écran et au panneau.
     *
     * @param 'plein'|'panneau' $mode
     */
    public static function application(string $mode): string
    {
        $mode = $mode === 'panneau' ? 'panneau' : 'plein';

        return '<div class="msg-app msg-app--' . $mode . '" data-msg-app data-mode="' . $mode . '" data-vue="liste">'
            . '<div class="msg-alerte" data-msg="alerte" role="alert" hidden></div>'
            . '<div class="msg-cadre">'
            . self::colonneListe()
            . self::colonneFil()
            . '<aside class="msg-infos" data-msg="infos" hidden aria-label="Informations sur la discussion"></aside>'
            . '</div>'
            . self::dialogues()
            . '</div>';
    }

    /** Une icône du jeu de la messagerie, décorative (le libellé est ailleurs). */
    public static function icone(string $nom, string $classe = ''): string
    {
        if (!isset(self::ICONES[$nom])) {
            return '';
        }

        return '<svg class="msg-i' . ($classe !== '' ? ' ' . View::e($classe) : '') . '" aria-hidden="true" focusable="false"><use href="#msg-i-' . View::e($nom) . '"></use></svg>';
    }

    /** @return array<int, string> les noms d'icônes connus */
    public static function nomsIcones(): array
    {
        return array_keys(self::ICONES);
    }

    // ------------------------------------------------------------------
    // Parties de la charpente
    // ------------------------------------------------------------------

    private static function sprite(): string
    {
        $symboles = '';
        foreach (self::ICONES as $nom => $trace) {
            $symboles .= '<symbol id="msg-i-' . $nom . '" viewBox="0 0 24 24">' . $trace . '</symbol>';
        }

        return '<svg class="msg-sprite" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">' . $symboles . '</svg>';
    }

    private static function colonneListe(): string
    {
        $filtres = '';
        foreach (['toutes' => 'Toutes', 'non_lues' => 'Non lues', 'groupes' => 'Groupes', 'archivees' => 'Archivées'] as $cle => $libelle) {
            $filtres .= '<button type="button" class="msg-filtre" data-msg-filtre="' . $cle . '" aria-pressed="' . ($cle === 'toutes' ? 'true' : 'false') . '">'
                . View::e($libelle) . '</button>';
        }

        return '<section class="msg-liste" data-msg="liste" aria-label="Discussions">'
            . '<header class="msg-liste-tete">'
            . '<h2 class="msg-liste-titre">Discussions</h2>'
            . '<div class="msg-liste-actions">'
            . self::boutonIcone('nouvelle', 'Nouvelle discussion', ['data-msg-action' => 'nouvelle-discussion'])
            . self::boutonIcone('groupe', 'Nouveau groupe', ['data-msg-action' => 'nouveau-groupe'])
            . self::boutonIcone('reglages', 'Mes réglages', ['data-msg-action' => 'reglages'])
            . '</div>'
            . '</header>'
            . '<div class="msg-recherche" role="search">'
            . self::icone('recherche')
            . '<input type="search" class="msg-recherche-champ" data-msg="recherche-liste" placeholder="Rechercher une discussion ou un message" aria-label="Rechercher une discussion ou un message" autocomplete="off" maxlength="80">'
            . '</div>'
            . '<div class="msg-filtres" role="group" aria-label="Filtrer les discussions">' . $filtres . '</div>'
            . '<div class="msg-liste-defile" data-msg="liste-defile">'
            . '<ul class="msg-convs" data-msg="convs" aria-label="Liste des discussions"></ul>'
            . '<div class="msg-liste-etat" data-msg="liste-etat">'
            . '<span class="msg-chargement" aria-hidden="true"></span><p>Chargement des discussions…</p>'
            . '</div>'
            . '<section class="msg-resultats" data-msg="resultats" hidden aria-label="Messages trouvés"></section>'
            . '</div>'
            . '</section>';
    }

    private static function colonneFil(): string
    {
        return '<section class="msg-fil" data-msg="fil" aria-label="Conversation">'
            . '<div class="msg-fil-accueil" data-msg="accueil">'
            . '<span class="msg-fil-accueil-icone">' . self::icone('discussion') . '</span>'
            . '<h2>Messagerie LBP</h2>'
            . '<p>Choisissez une discussion à gauche, ou commencez-en une avec un collègue.</p>'
            . '<button type="button" class="msg-btn msg-btn--primaire" data-msg-action="nouvelle-discussion">' . self::icone('nouvelle') . '<span>Nouvelle discussion</span></button>'
            . '</div>'
            . '<div class="msg-fil-contenu" data-msg="conversation" hidden>'
            . '<header class="msg-fil-tete">'
            . self::boutonIcone('retour', 'Retour aux discussions', ['data-msg-action' => 'retour', 'class' => 'msg-retour'])
            . '<button type="button" class="msg-fil-identite" data-msg-action="infos" aria-label="Voir les informations de la discussion">'
            . '<span class="msg-avatar" data-msg="tete-avatar" aria-hidden="true"></span>'
            . '<span class="msg-fil-noms"><strong data-msg="tete-nom"></strong><small data-msg="tete-sous-titre"></small></span>'
            . '</button>'
            . '<span class="msg-lecture-seule" data-msg="tete-direction" hidden title="Vous consultez cette discussion au titre de la Direction, sans en être membre. L’ouverture est inscrite au journal.">'
            . self::icone('oeil') . '<span>Lecture seule</span></span>'
            . '<div class="msg-fil-actions">'
            . self::boutonIcone('recherche', 'Rechercher dans la discussion', ['data-msg-action' => 'recherche-fil'])
            . self::boutonIcone('infos', 'Informations sur la discussion', ['data-msg-action' => 'infos'])
            . '</div>'
            . '</header>'
            . '<div class="msg-epingles" data-msg="epingles" hidden></div>'
            . '<div class="msg-recherche-fil" data-msg="recherche-fil" hidden role="search">'
            . self::icone('recherche')
            . '<input type="search" class="msg-recherche-champ" data-msg="recherche-fil-champ" placeholder="Rechercher dans cette discussion" aria-label="Rechercher dans cette discussion" maxlength="80">'
            . '<span class="msg-recherche-fil-compte" data-msg="recherche-fil-compte" aria-live="polite"></span>'
            . self::boutonIcone('fermer', 'Fermer la recherche', ['data-msg-action' => 'fermer-recherche-fil'])
            . '</div>'
            . '<div class="msg-messages" data-msg="messages" role="log" aria-live="polite" aria-relevant="additions" aria-label="Messages" tabindex="0"></div>'
            . '<button type="button" class="msg-vers-bas" data-msg-action="vers-bas" hidden aria-label="Aller aux derniers messages">'
            . self::icone('fleche-bas') . '<span class="msg-pastille" data-msg="vers-bas-compte" hidden></span>'
            . '</button>'
            . self::saisie()
            . '</div>'
            . '<div class="msg-depot" data-msg="depot" hidden>'
            . '<div class="msg-depot-cadre">' . self::icone('televerser')
            . '<strong>Déposez vos fichiers pour les joindre</strong>'
            . '<span>10 fichiers au plus, 25 Mo chacun</span></div>'
            . '</div>'
            . '</section>';
    }

    private static function saisie(): string
    {
        return '<footer class="msg-saisie-zone" data-msg="saisie-zone">'
            . '<div class="msg-saisie-contexte" data-msg="saisie-contexte" hidden>'
            . '<span class="msg-saisie-contexte-icone" data-msg="saisie-contexte-icone"></span>'
            . '<span class="msg-saisie-contexte-texte"><strong data-msg="saisie-contexte-titre"></strong><span data-msg="saisie-contexte-apercu"></span></span>'
            . self::boutonIcone('fermer', 'Annuler', ['data-msg-action' => 'annuler-contexte'])
            . '</div>'
            . '<section class="msg-plateau" data-msg="plateau" hidden aria-label="Fichiers à envoyer"></section>'
            . '<form class="msg-saisie" data-msg="saisie" novalidate>'
            . '<button type="button" class="msg-icone-btn msg-trombone" data-msg-action="joindre" aria-haspopup="true" title="Joindre des fichiers" aria-label="Joindre des fichiers : 10 au plus, 25 Mo chacun">' . self::icone('trombone') . '</button>'
            . '<input type="file" class="msg-fichier-champ" data-msg="fichier-choix" multiple hidden tabindex="-1" aria-hidden="true" accept="' . self::ACCEPT_FICHIERS . '">'
            . '<input type="file" class="msg-fichier-champ" data-msg="fichier-galerie" multiple hidden tabindex="-1" aria-hidden="true" accept="' . self::ACCEPT_IMAGES . '">'
            . '<input type="file" class="msg-fichier-champ" data-msg="fichier-photo" hidden tabindex="-1" aria-hidden="true" accept="image/*" capture="environment">'
            . '<textarea class="msg-saisie-champ" data-msg="saisie-champ" rows="1" maxlength="4000" placeholder="Écrire un message" aria-label="Écrire un message. Entrée pour envoyer, Maj + Entrée pour aller à la ligne"></textarea>'
            . '<button type="submit" class="msg-envoyer" data-msg="envoyer" aria-label="Envoyer" title="Envoyer (Entrée)">' . self::icone('envoyer') . '</button>'
            . '</form>'
            . '<p class="msg-saisie-fermee" data-msg="saisie-fermee" hidden>' . self::icone('cadenas') . '<span data-msg="saisie-fermee-texte">Seuls les administrateurs peuvent écrire</span></p>'
            . '</footer>';
    }

    private static function dialogues(): string
    {
        return self::dialogue('annuaire', 'Nouvelle discussion',
            '<div class="msg-recherche">' . self::icone('recherche')
            . '<input type="search" class="msg-recherche-champ" data-msg="annuaire-champ" placeholder="Nom, agence ou fonction" aria-label="Chercher un collègue" autocomplete="off" maxlength="80"></div>'
            . '<ul class="msg-choix" data-msg="annuaire-liste" aria-label="Collègues"></ul>')
            . self::dialogue('groupe', 'Nouveau groupe',
                '<form class="msg-form" data-msg="groupe-form" novalidate>'
                . '<label class="msg-champ"><span>Nom du groupe</span><input type="text" data-msg="groupe-nom" required maxlength="120" autocomplete="off" placeholder="Ex. : Export aérien Dokui"></label>'
                . '<label class="msg-champ"><span>Description <small>(facultative)</small></span><textarea data-msg="groupe-description" rows="2" maxlength="500"></textarea></label>'
                . '<fieldset class="msg-champ msg-champ--membres"><legend>Membres</legend>'
                . '<div class="msg-jetons" data-msg="groupe-jetons" aria-live="polite"></div>'
                . '<div class="msg-recherche">' . self::icone('recherche')
                . '<input type="search" class="msg-recherche-champ" data-msg="groupe-recherche" placeholder="Ajouter des collègues" aria-label="Chercher des collègues à ajouter" autocomplete="off" maxlength="80"></div>'
                . '<ul class="msg-choix msg-choix--court" data-msg="groupe-annuaire" aria-label="Collègues"></ul>'
                . '</fieldset>'
                . '<p class="msg-erreur" data-msg="groupe-erreur" role="alert" hidden></p>'
                . '<div class="msg-form-actions"><button type="button" class="msg-btn" data-msg-fermer-dialogue>Annuler</button>'
                . '<button type="submit" class="msg-btn msg-btn--primaire">Créer le groupe</button></div>'
                . '</form>')
            . self::dialogue('transfert', 'Transférer le message',
                '<p class="msg-dialogue-aide">Choisissez la discussion qui recevra une copie du message.</p>'
                . '<ul class="msg-choix" data-msg="transfert-liste" aria-label="Discussions"></ul>')
            . self::dialogue('lu-par', 'Lu par', '<div data-msg="lu-par-contenu"></div>')
            . self::dialogue('ajout-membres', 'Ajouter des membres',
                '<form class="msg-form" data-msg="ajout-form" novalidate>'
                . '<div class="msg-jetons" data-msg="ajout-jetons" aria-live="polite"></div>'
                . '<div class="msg-recherche">' . self::icone('recherche')
                . '<input type="search" class="msg-recherche-champ" data-msg="ajout-recherche" placeholder="Chercher des collègues" aria-label="Chercher des collègues à ajouter" autocomplete="off" maxlength="80"></div>'
                . '<ul class="msg-choix msg-choix--court" data-msg="ajout-annuaire" aria-label="Collègues"></ul>'
                . '<p class="msg-erreur" data-msg="ajout-erreur" role="alert" hidden></p>'
                . '<div class="msg-form-actions"><button type="button" class="msg-btn" data-msg-fermer-dialogue>Annuler</button>'
                . '<button type="submit" class="msg-btn msg-btn--primaire">Ajouter</button></div>'
                . '</form>')
            . self::dialogue('renommer', 'Modifier le groupe',
                '<form class="msg-form" data-msg="renommer-form" novalidate>'
                . '<label class="msg-champ"><span>Nom du groupe</span><input type="text" data-msg="renommer-nom" required maxlength="120" autocomplete="off"></label>'
                . '<label class="msg-champ"><span>Description <small>(facultative)</small></span><textarea data-msg="renommer-description" rows="3" maxlength="500"></textarea></label>'
                . '<p class="msg-erreur" data-msg="renommer-erreur" role="alert" hidden></p>'
                . '<div class="msg-form-actions"><button type="button" class="msg-btn" data-msg-fermer-dialogue>Annuler</button>'
                . '<button type="submit" class="msg-btn msg-btn--primaire">Enregistrer</button></div>'
                . '</form>')
            . self::dialogue('reglages', 'Mes réglages',
                '<div class="msg-reglages">'
                . self::interrupteur('presence_masquee', 'Masquer ma présence', 'Vos collègues ne voient ni « en ligne » ni l’heure de votre dernier passage.')
                . self::interrupteur('son_actif', 'Son à l’arrivée d’un message', 'Un son discret, sauf pour les discussions en sourdine.')
                . self::interrupteur('apercu_masque', 'Masquer l’aperçu des messages', 'La liste des discussions n’affiche plus le début des messages, utile sur un écran partagé.')
                . '</div>'
                . '<p class="msg-erreur" data-msg="reglages-erreur" role="alert" hidden></p>')
            . self::dialogue('confirmer', 'Confirmer',
                '<p data-msg="confirmer-texte"></p>'
                . '<div class="msg-form-actions"><button type="button" class="msg-btn" data-msg-fermer-dialogue>Annuler</button>'
                . '<button type="button" class="msg-btn msg-btn--danger" data-msg="confirmer-ok">Confirmer</button></div>');
    }

    private static function dialogue(string $nom, string $titre, string $corps): string
    {
        return '<dialog class="msg-dialogue" data-msg-dialogue="' . View::e($nom) . '" aria-label="' . View::e($titre) . '">'
            . '<div class="msg-dialogue-cadre">'
            . '<header class="msg-dialogue-tete"><h3>' . View::e($titre) . '</h3>'
            . self::boutonIcone('fermer', 'Fermer', ['data-msg-fermer-dialogue' => true])
            . '</header>'
            . '<div class="msg-dialogue-corps">' . $corps . '</div>'
            . '</div>'
            . '</dialog>';
    }

    private static function interrupteur(string $cle, string $libelle, string $aide): string
    {
        return '<label class="msg-interrupteur">'
            . '<span class="msg-interrupteur-texte"><strong>' . View::e($libelle) . '</strong><small>' . View::e($aide) . '</small></span>'
            . '<input type="checkbox" role="switch" data-msg-preference="' . View::e($cle) . '">'
            . '<span class="msg-interrupteur-piste" aria-hidden="true"></span>'
            . '</label>';
    }

    /** @param array<string, mixed> $attributs */
    private static function boutonIcone(string $icone, string $libelle, array $attributs = []): string
    {
        $classe = trim('msg-icone-btn ' . (string) ($attributs['class'] ?? ''));
        unset($attributs['class']);

        return '<button type="button"' . Html::attrs(['class' => $classe, 'title' => $libelle, 'aria-label' => $libelle] + $attributs) . '>'
            . self::icone($icone)
            . '</button>';
    }
}
