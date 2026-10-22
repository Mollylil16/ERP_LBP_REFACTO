<?php

declare(strict_types=1);

namespace App\Services\Noel;

use App\Security\NoelAcces;
use App\Services\Shared\ModuleDashboardService;

/**
 * La carte du logiciel, telle que la personne qui parle a NOEL peut la voir.
 *
 * Le catalogue des modules et la liste de leurs ecrans existent deja, dans
 * ModuleDashboardService : chaque entree y porte son libelle, son adresse, son
 * groupe, et surtout son « available » — c'est-a-dire le controle de droits
 * qui decide si la tuile s'affiche. NOEL se sert de cette carte-la, et
 * d'aucune autre.
 *
 * C'est ce qui lui fait herite des droits sans qu'on ait rien a redire :
 * l'ecran qu'une caissiere de Dokui ne peut pas ouvrir n'entre jamais dans ce
 * que NOEL lui raconte. Reecrire une seconde liste a cote serait la garantie
 * qu'elle derive — c'est deja arrive sur les demandes de fonds, ou la liste
 * reconnaissait le comptable que la fiche avait oublie.
 *
 * Le catalogue ne coute aucune requete : ce sont des tableaux litteraux.
 */
final class NoelCarte
{
    /**
     * Les modules qui tiennent leur menu a part, hors du catalogue central.
     *
     * Le catalogue de ModuleDashboardService ne les connait pas : chacun a sa
     * propre classe de navigation, utilisee par son controleur de base. Sans
     * ce rattachement, NOEL ignorerait purement et simplement les Ressources
     * humaines et l'Administration — c'est-a-dire les deux modules sur
     * lesquels on lui posera le plus de questions.
     *
     * Le Colisage figure bien au catalogue, mais avec un menu reduit : sa
     * propre classe en porte trente-trois entrees contre quelques-unes. On
     * prend la plus complete.
     *
     * @var array<string, array{classe: class-string, label: string, description: string}>
     */
    private const MENUS_A_PART = [
        'rh' => [
            'classe' => \App\View\Navigation\RhNavigation::class,
            'label' => 'Ressources humaines',
            'description' => 'Dossiers du personnel, contrats, pointage, paie, discipline et congés.',
        ],
        'colisage' => [
            'classe' => \App\View\Navigation\ColisageNavigation::class,
            'label' => 'Facturation & Colisage',
            'description' => 'Saisie des colis, opérations, groupage, factures et points de caisse du guichet.',
            // Ce menu-la interroge la base pour lister les departs en
            // preparation. Database::getConnection() n'echoue pas : il arrete
            // le processus. Un try/catch ne protegerait donc de rien, et la
            // suite de tests tomberait des que NOEL dresse sa carte. Meme
            // parade que AuditLogService : on s'abstient en test.
            'basedonnees' => true,
        ],
        'admin' => [
            'classe' => \App\View\Navigation\AdminNavigation::class,
            'label' => 'Administration',
            'description' => 'Comptes, rôles, habilitations et journal des accès.',
        ],
        /*
         * La cle est celle du catalogue — « employee » — et non le slug
         * « espace-employe » de son adresse. Se tromper de l'une pour l'autre
         * ne provoque aucune erreur : le module apparait simplement deux fois,
         * une fois par menu, et NOEL en parle comme de deux endroits distincts.
         */
        'employee' => [
            'classe' => \App\View\Navigation\EmployeeNavigation::class,
            'label' => 'Espace employé',
            'description' => 'Votre propre dossier : bulletins, demandes, congés.',
        ],
    ];

    private ModuleDashboardService $modules;

    /** @var array<string, array<string, mixed>>|null */
    private ?array $cache = null;

    public function __construct(?ModuleDashboardService $modules = null)
    {
        $this->modules = $modules ?? new ModuleDashboardService();
    }

    /**
     * Tous les modules que cette personne peut ouvrir, avec leurs ecrans.
     *
     * @return array<string, array<string, mixed>>
     */
    public function modulesVisibles(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $carte = [];

        $catalogue = $this->modules->modules();

        // Les menus a part priment sur le catalogue quand les deux decrivent
        // le meme module : leur liste est la plus complete.
        foreach (self::MENUS_A_PART as $slug => $menu) {
            $catalogue[$slug] = [
                'label' => $menu['label'],
                'description' => $menu['description'],
            ];
        }

        foreach ($catalogue as $slug => $module) {
            $slug = (string) $slug;

            // Un module reserve n'existe pas pour qui n'y a pas droit. NOEL ne
            // dit pas « je ne peux pas vous en parler » : il l'ignore.
            if (!NoelAcces::peutEvoquerModule($slug)) {
                continue;
            }

            $ecrans = $this->ecransDuModule($slug);

            if ($ecrans === []) {
                continue;
            }

            $carte[$slug] = [
                'slug' => $slug,
                'label' => (string) ($module['label'] ?? $slug),
                'description' => (string) ($module['description'] ?? ''),
                'ecrans' => $ecrans,
            ];
        }

        return $this->cache = $carte;
    }

    /**
     * Les ecrans d'un module que cette personne peut reellement ouvrir.
     *
     * @return array<int, array<string, string>>
     */
    public function ecransDuModule(string $slug): array
    {
        $navigation = [];

        if (isset(self::MENUS_A_PART[$slug])) {
            $menu = self::MENUS_A_PART[$slug];
            $enTest = ($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? '') === 'testing';

            if (!empty($menu['basedonnees']) && $enTest) {
                return [];
            }

            $classe = $menu['classe'];
            $navigation = method_exists($classe, 'items') ? $classe::items() : [];
        } else {
            try {
                $navigation = (array) ($this->modules->dashboard($slug)['navigation'] ?? []);
            } catch (\Throwable $e) {
                // Un module sans tableau de bord declare ne doit pas faire
                // taire NOEL sur tous les autres.
                return [];
            }
        }

        $ecrans = [];

        foreach ($navigation as $entree) {
            $entree = (array) $entree;

            if (($entree['available'] ?? false) !== true) {
                continue;
            }

            $url = ltrim((string) ($entree['url'] ?? ''), '/');

            if ($url === '') {
                continue;
            }

            $ecrans[] = [
                'cle' => (string) ($entree['key'] ?? ''),
                'label' => (string) ($entree['label'] ?? ''),
                'groupe' => (string) ($entree['group'] ?? ''),
                'url' => $url,
            ];
        }

        return $ecrans;
    }

    /**
     * Les ecrans dont le libelle approche des mots cherches.
     *
     * On cherche « ou je soumets mon point de caisse » : les mots utiles sont
     * « point » et « caisse », le reste est de la grammaire. On ignore donc
     * les mots de moins de quatre lettres, et les accents, pour que
     * « reglement » trouve « Règlements ».
     *
     * @return array<int, array<string, string>>
     */
    public function chercherEcrans(string $question, int $limite = 5): array
    {
        $mots = self::motsUtiles($question);

        if ($mots === []) {
            return [];
        }

        $trouves = [];

        foreach ($this->modulesVisibles() as $module) {
            foreach ($module['ecrans'] as $ecran) {
                $cible = self::sansAccent(
                    $ecran['label'] . ' ' . $ecran['groupe'] . ' ' . $module['label'] . ' ' . $ecran['url']
                );

                $score = 0;
                foreach ($mots as $mot) {
                    if (str_contains($cible, $mot)) {
                        $score++;
                    }
                }

                if ($score > 0) {
                    $ecran['module'] = $module['label'];
                    $ecran['score'] = (string) $score;
                    $trouves[] = $ecran;
                }
            }
        }

        usort($trouves, static fn(array $a, array $b): int => (int) $b['score'] <=> (int) $a['score']);

        return array_slice($trouves, 0, $limite);
    }

    /**
     * Les mots d'une question sur lesquels il vaut la peine de chercher.
     *
     * @return array<int, string>
     */
    public static function motsUtiles(string $texte): array
    {
        $mots = preg_split('/[^\p{L}\p{N}]+/u', self::sansAccent($texte)) ?: [];

        $vides = [
            'dans', 'pour', 'avec', 'mais', 'donc', 'elle', 'nous', 'vous', 'cette', 'faire',
            'comment', 'pourquoi', 'quand', 'est-ce', 'que', 'qui', 'quoi', 'avoir', 'etre',
            'mon', 'mes', 'son', 'ses', 'leur', 'tout', 'tous', 'plus', 'bien', 'peux', 'peut',
            'arrive', 'arrives', 'narrive', 'trouve', 'trouver', 'cherche', 'chercher', 'voir',
        ];

        $utiles = [];
        foreach ($mots as $mot) {
            if (mb_strlen($mot) >= 4 && !in_array($mot, $vides, true)) {
                $utiles[$mot] = $mot;
            }
        }

        return array_values($utiles);
    }

    public static function sansAccent(string $texte): string
    {
        $texte = mb_strtolower($texte, 'UTF-8');

        $remplacements = [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'í' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ó' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ];

        return strtr($texte, $remplacements);
    }
}
