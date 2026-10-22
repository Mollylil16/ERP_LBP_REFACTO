<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

/**
 * Écran « Documents » de la messagerie : le classeur de tous les fichiers
 * échangés dans les discussions dont on est membre.
 *
 * Comme l'écran des discussions, le PHP ne rend que la charpente — champs de
 * filtre, barre d'outils, zones vides — et public/assets/js/messagerie-documents.js
 * y place les documents reçus de l'API (/messagerie/api/documents). Les
 * listes « discussion » et « expéditeur » sont remplies par le JavaScript.
 *
 * Une seule instance par page : les identifiants servent aux étiquettes.
 */
final class MessagerieDocuments
{
    /** Catégories du serveur (MessagerieFormats::CATEGORIES) et leur libellé. */
    public const CATEGORIES = [
        'pdf' => 'PDF',
        'image' => 'Images',
        'word' => 'Word',
        'tableur' => 'Tableurs (Excel, CSV)',
        'presentation' => 'Présentations',
        'texte' => 'Texte',
    ];

    /** Colonnes de tri de l'API et leur libellé. */
    public const TRIS = [
        'date' => 'Date',
        'nom' => 'Nom',
        'taille' => 'Taille',
        'expediteur' => 'Expéditeur',
        'type' => 'Type',
    ];

    public static function ecran(): string
    {
        return '<div class="finea-shell"><div class="finea-container msg-docs-page">'
            . Ui::pageHeader(
                'Documents',
                'Tous les fichiers échangés dans vos discussions : retrouvez-les, ouvrez-les ou revenez au message qui les accompagne.',
                ['eyebrow' => 'Messagerie']
            )
            . '<section class="msg-docs" data-msg-documents aria-label="Classeur des documents">'
            . self::filtres()
            . self::barre()
            . '<div class="msg-docs-etat" data-docs="etat" role="status">'
            . '<span class="msg-chargement" aria-hidden="true"></span><p>Chargement des documents…</p>'
            . '</div>'
            . '<div class="msg-docs-resultats" data-docs="resultats" aria-live="polite" hidden></div>'
            . '<nav class="msg-docs-pages" data-docs="pagination" aria-label="Pages de résultats" hidden>'
            . '<button type="button" class="msg-btn" data-docs="page-precedente">' . Messagerie::icone('precedent') . '<span>Précédente</span></button>'
            . '<span class="msg-docs-page-num" data-docs="page-num"></span>'
            . '<button type="button" class="msg-btn" data-docs="page-suivante"><span>Suivante</span>' . Messagerie::icone('suivant') . '</button>'
            . '</nav>'
            . '<noscript><p class="msg-docs-noscript">Le classeur a besoin de JavaScript pour afficher les documents.</p></noscript>'
            . '</section>'
            . '</div></div>';
    }

    private static function filtres(): string
    {
        $categories = [['value' => '', 'label' => 'Tous les types']];
        foreach (self::CATEGORIES as $code => $libelle) {
            $categories[] = ['value' => $code, 'label' => $libelle];
        }

        return '<form class="msg-docs-filtres" data-docs="filtres" role="search" novalidate>'
            . '<div class="msg-docs-recherche">'
            . '<label class="msg-sr" for="msgdocs-q">Rechercher un document</label>'
            . Messagerie::icone('recherche')
            . '<input type="search" id="msgdocs-q" name="q" class="msg-docs-champ" data-docs="q" placeholder="Rechercher par nom ou légende" autocomplete="off" maxlength="100">'
            . '</div>'
            . '<button type="button" class="msg-btn msg-docs-plus" data-docs="basculer-filtres" aria-expanded="false" aria-controls="msgdocs-detail">'
            . Messagerie::icone('filtre') . '<span>Filtres</span><span class="msg-pastille" data-docs="nb-filtres" hidden>0</span>'
            . '</button>'
            . '<div class="msg-docs-detail" id="msgdocs-detail" data-docs="detail">'
            . Form::select('conversation_id', [['value' => '', 'label' => 'Toutes les discussions']], '', ['label' => 'Discussion', 'id' => 'msgdocs-conversation', 'data-docs' => 'conversation_id'])
            . Form::select('expediteur_id', [['value' => '', 'label' => 'Tous les expéditeurs']], '', ['label' => 'Expéditeur', 'id' => 'msgdocs-expediteur', 'data-docs' => 'expediteur_id'])
            . Form::select('categorie', $categories, '', ['label' => 'Type', 'id' => 'msgdocs-categorie', 'data-docs' => 'categorie'])
            . Form::input('du', ['label' => 'Du', 'type' => 'date', 'id' => 'msgdocs-du', 'data-docs' => 'du'])
            . Form::input('au', ['label' => 'Au', 'type' => 'date', 'id' => 'msgdocs-au', 'data-docs' => 'au'])
            . '<label class="msg-docs-favoris"><input type="checkbox" name="favoris" value="1" data-docs="favoris">'
            . '<span>' . Messagerie::icone('etoile') . 'Mes favoris seulement</span></label>'
            . '<button type="button" class="msg-lien msg-docs-effacer" data-docs="effacer" hidden>Effacer les filtres</button>'
            . '</div>'
            . '<p class="msg-erreur msg-docs-erreur" data-docs="erreur-filtres" role="alert" hidden></p>'
            . '</form>';
    }

    private static function barre(): string
    {
        $tris = '';
        foreach (self::TRIS as $code => $libelle) {
            $tris .= '<option value="' . View::e($code) . '">' . View::e($libelle) . '</option>';
        }

        return '<div class="msg-docs-barre">'
            . '<p class="msg-docs-compte" data-docs="compte" aria-live="polite"></p>'
            . '<div class="msg-docs-outils">'
            . '<label class="msg-docs-tri"><span>Trier par</span><select data-docs="tri" aria-label="Trier par">' . $tris . '</select></label>'
            . '<button type="button" class="msg-icone-btn msg-docs-ordre" data-docs="ordre" title="Ordre décroissant" aria-label="Ordre décroissant, cliquer pour inverser">' . Messagerie::icone('tri') . '</button>'
            . '<div class="msg-docs-vues" role="group" aria-label="Affichage">'
            . '<button type="button" class="msg-icone-btn" data-docs-vue="liste" aria-pressed="true" title="Afficher en liste" aria-label="Afficher en liste">' . Messagerie::icone('liste') . '</button>'
            . '<button type="button" class="msg-icone-btn" data-docs-vue="vignettes" aria-pressed="false" title="Afficher en vignettes" aria-label="Afficher en vignettes">' . Messagerie::icone('vignettes') . '</button>'
            . '</div>'
            . '</div>'
            . '</div>';
    }
}
