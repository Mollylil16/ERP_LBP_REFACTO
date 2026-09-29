<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\View\Components\Admin;
use App\View\Components\Pagination;
use App\View\Components\Rh;
use Tests\TestCase;

/**
 * La pagination, partout.
 *
 * Elle sortait sans style hors du module RH : son habillage vivait dans
 * rh.css, que seuls les écrans RH chargent. Ailleurs — CRM, Facturation,
 * Colisage, Pilotage — les numéros se collaient les uns aux autres :
 * « 1234567891011121314 ». Le style est maintenant dans finea-ui.css, que
 * tous les modules chargent.
 */
final class PaginationRenduTest extends TestCase
{
    /** @return array<int, array{number:int, href:string, active:bool}> */
    private function liens(int $total, int $courante): array
    {
        $liens = [];
        for ($page = 1; $page <= $total; $page++) {
            $liens[] = [
                'number' => $page,
                'href' => '/crm/clients?page=' . $page,
                'active' => $page === $courante,
            ];
        }

        return $liens;
    }

    /**
     * Le style suit le composant : sans cette règle dans la feuille commune,
     * les modules qui ne chargent pas rh.css réaffichent des numéros nus.
     */
    public function test_le_style_est_dans_la_feuille_que_tous_les_modules_chargent(): void
    {
        $commune = (string) file_get_contents(BASE_PATH . '/public/assets/css/finea-ui.css');

        self::assertStringContainsString('.finea-pagination {', $commune);
        self::assertStringContainsString('.finea-pagination a {', $commune);
        self::assertStringContainsString('.finea-pagination a.is-active {', $commune);
    }

    /** Chaque écran paginé passe par le composant, et porte donc la classe commune. */
    public function test_toutes_les_paginations_portent_la_classe_commune(): void
    {
        $liens = $this->liens(3, 2);

        foreach ([
            'CRM et Facturation' => Rh::pagination(2, 3, static fn (int $p): string => '/crm/clients?page=' . $p),
            'Colisage et Personnel' => Rh::paginationLinks($liens),
            'Admin' => Admin::pagination($liens),
        ] as $ecran => $html) {
            self::assertStringContainsString('class="finea-pagination', $html, $ecran);
        }
    }

    /** L'ancienne classe reste posée : les écrans déjà habillés ne bougent pas. */
    public function test_les_ecrans_deja_habilles_gardent_leur_classe(): void
    {
        self::assertStringContainsString('rh-pagination', Rh::paginationLinks($this->liens(3, 1)));
        self::assertStringContainsString('admin-pagination', Admin::pagination($this->liens(3, 1)));
    }

    /**
     * Quarante numéros à la file ne se cliquent pas, ils s'évitent : au-delà
     * d'une poignée de pages, la liste garde la première, la dernière et les
     * voisines de la page courante.
     */
    public function test_une_longue_liste_est_resserree(): void
    {
        $html = Pagination::links($this->liens(40, 20));

        self::assertSame(1, substr_count($html, '>1</a>'), 'La première page reste accessible.');
        self::assertSame(1, substr_count($html, '>40</a>'), 'La dernière page reste accessible.');
        self::assertStringContainsString('>19</a>', $html);
        self::assertStringContainsString('>20</a>', $html);
        self::assertStringContainsString('>21</a>', $html);
        self::assertStringNotContainsString('>12</a>', $html, 'Les pages lointaines sont coupées.');
        self::assertStringContainsString('finea-pagination-coupure', $html);

        // Cinq numéros et deux coupures : 1 … 19 20 21 … 40.
        self::assertSame(5, substr_count($html, 'finea-pagination-page'));
        self::assertSame(2, substr_count($html, 'finea-pagination-coupure'));
    }

    /** En deçà du seuil, tout tient sur une ligne : aucune coupure. */
    public function test_une_liste_courte_reste_entiere(): void
    {
        $html = Pagination::links($this->liens(8, 4));

        self::assertSame(8, substr_count($html, 'finea-pagination-page'));
        self::assertStringNotContainsString('finea-pagination-coupure', $html);
    }

    /**
     * Une flèche morte se clique quand même, et l'on croit la page bloquée :
     * il n'y en a pas sur la première ni la dernière page.
     */
    public function test_pas_de_fleche_morte_en_bout_de_liste(): void
    {
        $premiere = Pagination::links($this->liens(5, 1));
        $milieu = Pagination::links($this->liens(5, 3));
        $derniere = Pagination::links($this->liens(5, 5));

        self::assertSame(1, substr_count($premiere, 'finea-pagination-fleche'), 'Seulement « suivante ».');
        self::assertSame(2, substr_count($milieu, 'finea-pagination-fleche'));
        self::assertSame(1, substr_count($derniere, 'finea-pagination-fleche'), 'Seulement « précédente ».');
    }

    /** Une seule page ne se pagine pas. */
    public function test_une_seule_page_n_affiche_rien(): void
    {
        self::assertSame('', Pagination::links($this->liens(1, 1)));
        self::assertSame('', Pagination::links([]));
        self::assertSame('', Pagination::pages(1, 1, static fn (int $p): string => '/x?page=' . $p));
    }

    /** La page courante est annoncée aux lecteurs d'écran. */
    public function test_la_page_courante_est_annoncee(): void
    {
        $html = Pagination::links($this->liens(5, 3));

        self::assertStringContainsString('aria-current="page"', $html);
        self::assertSame(1, substr_count($html, 'aria-current'));
        self::assertStringContainsString('aria-label="Pagination"', $html);
    }

    /** Les adresses sont échappées : un filtre dans l'URL ne casse pas la page. */
    public function test_les_adresses_sont_echappees(): void
    {
        $html = Pagination::links([
            ['number' => 1, 'href' => '/crm/clients?q=a&b=1&page=1', 'active' => true],
            ['number' => 2, 'href' => '/crm/clients?q="><script>', 'active' => false],
        ]);

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&amp;b=1', $html);
    }
}
