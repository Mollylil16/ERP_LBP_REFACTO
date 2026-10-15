<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

/**
 * Page de connexion : le panneau de gauche.
 *
 * Le formulaire est la seule chose utile de cet ecran ; le panneau de gauche ne
 * sert qu'a dire, en une seconde, de quel logiciel il s'agit. On y raconte donc
 * le metier de l'entreprise plutot que de le decrire : un agent saisit a un
 * comptoir, les colis partent sur le convoyeur, l'avion decolle vers Paris.
 * C'est la ligne Abidjan - France, qui porte 93 % du volume reel.
 *
 * Tout le decor est du SVG en ligne : aucune image a telecharger, et le trait
 * suit la palette de marque (marine, bleu ciel, jaune LBP) au lieu de la subir.
 */
final class Auth
{
    /**
     * Quatre arguments, quatre icones. Le libelle vient d'abord : l'icone n'est
     * qu'un reperage visuel, elle ne porte aucune information a elle seule.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const ARGUMENTS = [
        [
            'Gestion complète du transit et douane',
            '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>'
                . '<polyline points="14 2 14 8 20 8"></polyline>'
                . '<polyline points="9 15 11 17 15 12"></polyline>',
        ],
        [
            'Suivi des colis et expéditions en temps réel',
            '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>'
                . '<polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>'
                . '<line x1="12" y1="22.08" x2="12" y2="12"></line>',
        ],
        [
            'Pilotage financier et ressources humaines',
            '<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>'
                . '<polyline points="17 6 23 6 23 12"></polyline>',
        ],
        [
            'Tableau de bord multi-modules',
            '<rect x="3" y="3" width="7" height="7" rx="1"></rect>'
                . '<rect x="14" y="3" width="7" height="7" rx="1"></rect>'
                . '<rect x="14" y="14" width="7" height="7" rx="1"></rect>'
                . '<rect x="3" y="14" width="7" height="7" rx="1"></rect>',
        ],
    ];

    public static function panneauVisuel(): string
    {
        $logo = View::asset('images/logo-lbp.png');

        $html = '<div class="auth-visual-panel">';
        $html .= self::decor();

        // Le contenu est regroupe pour passer d'un bloc au-dessus du decor :
        // un seul z-index a tenir, au lieu d'un par element.
        $html .= '<div class="auth-visual-content">'
            . '<div class="auth-visual-logo">'
            . '<img src="' . View::e($logo) . '" alt="La Belle Porte - LBP CI" class="auth-logo-img">'
            . '</div>'
            . '<div class="auth-badge">ERP LBP Transit</div>'
            . '<h1>La Belle Porte<br><span class="auth-hero-accent">Côte d\'Ivoire</span></h1>'
            . '<p>Votre plateforme de gestion intégrée pour le transit, la logistique '
            . 'et les opérations douanières.</p>'
            . self::arguments()
            . '</div>';

        return $html . '</div>';
    }

    private static function arguments(): string
    {
        $html = '<ul class="auth-highlights">';

        foreach (self::ARGUMENTS as [$libelle, $icone]) {
            $html .= '<li>'
                . '<span class="auth-highlight-icon" aria-hidden="true">'
                . '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"'
                . ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $icone . '</svg>'
                . '</span>'
                . '<span>' . View::e($libelle) . '</span>'
                . '</li>';
        }

        return $html . '</ul>';
    }

    /**
     * Le decor, en deux calques.
     *
     * Deux SVG plutot qu'un seul en plein cadre : chacun s'accroche a son coin
     * et garde ses proportions. Un unique calque etire sur tout le panneau
     * deformerait l'avion, ou se ferait rogner le convoyeur sur un ecran large
     * et court — ce qui arrive des 1600x700.
     */
    private static function decor(): string
    {
        return '<div class="auth-scene" aria-hidden="true">'
            . self::calqueCiel()
            . self::calqueSol()
            . '</div>';
    }

    /**
     * Ciel : le globe, la trajectoire en pointilles, l'avion.
     *
     * Le trace est une courbe de Bezier fixe ; les positions de l'avion dans
     * l'animation CSS sont les points de cette meme courbe, calcules une fois.
     * Toucher le « d » du chemin sans refaire les keyframes ferait voler l'avion
     * a cote de sa route.
     */
    private static function calqueCiel(): string
    {
        $globe = '<g class="auth-ink-faint">'
            . '<circle cx="560" cy="110" r="150"></circle>'
            . '<ellipse cx="560" cy="110" rx="52" ry="150"></ellipse>'
            . '<ellipse cx="560" cy="110" rx="105" ry="150"></ellipse>'
            . '<line x1="430" y1="35" x2="690" y2="35"></line>'
            . '<line x1="410" y1="110" x2="710" y2="110"></line>'
            . '<line x1="430" y1="185" x2="690" y2="185"></line>'
            . '</g>';

        // Les deux anneaux qui pulsaient deja sur cette page sont conserves,
        // mais recentres sur le globe : ils cessent d'etre un ornement libre
        // pour devenir son halo.
        $anneaux = '<circle class="auth-ring auth-ring--gold" cx="560" cy="110" r="190"></circle>'
            . '<circle class="auth-ring auth-ring--sky" cx="560" cy="110" r="220"></circle>';

        /*
         * La courbe longe le bas du panneau avant de monter : c'est un
         * decollage, et c'est surtout ce qui la tient hors du texte. Une
         * diagonale franche d'un coin a l'autre passait derriere le titre et
         * les arguments, et l'avion y traversait les mots.
         *
         * La montee quasi verticale n'est pas une licence : Abidjan est a 5°N,
         * Paris a 49°N, presque plein nord. La route dessinee est la vraie.
         */
        $route = '<path class="auth-route" d="M55 700 C300 690 400 480 540 150"></path>';

        $escales = '<circle class="auth-pulse auth-pulse--gold" cx="55" cy="700" r="13"></circle>'
            . '<circle class="auth-dot auth-dot--gold" cx="55" cy="700" r="3.6"></circle>'
            . '<circle class="auth-pulse auth-pulse--sky" cx="540" cy="150" r="13"></circle>'
            . '<circle class="auth-dot auth-dot--sky" cx="540" cy="150" r="3.6"></circle>'
            . '<text class="auth-scene-label" x="83" y="668">ABIDJAN</text>'
            . '<text class="auth-scene-label" x="516" y="132" text-anchor="end">PARIS</text>';

        // L'icone pointe deja vers le haut a droite, soit l'orientation de la
        // route : aucune rotation de base, les keyframes ne corrigent que le
        // roulis. Le groupe interieur porte le centrage pour que la rotation
        // CSS du groupe exterieur ne l'ecrase pas.
        $avion = '<g class="auth-plane"><g transform="scale(1.4) translate(-12,-12)">'
            . '<line x1="22" y1="2" x2="11" y2="13"></line>'
            . '<polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>'
            . '</g></g>';

        // « xMaxYMid » : quand le calque est borne en largeur sur un ecran
        // etroit, le dessin reste colle au bord droit au lieu de se recentrer
        // en laissant le globe flotter au milieu du panneau.
        return '<svg class="auth-scene-sky" viewBox="0 0 640 760" fill="none"'
            . ' preserveAspectRatio="xMaxYMid meet"'
            . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
            . $globe . $anneaux . $route . $escales . $avion
            . '</svg>';
    }

    /**
     * Sol : le comptoir, l'agent qui saisit, le convoyeur et ses colis.
     *
     * En silhouette au trait, et volontairement plus sombre que le ciel : c'est
     * le point de depart de l'histoire, pas son sujet. Les colis avancent vers
     * l'endroit ou la trajectoire decolle.
     */
    private static function calqueSol(): string
    {
        $accueil = '<g class="auth-ink-soft">'
            . '<circle cx="48" cy="58" r="11"></circle>'
            . '<path d="M26 94a22 22 0 0 1 44 0"></path>'
            . '<path d="M62 86 78 80"></path>'
            . '<path d="M14 132V94h102v38"></path>'
            . '<line x1="8" y1="94" x2="122" y2="94"></line>'
            . '</g>'
            . '<g class="auth-ink-sky">'
            . '<rect x="80" y="70" width="24" height="18" rx="2.5"></rect>'
            . '<line x1="92" y1="88" x2="92" y2="94"></line>'
            . '</g>';

        // Rouleaux dessines un par un : une boucle PHP evite de recopier
        // trente cercles a la main, et de se tromper d'un pas en les relisant.
        $rouleaux = '';
        for ($x = 152; $x <= 864; $x += 24) {
            $rouleaux .= '<circle cx="' . $x . '" cy="123" r="9"></circle>';
        }

        // Le tapis a deux pieds : sans eux, il se termine sur un trait coupe net
        // et ressemble a un trace inacheve plutot qu'a une machine.
        $convoyeur = '<g class="auth-ink-faint">'
            . '<line x1="0" y1="140" x2="900" y2="140"></line>'
            . '<line x1="140" y1="114" x2="876" y2="114"></line>'
            . '<line x1="142" y1="114" x2="142" y2="140"></line>'
            . '<line x1="874" y1="114" x2="874" y2="140"></line>'
            . $rouleaux
            . '</g>';

        $carton = '<rect x="-18" y="-30" width="36" height="30" rx="2.5"></rect>'
            . '<line x1="-18" y1="-21" x2="18" y2="-21"></line>'
            . '<line x1="0" y1="-30" x2="0" y2="-21"></line>';

        // Le carton est dessine autour de (0,0) et pose sur la ligne du tapis :
        // le groupe n'a plus qu'a etre translate, ce que fait l'animation.
        $colis = '';
        foreach ([1, 2, 3, 4] as $rang) {
            $colis .= '<g class="auth-colis auth-colis--' . $rang . '">' . $carton . '</g>';
        }

        /*
         * Le cadre est large (900 x 150) pour une raison de proportion : ce
         * calque est dimensionne sur la largeur du panneau, et avec un cadre
         * plus carre il grossissait sur un ecran large et court — a 1600x720
         * le comptoir venait buter contre le dernier argument de la liste.
         */
        return '<svg class="auth-scene-ground" viewBox="0 0 900 150" fill="none"'
            . ' preserveAspectRatio="xMinYMax meet"'
            . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
            . $convoyeur . $accueil . $colis
            . '</svg>';
    }
}
