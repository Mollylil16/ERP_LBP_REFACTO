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

        // « xMaxYMid » : quand le calque est borne en largeur sur un ecran
        // etroit, le dessin reste colle au bord droit au lieu de se recentrer
        // en laissant le globe flotter au milieu du panneau.
        return '<svg class="auth-scene-sky" viewBox="0 0 640 760" fill="none"'
            . ' preserveAspectRatio="xMaxYMid meet"'
            . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
            . $globe . $anneaux . self::reseau()
            . '</svg>';
    }

    /**
     * Le reseau reel : Abidjan en hub, Paris, Dakar, le Canada.
     *
     * Ce sont les liaisons du catalogue de production, pas une carte d'humeur :
     * Abidjan - Paris (aerien et express, 93 % du volume), Abidjan - Dakar,
     * Abidjan - Canada, et Dakar - Paris. Le Canada est desservi sans y avoir
     * d'agence, d'ou son point creux : une escale, pas un bureau. Aucune ville
     * canadienne n'apparait dans les donnees, le libelle reste « CANADA ».
     *
     * Toutes les routes partent vers la droite en longeant le bas du panneau
     * avant de monter. Ce n'est pas la geographie, c'est la seule forme qui
     * laisse la colonne de texte intacte : une branche qui viserait le haut a
     * gauche traverserait le titre et les arguments. Les trois branches sont
     * emboitees, de la plus redressee (Paris) a la plus basse (Canada), et ne
     * se croisent donc jamais.
     *
     * Tout est reuni dans un seul groupe pour qu'une regle suffise a le retirer
     * sur ecran etroit, ou il n'y a plus la hauteur de le lire.
     */
    private static function reseau(): string
    {
        /*
         * La route principale longe le bas du panneau avant de monter : c'est
         * un decollage, et c'est surtout ce qui la tient hors du texte. Une
         * diagonale franche d'un coin a l'autre passait derriere le titre, et
         * l'avion y traversait les mots.
         *
         * La montee quasi verticale n'est pas une licence : Abidjan est a 5°N,
         * Paris a 49°N, presque plein nord. Cette route-la est la vraie.
         */
        $routes = '<path class="auth-route" d="M55 700 C300 690 400 480 540 150"></path>'
            . '<path class="auth-route auth-route--second" d="M55 700 C280 712 420 640 470 420"></path>'
            . '<path class="auth-route auth-route--second" d="M470 420 Q530 300 540 150"></path>'
            . '<path class="auth-route auth-route--tiers" d="M55 700 C250 700 400 690 520 610"></path>';

        // Seuls les deux bouts de l'axe majeur battent. Faire pulser les quatre
        // points transformait le panneau en sapin de Noel : les escales
        // secondaires n'ont qu'un anneau fixe.
        $escales = '<circle class="auth-pulse auth-pulse--gold" cx="55" cy="700" r="13"></circle>'
            . '<circle class="auth-dot auth-dot--gold" cx="55" cy="700" r="4.4"></circle>'
            . '<circle class="auth-pulse auth-pulse--sky" cx="540" cy="150" r="13"></circle>'
            . '<circle class="auth-dot auth-dot--sky" cx="540" cy="150" r="3.6"></circle>'
            . '<circle class="auth-escale" cx="470" cy="420" r="8"></circle>'
            . '<circle class="auth-dot auth-dot--sky" cx="470" cy="420" r="3.2"></circle>'
            . '<circle class="auth-escale auth-escale--legere" cx="520" cy="610" r="5.5"></circle>';

        $libelles = '<text class="auth-scene-label auth-scene-label--hub" x="83" y="668">ABIDJAN</text>'
            . '<text class="auth-scene-label" x="516" y="132" text-anchor="end">PARIS</text>'
            . '<text class="auth-scene-label" x="446" y="412" text-anchor="end">DAKAR</text>'
            . '<text class="auth-scene-label auth-scene-label--legere" x="496" y="602"'
            . ' text-anchor="end">CANADA</text>';

        /*
         * La silhouette est vue de dessus et pointe vers le haut, pas vers le
         * haut a droite comme le chevron qu'elle remplace. Les angles de
         * « authVol » ont ete refaits en consequence : ils valent desormais
         * l'angle de la tangente + 90°. Reprendre l'un sans l'autre ferait
         * voler l'avion en crabe.
         *
         * Le groupe interieur porte le centrage et l'echelle en attribut, pour
         * que la transformation CSS du groupe exterieur ne les ecrase pas.
         */
        $avion = '<g class="auth-plane"><g transform="scale(1.3) translate(-12,-12)">'
            . '<path d="M12 2c1.2 0 2 1.3 2 3v6l8 5v2l-8-2.5V20l3 2v1.5L12 22.2 7 23.5V22l3-2'
            . 'v-4.5L2 18v-2l8-5V5c0-1.7.8-3 2-3z"></path>'
            . '</g></g>';

        return '<g class="auth-reseau">' . $routes . $escales . $libelles . $avion . '</g>';
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
        /*
         * L'accueil se lit de gauche a droite, dans l'ordre reel du guichet :
         * le client pose son colis sur le comptoir, l'agent le saisit a son
         * poste, le colis repart sur le convoyeur. Il manquait le client — la
         * scene montrait un agent seul derriere un comptoir vide, ce qui ne
         * raconte pas une entreprise d'accueil.
         *
         * Les deux silhouettes ont la meme carrure : ce sont deux personnes,
         * l'une devant, l'autre derriere, et c'est le comptoir entre elles qui
         * dit lequel travaille la.
         */
        $client = '<circle cx="38" cy="58" r="11"></circle>'
            . '<path d="M16 94a22 22 0 0 1 44 0"></path>'
            . '<path d="M56 86 68 88"></path>';

        // Le colis depose a la meme ecriture que ceux du convoyeur : c'est le
        // meme objet, trois metres plus loin dans son parcours.
        $colisDepose = '<rect x="70" y="72" width="26" height="22" rx="2.5"></rect>'
            . '<line x1="70" y1="80" x2="96" y2="80"></line>'
            . '<line x1="83" y1="72" x2="83" y2="80"></line>';

        $agent = '<circle cx="154" cy="58" r="11"></circle>'
            . '<path d="M132 94a22 22 0 0 1 44 0"></path>'
            . '<path d="M140 86 128 80"></path>';

        $comptoir = '<path d="M66 132V94h126v38"></path>'
            . '<line x1="60" y1="94" x2="198" y2="94"></line>';

        $accueil = '<g class="auth-ink-soft">'
            . $client . $colisDepose . $comptoir . $agent
            . '</g>'
            . '<g class="auth-ink-sky">'
            . '<rect x="104" y="70" width="24" height="18" rx="2.5"></rect>'
            . '<line x1="116" y1="88" x2="116" y2="94"></line>'
            . '</g>';

        // Rouleaux dessines un par un : une boucle PHP evite de recopier
        // trente cercles a la main, et de se tromper d'un pas en les relisant.
        $rouleaux = '';
        for ($x = 218; $x <= 864; $x += 24) {
            $rouleaux .= '<circle cx="' . $x . '" cy="123" r="9"></circle>';
        }

        // Le tapis a deux pieds : sans eux, il se termine sur un trait coupe net
        // et ressemble a un trace inacheve plutot qu'a une machine. Il commence
        // apres le comptoir, la ou le colis saisi est pose.
        $convoyeur = '<g class="auth-ink-faint">'
            . '<line x1="0" y1="140" x2="900" y2="140"></line>'
            . '<line x1="206" y1="114" x2="876" y2="114"></line>'
            . '<line x1="208" y1="114" x2="208" y2="140"></line>'
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

    /**
     * Écran « Choisir votre mot de passe », seul écran ouvert tant que le
     * mot de passe provisoire n'est pas remplacé (App\Security\MotDePasseProvisoire).
     */
    public static function changementMotDePasse(string $nomComplet, bool $obligatoire): string
    {
        $titre = $obligatoire ? 'Choisissez votre mot de passe' : 'Changer votre mot de passe';
        $intro = $obligatoire
            ? 'Bonjour ' . $nomComplet . '. Vous vous êtes connecté avec un mot de passe provisoire. '
                . 'Choisissez maintenant le vôtre : vous seul le connaîtrez.'
            : 'Choisissez un nouveau mot de passe, que vous seul connaîtrez.';

        return '<section class="auth-page">'
            . self::panneauVisuel()
            . '<div class="auth-card">'
            . '<div class="auth-card-logo"><img src="' . View::asset('images/logo-lbp.png') . '" alt="LBP" class="auth-card-logo-img"></div>'
            . '<div class="auth-header">'
            . '<span class="auth-kicker">Sécurité du compte</span>'
            . '<h2>' . View::e($titre) . '</h2>'
            . '<p>' . View::e($intro) . '</p>'
            . '</div>'
            . '<form method="POST" action="' . View::url('compte/mot-de-passe') . '" class="auth-form">'
            . \App\Helpers\Csrf::input()
            . Form::input('actuel', [
                'label' => $obligatoire ? 'Mot de passe provisoire' : 'Mot de passe actuel',
                'type' => 'password', 'required' => true, 'autocomplete' => 'current-password',
            ])
            . Form::input('nouveau', [
                'label' => 'Nouveau mot de passe',
                'type' => 'password', 'required' => true, 'autocomplete' => 'new-password',
                'minlength' => \App\Security\MotDePasseProvisoire::LONGUEUR_MIN,
                'hint' => 'Au moins ' . \App\Security\MotDePasseProvisoire::LONGUEUR_MIN . ' caractères. Une phrase courte que vous retenez facilement fait un bon mot de passe.',
            ])
            . Form::input('confirmation', [
                'label' => 'Confirmez le nouveau mot de passe',
                'type' => 'password', 'required' => true, 'autocomplete' => 'new-password',
            ])
            . Ui::button('Enregistrer mon mot de passe', ['variant' => 'primary', 'type' => 'submit', 'class' => 'btn btn-primary btn-login'])
            . '</form>'
            . '<div class="auth-footer"><a href="' . View::url('logout') . '">Se déconnecter</a></div>'
            . '</div></section>';
    }
}
