<?php

declare(strict_types=1);

namespace Tests\Unit\View\Components;

use App\Services\Messagerie\MessagerieEspace;
use App\View\Components\AdminMessagerieEspace;
use App\View\Navigation\AdminNavigation;
use Tests\Support\Messagerie\DepotMessagerieMemoire;
use Tests\TestCase;

final class AdminMessagerieEspaceTest extends TestCase
{
    public function test_la_page_dit_l_espace_et_passe_en_alerte_au_dela_du_seuil(): void
    {
        $depot = new DepotMessagerieMemoire();
        $depot->fichiers[1] = [
            'id' => 1, 'empreinte' => str_repeat('a', 64), 'chemin' => 'messagerie/2026/10/a.pdf', 'chemin_miniature' => null,
            'mime' => 'application/pdf', 'categorie' => 'pdf', 'extension' => 'pdf',
            'taille' => 3 * 1024 * 1024, 'taille_miniature' => 0, 'largeur' => null, 'hauteur' => null,
            'created_at' => '2026-10-22 09:00:00',
        ];

        $normal = AdminMessagerieEspace::page((new MessagerieEspace($depot))->etat());
        self::assertStringContainsString('3,0 Mo', $normal);
        self::assertStringContainsString('octobre 2026', $normal);
        self::assertStringContainsString('PDF', $normal);
        self::assertStringContainsString('Normal', $normal);
        self::assertStringNotContainsString('Seuil dépassé', $normal);

        $alerte = AdminMessagerieEspace::page((new MessagerieEspace($depot, 1024 * 1024))->etat());
        self::assertStringContainsString('Seuil dépassé', $alerte);
        self::assertStringContainsString('tone-danger', $alerte);
    }

    public function test_l_ecran_figure_dans_le_menu_de_l_administration(): void
    {
        self::assertContains('admin/messagerie/espace', array_column(AdminNavigation::items(), 'url'));
    }
}
