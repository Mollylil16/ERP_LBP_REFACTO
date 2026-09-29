<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

/**
 * La pagination, une seule fois pour tout le logiciel.
 *
 * Elle s'affichait jusqu'ici sous la classe « rh-pagination », dont le style
 * vit dans rh.css — que seul le module RH charge. Partout ailleurs (CRM,
 * Facturation, Colisage, Pilotage), les numéros sortaient sans aucune mise en
 * forme et se collaient les uns aux autres : « 1234567891011121314 ».
 * Le style est donc désormais dans finea-ui.css, que tous les modules
 * chargent, et l'ancienne classe reste posée pour ne rien changer là où elle
 * fonctionnait déjà.
 *
 * Et la liste ne déroule plus toutes les pages : au-delà d'une poignée, elle
 * garde la première, la dernière, les voisines de la page courante, et coupe
 * le reste. Quarante numéros à la file ne se cliquent pas, ils s'évitent.
 */
final class Pagination
{
    /** Pages affichées de part et d'autre de la page courante. */
    private const VOISINS = 1;

    /**
     * Au-delà de ce nombre de pages, on resserre la liste. En deçà, tout
     * tient sur une ligne et les points de suspension ne servent à rien.
     */
    private const SEUIL_FENETRE = 9;

    private const CHEVRON_GAUCHE = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg>';
    private const CHEVRON_DROIT = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>';

    /**
     * @param array<int, array{number:int, href:string, active:bool}> $liens
     */
    public static function links(array $liens, string $classeHeritee = ''): string
    {
        $liens = array_values(array_filter($liens, static fn (mixed $l): bool => is_array($l) && isset($l['number'], $l['href'])));

        if (count($liens) <= 1) {
            return '';
        }

        $classe = trim('finea-pagination ' . $classeHeritee);
        $courante = self::indexCourant($liens);

        $html = '<nav class="' . View::e($classe) . '" aria-label="Pagination">';
        $html .= self::fleche($liens[$courante - 1] ?? null, self::CHEVRON_GAUCHE, 'Page précédente');

        foreach (self::fenetre($liens, $courante) as $element) {
            if ($element === null) {
                $html .= '<span class="finea-pagination-coupure" aria-hidden="true">…</span>';
                continue;
            }

            $actif = !empty($element['active']);
            $html .= '<a class="finea-pagination-page' . ($actif ? ' is-active' : '') . '" href="'
                . View::e((string) $element['href']) . '"'
                . ($actif ? ' aria-current="page"' : '')
                . ' aria-label="Page ' . (int) $element['number'] . '">'
                . (int) $element['number'] . '</a>';
        }

        $html .= self::fleche($liens[$courante + 1] ?? null, self::CHEVRON_DROIT, 'Page suivante');

        return $html . '</nav>';
    }

    /**
     * La même chose, quand l'appelant tient le compte des pages plutôt que
     * la liste des liens.
     *
     * @param callable(int):string $href
     */
    public static function pages(int $courante, int $total, callable $href, string $classeHeritee = ''): string
    {
        if ($total <= 1) {
            return '';
        }

        $liens = [];
        for ($page = 1; $page <= $total; $page++) {
            $liens[] = [
                'number' => $page,
                'href' => $href($page),
                'active' => $page === $courante,
            ];
        }

        return self::links($liens, $classeHeritee);
    }

    /**
     * Les liens à afficher, null marquant une coupure.
     *
     * @param array<int, array{number:int, href:string, active:bool}> $liens
     * @return array<int, array{number:int, href:string, active:bool}|null>
     */
    private static function fenetre(array $liens, int $courante): array
    {
        $dernier = count($liens) - 1;

        if (count($liens) <= self::SEUIL_FENETRE) {
            return $liens;
        }

        // Premières, dernières et voisines : le reste disparaît derrière les
        // points de suspension.
        $gardees = [0, $dernier];
        for ($decalage = -self::VOISINS; $decalage <= self::VOISINS; $decalage++) {
            $gardees[] = $courante + $decalage;
        }

        $gardees = array_values(array_unique(array_filter(
            $gardees,
            static fn (int $index): bool => $index >= 0 && $index <= $dernier
        )));
        sort($gardees);

        $fenetre = [];
        $precedent = null;

        foreach ($gardees as $index) {
            if ($precedent !== null && $index > $precedent + 1) {
                $fenetre[] = null;
            }
            $fenetre[] = $liens[$index];
            $precedent = $index;
        }

        return $fenetre;
    }

    /** @param array<int, array{number:int, href:string, active:bool}> $liens */
    private static function indexCourant(array $liens): int
    {
        foreach ($liens as $index => $lien) {
            if (!empty($lien['active'])) {
                return $index;
            }
        }

        return 0;
    }

    /** @param array{number:int, href:string, active:bool}|null $lien */
    private static function fleche(?array $lien, string $icone, string $libelle): string
    {
        // Pas de flèche morte en bout de liste : un bouton qui ne fait rien
        // se clique quand même, et l'on croit que la page est bloquée.
        if ($lien === null) {
            return '';
        }

        return '<a class="finea-pagination-fleche" href="' . View::e((string) $lien['href'])
            . '" aria-label="' . View::e($libelle) . '" title="' . View::e($libelle) . '">' . $icone . '</a>';
    }
}
