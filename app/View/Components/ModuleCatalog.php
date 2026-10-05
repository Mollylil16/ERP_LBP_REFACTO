<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\ModuleIcon;
use App\Helpers\View;

/**
 * Le portail : l'écran que chacun ouvre dix fois par jour.
 *
 * Il affichait dix-huit tuiles de poids égal, chacune avec sa description, et
 * un bandeau de 240 pixels pour une phrase de politesse. Rien ne guidait
 * l'œil, et il fallait défiler pour trouver ce qu'on ouvre tous les matins.
 *
 * Décidé le 05/10/2026 : un lanceur, pas une page de lecture. Les tuiles sont
 * compactes et rangées par métier, la description n'apparaît qu'au survol, et
 * une rangée en tête donne les modules que la personne ouvre vraiment.
 *
 * Le style est parti dans public/assets/css/portail.css. Il vivait ici, mêlé
 * au PHP, et c'est pour cela que le portail vieillissait à part du reste.
 */
final class ModuleCatalog
{
    /**
     * Les métiers de la maison, et les modules qui leur appartiennent.
     *
     * L'ordre compte : c'est celui dans lequel les groupes s'affichent. Un
     * module absent de cette table tombe dans « Autres outils » plutôt que de
     * disparaître — un portail qui perd une tuile en silence est pire qu'un
     * portail mal rangé.
     *
     * @var array<string, array<int, string>>
     */
    public const GROUPES = [
        'Exploitation' => [
            'colisage', 'logistique', 'entrepots', 'flotte-transport',
            'tracking-colis', 'transit-douane',
        ],
        'Finance & administration' => [
            'finance', 'rh', 'pilotage-dg', 'facturation', 'admin',
        ],
        'Relation client' => [
            'crm', 'call-center', 'portefeuille-clients',
            'agents-correspondants', 'tickets', 'site-admin',
        ],
        'Mon espace' => [
            'espace-employe',
        ],
    ];

    /**
     * Les modules proposés d'emblée, dans l'ordre, à qui n'a encore rien
     * ouvert depuis ce navigateur.
     *
     * @var array<int, string>
     */
    private const RAPIDES_PAR_DEFAUT = ['finance', 'colisage', 'logistique', 'pilotage-dg', 'crm'];

    /** Combien de tuiles au maximum dans la rangée du haut. */
    private const RAPIDES_MAX = 5;

    /** La couleur qui rattache chaque module à son univers. */
    private const COULEURS = [
        'finance' => '#2563eb',
        'rh' => '#7c3aed',
        'colisage' => '#ea580c',
        'logistique' => '#059669',
        'espace-employe' => '#0891b2',
        'crm' => '#0e7490',
        'tickets' => '#9333ea',
        'site-admin' => '#db2777',
        'transit-douane' => '#065f46',
        'tracking-colis' => '#4f46e5',
        'facturation' => '#0369a1',
        'entrepots' => '#7c2d12',
        'flotte-transport' => '#0d9488',
        'portefeuille-clients' => '#15803d',
        'agents-correspondants' => '#a16207',
        'pilotage-dg' => '#b91c1c',
        'call-center' => '#0284c7',
        'admin' => '#475569',
    ];

    public static function hero(string $userName, int $moduleCount): string
    {
        $prenom = self::prenom($userName);

        return '<section class="portail-accueil">'
            . '<h1 class="portail-salut">' . View::e('Bonjour ' . $prenom . '.') . '</h1>'
            . '<p class="portail-sous">' . View::e(self::phrase($moduleCount)) . '</p>'
            . '</section>';
    }

    /**
     * Le prénom plutôt que l'état civil complet.
     *
     * « Bonjour BRUNELL OMEPIEUR » sonne comme une convocation ; le portail
     * est un accueil.
     */
    private static function prenom(string $nomComplet): string
    {
        $morceaux = preg_split('/\s+/', trim($nomComplet)) ?: [];
        $premier = (string) ($morceaux[0] ?? '');

        if ($premier === '') {
            return 'à vous';
        }

        return mb_convert_case(mb_strtolower($premier), MB_CASE_TITLE, 'UTF-8');
    }

    private static function phrase(int $nombre): string
    {
        if ($nombre <= 0) {
            return "Aucun module ne vous est ouvert pour l'instant.";
        }

        return $nombre === 1
            ? 'Un module vous est ouvert.'
            : $nombre . ' modules vous sont ouverts, rangés par métier.';
    }

    /**
     * @param array<int,array{value:string,label:string}> $options
     */
    public static function moduleFilter(array $options, int $moduleCount): string
    {
        $selector = Form::selectSearch('portal_modules', $options, [], [
            'label' => 'Rechercher et filtrer les modules métier',
            'multiple' => true,
            'id' => 'portalModuleSelect',
            'placeholder' => 'Rechercher un module par nom ou code…',
            'fieldClass' => 'portal-module-filter-field',
            'data-portal-module-filter' => '1',
        ]);

        /*
         * Le conteneur portal-module-filter porte les regles responsives du
         * champ, dans app.css. Le retirer faisait deborder la page de 100 px
         * sur un telephone, sans rien changer a l oeil sur un ordinateur.
         */
        return '<section class="portail-recherche" aria-label="Recherche de modules">'
            . '<div class="portal-module-filter">' . $selector . '</div>'
            . '<div class="portail-recherche-meta">'
            . '<span id="moduleSearchCount">' . $moduleCount . ' module' . ($moduleCount > 1 ? 's' : '') . ' accessible' . ($moduleCount > 1 ? 's' : '') . '</span>'
            . Ui::button('Tout afficher', [
                'variant' => 'plain',
                'type' => 'button',
                'id' => 'moduleFilterReset',
                'class' => 'portail-reset',
                'hidden' => true,
            ])
            . '</div></section>';
    }

    /** @param array<int,array<string,mixed>> $modules */
    public static function moduleGrid(array $modules): string
    {
        $parCle = [];
        foreach ($modules as $module) {
            $parCle[(string) ($module['key'] ?? '')] = $module;
        }

        return self::accesRapides($parCle)
            . self::groupes($parCle)
            . '<p class="portail-vide" id="moduleEmptyState" hidden>Aucun module ne correspond à votre recherche.</p>';
    }

    /**
     * La rangée du haut.
     *
     * Le serveur ne sait pas ce que cette personne ouvre le plus : rien ne
     * l'enregistre. Il propose donc les modules les plus courants de la
     * maison, et le navigateur remplace ensuite cette liste par les tuiles
     * réellement cliquées depuis ce poste. Chacun garde sa propre rangée, sans
     * qu'aucun suivi ne remonte.
     *
     * @param array<string, array<string,mixed>> $parCle
     */
    private static function accesRapides(array $parCle): string
    {
        $choisis = [];

        foreach (self::RAPIDES_PAR_DEFAUT as $cle) {
            if (isset($parCle[$cle])) {
                $choisis[] = $parCle[$cle];
            }

            if (count($choisis) >= self::RAPIDES_MAX) {
                break;
            }
        }

        // Moins de deux tuiles ne forment pas une rangée : autant n'en pas
        // faire et laisser la place aux groupes.
        if (count($choisis) < 2) {
            return '';
        }

        $tuiles = '';
        foreach ($choisis as $module) {
            $tuiles .= self::moduleCard($module, true);
        }

        return '<section class="portail-rapides" data-portail-rapides aria-label="Accès rapides">'
            . '<h2>Accès rapides</h2>'
            . '<div class="portail-rangee">' . $tuiles . '</div>'
            . '</section>';
    }

    /** @param array<string, array<string,mixed>> $parCle */
    private static function groupes(array $parCle): string
    {
        $restants = $parCle;
        $html = '';

        foreach (self::GROUPES as $titre => $cles) {
            $tuiles = '';

            foreach ($cles as $cle) {
                if (!isset($restants[$cle])) {
                    continue;
                }

                $tuiles .= self::moduleCard($restants[$cle]);
                unset($restants[$cle]);
            }

            if ($tuiles !== '') {
                $html .= self::groupe($titre, $tuiles);
            }
        }

        // Un module ajouté demain et pas encore rangé doit rester visible.
        if ($restants !== []) {
            $tuiles = '';
            foreach ($restants as $module) {
                $tuiles .= self::moduleCard($module);
            }
            $html .= self::groupe('Autres outils', $tuiles);
        }

        return $html;
    }

    private static function groupe(string $titre, string $tuiles): string
    {
        return '<section class="portail-groupe" data-portail-groupe>'
            . '<h2>' . View::e($titre) . '</h2>'
            . '<div class="portail-rangee">' . $tuiles . '</div>'
            . '</section>';
    }

    /**
     * @param array<string,mixed> $module
     * @param bool $rapide La tuile est dans la rangée du haut : elle ne doit
     *        pas être comptée deux fois par le filtre de recherche.
     */
    public static function moduleCard(array $module, bool $rapide = false): string
    {
        $maintenance = (bool) ($module['is_maintenance'] ?? false);
        $cle = (string) ($module['key'] ?? '');
        $label = (string) ($module['label'] ?? 'Module');
        $description = (string) ($module['description'] ?? '');

        $classe = Html::classes([
            'portail-tuile',
            (string) ($module['class'] ?? ''),
            'is-maintenance' => $maintenance,
        ]);

        $couleur = self::COULEURS[$cle] ?? '#475569';

        $contenu = '<span class="portail-pastille" aria-hidden="true">'
            . ModuleIcon::svg((string) ($module['icon'] ?? 'admin'))
            . '</span>'
            . '<span class="portail-nom">' . View::e($label) . '</span>'
            . '<span class="portail-code">' . View::e((string) ($module['code'] ?? '')) . '</span>'
            . ($maintenance ? '<span class="portail-maintenance">Indisponible</span>' : '')
            . ($description === '' ? '' : '<span class="portail-desc">' . View::e($description) . '</span>');

        $attributs = 'class="' . View::e($classe) . '"'
            . ' style="--portail-couleur:' . View::e($couleur) . '"'
            . ' data-module-key="' . View::e($cle) . '"'
            . ($rapide ? ' data-portail-rapide="1"' : ' data-module-card');

        if ($maintenance) {
            $raison = trim((string) ($module['maintenance_reason'] ?? ''));

            return '<div ' . $attributs . ' aria-disabled="true"'
                . ' title="' . View::e($raison === '' ? 'Module en maintenance' : $raison) . '">'
                . $contenu . '</div>';
        }

        return '<a ' . $attributs
            . ' href="' . View::url(ltrim((string) ($module['url'] ?? ''), '/')) . '"'
            . ' aria-label="' . View::e('Ouvrir le module ' . $label) . '">'
            . $contenu . '</a>';
    }

    public static function footerNote(
        string $eyebrow = 'Navigation centralisée',
        string $message = 'Le portail reste l’accueil privé, chaque module conserve son propre tableau de bord.',
        string $actionLabel = 'Déconnexion',
        string $actionHref = 'logout',
    ): string {
        return '<section class="portail-pied">'
            . '<span>' . View::e($message) . '</span>'
            . Ui::button($actionLabel, ['href' => $actionHref, 'variant' => 'secondary'])
            . '</section>';
    }
}
