<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

/**
 * NOEL — le visage de l'assistant interne.
 *
 * EN DÉVELOPPEMENT : ce composant ne dessine qu'un visage. Aucune route, aucun
 * menu, aucun écran ne l'appelle encore, et NOEL ne répond à personne à ce
 * jour. Ce qui est livré ici, c'est sa tête, à faire trancher avant de brancher
 * quoi que ce soit derrière.
 *
 * -------------------------------------------------------------------------
 * Le parti pris
 * -------------------------------------------------------------------------
 * La demande était un visage, pas un symbole d'IA. Donc ni étincelle à quatre
 * branches, ni robot, ni puce, ni orbe : quelqu'un. Et quelqu'un de précis —
 * NOEL travaille dans une maison de transit à Abidjan, il parle à des
 * caissières, des agents de guichet, des magasiniers. Il porte donc une
 * casquette et un polo de travail, comme les gens à qui il s'adresse.
 *
 * La casquette n'est pas un ornement, c'est la décision structurante. À 28 px
 * un visage ne garde que sa silhouette : deux yeux, une bouche, une masse. Une
 * tête nue dans un cercle donne la même tache ronde que tous les avatars du
 * monde. La visière, vue en trois-quarts, ressort en pointe du côté gauche et
 * casse le cercle : c'est à cela qu'on reconnaît NOEL dans un fil, de loin,
 * sans le lire.
 *
 * Ce qui a été écarté : les lunettes (une barre horizontale à petite taille,
 * et un air de comptable), le casque de téléphoniste (trop proche de l'image
 * du robot de hotline, et l'arceau se lit comme une auréole), et deux
 * casquettes vues strictement de face, dont on trouvera le détail plus bas,
 * au-dessus du tracé de la visière.
 *
 * -------------------------------------------------------------------------
 * Deux niveaux de détail, pas deux personnages
 * -------------------------------------------------------------------------
 * Le même visage est dessiné à deux finesses. Sous 48 px, les oreilles, le
 * nez, les éclats du regard et l'ourlet du col ne font plus qu'un demi-pixel
 * de bruit gris : ils sont retirés, ce qui rend le petit format plus net et
 * son balisage plus court — et ce balisage est répété des dizaines de fois
 * dans un fil de conversation. C'est le réglage optique habituel d'une icône,
 * pas un autre dessin.
 *
 * -------------------------------------------------------------------------
 * Les états
 * -------------------------------------------------------------------------
 * Quatre états, un seul dessin. Seuls les sourcils, la bouche et la direction
 * du regard changent : ce sont eux qui portent toute l'émotion d'un visage, et
 * c'est aussi tout ce qui reste lisible à 28 px. La casquette, la peau, le
 * polo ne bougent jamais d'un état à l'autre.
 *
 *   repos       sourcils horizontaux, bouche calme. Attentif, pas souriant.
 *   reflechit   un sourcil levé, lèvres pressées, regard de côté. Animé.
 *   content     sourcils relevés, sourire franc, yeux plissés.
 *   preoccupe   sourcils rentrés par l'intérieur, bouche aplatie, tête penchée.
 *
 * L'état « preoccupe » sert quand NOEL signale un oubli. Il est soucieux, pas
 * fâché : la personne a oublié de soumettre son point de caisse, elle n'a rien
 * fait de mal. D'où une bouche à peine tombante, et pas une grimace.
 *
 * -------------------------------------------------------------------------
 * Tenue de la maison
 * -------------------------------------------------------------------------
 * SVG en ligne, aucune image à télécharger, aucune bibliothèque. Les couleurs
 * ne sont pas écrites dans les formes mais portées par des classes, pour que
 * public/assets/css/noel.css décide seul du rendu en thème clair et en thème
 * sombre — sans avoir à régénérer le balisage.
 *
 * Cette feuille est donc obligatoire : le visage sans elle n'est qu'un
 * ensemble de formes sans couleur.
 *
 * Deux choses restent à la charge de l'écran qui l'appellera. D'abord, si le
 * visage sert un jour de bouton — ouvrir la conversation, par exemple —, c'est
 * au bouton de faire ses 44 px de côté : un avatar de 32 px n'est pas une
 * cible tactile. Ensuite, le visage ne dit jamais de lui-même ce que NOEL est
 * en train de faire ; il prend l'état qu'on lui donne, et c'est à l'appelant
 * de ne lui donner « content » que lorsque quelque chose s'est réellement bien
 * passé.
 */
final class Noel
{
    /** Les quatre états, dans l'ordre où on les montre. */
    public const ETATS = ['repos', 'reflechit', 'content', 'preoccupe'];

    /** Taille de l'avatar en bulle de conversation, en pixels. */
    public const TAILLE_AVATAR = 32;

    /** Taille de l'écran d'ouverture, en pixels. */
    public const TAILLE_ACCUEIL = 120;

    /**
     * En dessous de ce seuil, le dessin passe en version simplifiée.
     *
     * 48 px est le point où un trait d'un demi-pixel (le nez, l'ourlet du col)
     * cesse d'ajouter de la nuance et commence à ajouter du flou.
     */
    private const SEUIL_DETAIL = 48;

    /**
     * Sourcils et bouche : les seuls traits qui changent d'un état à l'autre.
     *
     * Les coordonnées sont dans la grille de 64 du viewBox. Les deux sourcils
     * sont écrits séparément plutôt que miroir l'un de l'autre : l'état
     * « reflechit » a justement besoin de les désaccorder, et un visage
     * parfaitement symétrique a toujours l'air figé.
     *
     * @var array<string, array{sourcilG: string, sourcilD: string, bouche: string}>
     */
    private const TRAITS = [
        'repos' => [
            'sourcilG' => 'M23.4 31.8 C24.8 30.9 27.7 30.9 29.3 31.6',
            'sourcilD' => 'M40.6 31.8 C39.2 30.9 36.3 30.9 34.7 31.6',
            // Une courbe très faible : NOEL écoute, il ne sourit pas encore.
            // Une bouche souriante au repos donnerait l'air d'une mascotte.
            'bouche' => 'M28.3 45.2 C30 46.6 34 46.6 35.7 45.2',
        ],
        'reflechit' => [
            // Le sourcil gauche monte, le droit descend : c'est la dissymétrie
            // qui dit « il cherche », bien avant la bouche.
            'sourcilG' => 'M23.3 30.5 C25 29.9 27.6 30 29.4 30.7',
            'sourcilD' => 'M40.7 32.4 C39.3 31.6 36.5 31.7 34.8 32.4',
            'bouche' => 'M29 45.9 C31 45.3 33.4 45.6 35.6 44.9',
        ],
        'content' => [
            'sourcilG' => 'M23.2 31.1 C24.7 29.8 27.8 29.9 29.5 30.9',
            'sourcilD' => 'M40.8 31.1 C39.3 29.8 36.2 29.9 34.5 30.9',
            'bouche' => 'M26.4 44.4 C29 47.9 35 47.9 37.6 44.4',
        ],
        'preoccupe' => [
            // Les extrémités intérieures remontent. C'est le marqueur
            // d'inquiétude le plus lisible qui existe, et il tient en deux
            // traits de six unités.
            'sourcilG' => 'M23.3 32.7 C24.9 31.2 27.6 30.7 29.4 31.3',
            'sourcilD' => 'M40.7 32.7 C39.1 31.2 36.4 30.7 34.6 31.3',
            'bouche' => 'M28.4 46 C30.1 45.3 33.9 45.3 35.6 46',
        ],
    ];

    /**
     * Ce que lit une personne à la synthèse vocale, état par état.
     *
     * Le libellé décrit ce que le visage veut dire, pas sa géométrie : « NOEL
     * réfléchit » est une information, « visage aux sourcils dissymétriques »
     * n'en est pas une.
     *
     * @var array<string, string>
     */
    private const LIBELLES = [
        'repos' => 'NOEL, votre assistant',
        'reflechit' => 'NOEL réfléchit',
        'content' => 'NOEL, satisfait',
        'preoccupe' => 'NOEL a quelque chose à signaler',
    ];

    /**
     * Le visage, à la taille et dans l'état demandés.
     *
     * @param string $etat Une valeur de self::ETATS. Toute autre retombe sur « repos »
     *                     plutôt que de casser l'écran : un état inconnu doit se voir
     *                     à la relecture du code, pas chez la caissière.
     * @param array{taille?: int, detail?: string, titre?: string|null, classe?: string, decoratif?: bool} $options
     */
    public static function visage(string $etat = 'repos', array $options = []): string
    {
        $etat = in_array($etat, self::ETATS, true) ? $etat : 'repos';
        $taille = max(16, (int) ($options['taille'] ?? self::TAILLE_AVATAR));

        $detail = $options['detail'] ?? 'auto';
        if ($detail === 'auto') {
            $detail = $taille < self::SEUIL_DETAIL ? 'simplifie' : 'complet';
        }
        $complet = $detail === 'complet';

        /*
         * Décoratif par défaut dès que le visage est petit.
         *
         * Dans un fil de conversation, l'auteur du message est déjà écrit à
         * côté. Annoncer « NOEL » une quarantaine de fois de suite à la
         * synthèse vocale n'informe personne et rend le fil illisible. À partir
         * de l'écran d'ouverture, en revanche, le visage est seul : il reprend
         * un nom.
         */
        $decoratif = (bool) ($options['decoratif'] ?? $taille < self::SEUIL_DETAIL);
        $titre = $options['titre'] ?? self::LIBELLES[$etat];

        $classes = 'noel noel--' . $etat . ' noel--' . $detail;
        if (!empty($options['classe'])) {
            $classes .= ' ' . $options['classe'];
        }

        $svg = '<svg class="' . View::e($classes) . '"'
            . ' viewBox="0 0 64 64"'
            . ' style="--noel-taille: ' . $taille . 'px"';

        $svg .= $decoratif
            ? ' aria-hidden="true" focusable="false">'
            : ' role="img" focusable="false"><title>' . View::e((string) $titre) . '</title>';

        $svg .= self::fond()
            . self::buste($complet)
            . self::tete($complet, $etat)
            . '</svg>';

        return $svg;
    }

    /**
     * L'avatar de bulle de conversation : 32 px, version simplifiée.
     *
     * @param array{taille?: int, detail?: string, titre?: string|null, classe?: string, decoratif?: bool} $options
     */
    public static function avatar(string $etat = 'repos', array $options = []): string
    {
        return self::visage($etat, $options + ['taille' => self::TAILLE_AVATAR]);
    }

    /**
     * Le grand format de l'écran d'ouverture : 120 px, tous les détails.
     *
     * @param array{taille?: int, detail?: string, titre?: string|null, classe?: string, decoratif?: bool} $options
     */
    public static function accueil(string $etat = 'repos', array $options = []): string
    {
        return self::visage($etat, $options + ['taille' => self::TAILLE_ACCUEIL]);
    }

    /**
     * Le libellé parlé d'un état, pour les écrans qui l'annoncent eux-mêmes.
     */
    public static function libelle(string $etat): string
    {
        return self::LIBELLES[$etat] ?? self::LIBELLES['repos'];
    }

    /**
     * Le disque et son anneau.
     *
     * Ils ne sont pas décoratifs : ils garantissent que la silhouette se
     * détache du fond, qu'on soit sur une bulle blanche ou sur le marine du
     * thème sombre. Ils donnent aussi au buste sa découpe ronde, sans
     * clipPath — un clipPath exige un identifiant unique, et deux avatars sur
     * la même page finiraient par se le disputer.
     */
    private static function fond(): string
    {
        return '<circle class="noel-disque" cx="32" cy="32" r="31"></circle>'
            . '<circle class="noel-anneau" cx="32" cy="32" r="30.1"></circle>';
    }

    /**
     * Cou, polo et col.
     *
     * Le bas du polo suit exactement l'arc du disque (le « A31 31 » de fin),
     * pour que le vêtement s'arrête net au bord du cercle au lieu d'en sortir.
     */
    private static function buste(bool $complet): string
    {
        $html = '<path class="noel-ombre" d="M27.6 45.4 L27.6 50.6 C30.5 52.5 33.5 52.5 36.4 50.6 L36.4 45.4 Z"></path>';

        /*
         * Les épaules sont larges et le bas du polo suit exactement l'arc du
         * disque (le « A31 31 » de fin), pour que le vêtement s'arrête net au
         * bord du cercle au lieu d'en sortir.
         *
         * Première version : des épaules étroites et tombantes, qui donnaient
         * une silhouette frêle d'adolescent. NOEL est un collègue adulte.
         */
        $html .= '<path class="noel-polo" d="M11.4 55.2'
            . ' C14.4 50.6 20.6 47.3 26.4 46.2'
            . ' L37.6 46.2'
            . ' C43.4 47.3 49.6 50.6 52.6 55.2'
            . ' A31 31 0 0 1 11.4 55.2 Z"></path>';

        // L'échancrure du col, en marine sur le bleu du polo : elle suffit à
        // dire « polo » plutôt que « t-shirt », et elle remet du marine sous
        // le visage sans refaire un bloc sombre.
        $html .= '<path class="noel-col" d="M26.4 46.2 C28 49.3 29.9 51.8 32 53.6'
            . ' C34.1 51.8 36 49.3 37.6 46.2"></path>';

        if ($complet) {
            $html .= '<path class="noel-col-pli" d="M26.4 46.3 C27.2 49.2 28.8 51.8 31.2 54 L26.7 54.8 C25.2 51.4 25.3 48 26.4 46.3 Z"></path>'
                . '<path class="noel-col-pli" d="M37.6 46.3 C36.8 49.2 35.2 51.8 32.8 54 L37.3 54.8 C38.8 51.4 38.7 48 37.6 46.3 Z"></path>';
        }

        return $html;
    }

    /**
     * La tête : visage, casquette, et les traits de l'état.
     *
     * Tout est groupé dans « noel-tete » parce que l'état « reflechit » fait
     * basculer ce groupe entier d'un degré et demi autour de la nuque. Faire
     * bouger la tête seule, casquette comprise, coûte une seule transformation.
     */
    private static function tete(bool $complet, string $etat): string
    {
        $traits = self::TRAITS[$etat];

        $html = '<g class="noel-tete">';

        if ($complet) {
            // Les oreilles d'abord : elles sortent de derrière le visage. Elles
            // sont basses, à la hauteur des yeux comme chez tout le monde — plus
            // haut, elles se lisaient comme deux taches et non comme des oreilles.
            $html .= '<ellipse class="noel-ombre" cx="19.4" cy="36.6" rx="1.6" ry="2.3"></ellipse>'
                . '<ellipse class="noel-ombre" cx="44.6" cy="36.6" rx="1.6" ry="2.3"></ellipse>';
        }

        // Le visage. La mâchoire est plus étroite que les pommettes et le
        // menton descend bas : c'est ce qui sépare un adulte d'un poupon, et
        // c'est le seul garde-fou contre l'effet mascotte.
        $html .= '<path class="noel-peau" d="M19.3 25.6'
            . ' C19.3 18.6 24.6 14 32 14'
            . ' C39.4 14 44.7 18.6 44.7 25.6'
            . ' C44.7 36 42.1 43.8 37.5 47.9'
            . ' C35.3 49.9 28.7 49.9 26.5 47.9'
            . ' C21.9 43.8 19.3 36 19.3 25.6 Z"></path>';

        // L'ombre portée de la visière sur le front. Elle vaut mieux qu'un
        // trait de contour : elle dit que la casquette est posée sur la tête,
        // et non collée dessus. Elle s'arrête au-dessus des sourcils — plus
        // bas, elle leur mangeait leur contraste.
        $html .= '<path class="noel-front-ombre" d="M20 26.5'
            . ' C22.3 28.4 26.6 29.6 32 29.6'
            . ' C37.4 29.6 41.7 28.4 44 26.5'
            . ' L43.6 22 L20.4 22 Z"></path>';

        /*
         * Pas de cheveux.
         *
         * Une première version en posait deux touches aux tempes : à l'écran
         * elles ne se lisaient pas comme des cheveux mais comme deux taches
         * noires, et elles entraient en concurrence avec les oreilles juste en
         * dessous. Une casquette portée bas couvre les tempes — c'est crédible,
         * et cela rend au visage l'air qu'il lui manquait.
         */

        // Le regard dans son propre groupe : l'état « reflechit » le décale,
        // l'état « content » le plisse. Un seul groupe, une transformation.
        $html .= '<g class="noel-regard">'
            . '<ellipse class="noel-oeil" cx="26.6" cy="36.8" rx="2" ry="2.3"></ellipse>'
            . '<ellipse class="noel-oeil" cx="37.4" cy="36.8" rx="2" ry="2.3"></ellipse>';

        if ($complet) {
            // Les éclats : invisibles sous 48 px, mais à 120 px c'est eux qui
            // font la différence entre un regard et deux points noirs.
            $html .= '<circle class="noel-eclat" cx="27.3" cy="36" r="0.64"></circle>'
                . '<circle class="noel-eclat" cx="38.1" cy="36" r="0.64"></circle>';
        }

        $html .= '</g>';

        if ($complet) {
            // Le nez se résume à la base : un arc court et centré. Essai
            // précédent, un trait qui descendait de l'axe puis se recourbait —
            // de face, cela ne faisait pas un nez, cela faisait une cicatrice.
            $html .= '<path class="noel-trait noel-nez" d="M30.6 41 C31.4 41.7 32.6 41.7 33.4 41"></path>';
        }

        $html .= '<path class="noel-trait noel-sourcil" d="' . $traits['sourcilG'] . '"></path>'
            . '<path class="noel-trait noel-sourcil" d="' . $traits['sourcilD'] . '"></path>'
            . '<path class="noel-trait noel-bouche" d="' . $traits['bouche'] . '"></path>';

        /*
         * La casquette en dernier : elle passe par-dessus le front.
         *
         * Ce qui fait une casquette plutôt qu'un chapeau tient en deux
         * proportions, et la première version s'est trompée sur les deux.
         *
         * 1. La calotte est basse et large : onze unités de haut pour vingt-six
         *    de large. Un dôme plus haut devient un melon, et ce n'était pas
         *    une nuance — le premier rendu donnait un casque colonial.
         * 2. La visière ne fait pas le tour de la tête : elle est courte en
         *    largeur et profonde en hauteur, elle plonge vers l'avant. Une
         *    forme large et plate, à l'inverse, se lit comme un bord de
         *    chapeau de paille.
         *
         * Elle ne dépasse du crâne que d'une unité et demie de chaque côté.
         * C'est assez pour casser le rond du disque et donner à la silhouette
         * sa signature à 28 px, sans donner un chapeau.
         */
        $html .= '<path class="noel-casquette" d="M18.8 21.7'
            . ' C18.7 14.9 24.2 10.7 32 10.7'
            . ' C39.8 10.7 45.3 14.9 45.2 21.7 Z"></path>';

        /*
         * Le bandeau jaune LBP, fin, sur la calotte. C'est le seul endroit où
         * la marque entre : elle est portée par le personnage, pas posée à
         * côté de lui.
         *
         * Il est haut sur la calotte, et pas à sa base : la visière, qui passe
         * en trois-quarts devant le front, avalait un bandeau posé plus bas et
         * il ne restait du jaune qu'un éclat dans un coin.
         */
        $html .= '<path class="noel-bandeau" d="M20.3 17.2 L43.7 17.2 L44.5 18.9 L19.5 18.9 Z"></path>';

        /*
         * La visière, en trois-quarts : elle longe le front et ressort en
         * pointe du côté gauche de NOEL.
         *
         * C'est la décision qui a demandé le plus d'essais, et elle a été
         * tranchée en regardant quatre variantes côte à côte plutôt qu'en
         * raisonnant. De face — la version la plus évidente — une visière est
         * vue en raccourci : elle devient une bande horizontale sous la
         * calotte, et le cerveau lit un bord de chapeau, pas une visière. Deux
         * tentatives frontales ont donné un melon puis un bonnet à liseré.
         *
         * En trois-quarts, la visière se projette sur le côté. C'est cette
         * asymétrie qui dit « casquette », et c'est elle qui survit le mieux à
         * 28 px, là où tout le reste du visage n'est plus que trois taches.
         *
         * Elle est d'un marine plus sombre que la calotte : à petite taille,
         * deux marines proches fusionnent en une seule masse.
         */
        $html .= '<path class="noel-visiere" d="M20.4 20.2'
            . ' L43.6 19.4'
            . ' C47.2 19.8 50.1 21.2 51.1 23.2'
            . ' C47.9 26.2 41 28 33.4 27.9'
            . ' C26 27.8 21 25 20.4 24.2 Z"></path>';

        if ($complet) {
            $html .= '<circle class="noel-bouton" cx="32" cy="10.8" r="1.05"></circle>';
        }

        return $html . '</g>';
    }
}
