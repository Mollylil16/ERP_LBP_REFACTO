<?php

declare(strict_types=1);

namespace Tests\Unit\View\Components;

use App\Services\Messagerie\MessagerieFormats;
use App\Services\Messagerie\MessagerieService;
use App\View\Components\MessagerieDocuments;
use Tests\TestCase;

/**
 * Écran « Documents » (lot 3) : la charpente que le PHP rend et que
 * messagerie-documents.js remplit à partir de l'API.
 */
final class MessagerieDocumentsTest extends TestCase
{
    public function test_la_charpente_porte_les_filtres_la_barre_et_la_pagination(): void
    {
        $html = MessagerieDocuments::ecran();

        self::assertStringContainsString('data-msg-documents', $html);
        foreach (['q', 'conversation_id', 'expediteur_id', 'categorie', 'du', 'au', 'favoris', 'tri', 'ordre', 'resultats', 'pagination', 'compte', 'effacer', 'basculer-filtres', 'detail'] as $cle) {
            self::assertStringContainsString('data-docs="' . $cle . '"', $html, $cle);
        }
        self::assertStringContainsString('data-docs-vue="liste" aria-pressed="true"', $html);
        self::assertStringContainsString('data-docs-vue="vignettes" aria-pressed="false"', $html);
        self::assertStringContainsString('type="date"', $html);
        self::assertStringContainsString('role="search"', $html);
        // Tant que le JavaScript n'a rien reçu : un état de chargement, et
        // une explication sans JavaScript.
        self::assertStringContainsString('Chargement des documents', $html);
        self::assertStringContainsString('<noscript>', $html);
        self::assertStringContainsString('data-docs="resultats" aria-live="polite" hidden', $html);
        self::assertStringContainsString('data-docs="pagination" aria-label="Pages de résultats" hidden', $html);
    }

    public function test_chaque_champ_a_son_etiquette(): void
    {
        $html = MessagerieDocuments::ecran();

        foreach (['msgdocs-q', 'msgdocs-conversation', 'msgdocs-expediteur', 'msgdocs-categorie', 'msgdocs-du', 'msgdocs-au'] as $id) {
            self::assertStringContainsString('id="' . $id . '"', $html, $id);
            self::assertStringContainsString('for="' . $id . '"', $html, $id);
        }
        self::assertSame(1, substr_count($html, 'id="msgdocs-q"'));
    }

    public function test_les_types_et_les_tris_sont_ceux_de_l_api(): void
    {
        self::assertSame(MessagerieFormats::CATEGORIES, array_keys(MessagerieDocuments::CATEGORIES));

        foreach (array_keys(MessagerieDocuments::TRIS) as $tri) {
            self::assertSame($tri, MessagerieService::filtresDocuments(['tri' => $tri])['tri'], $tri);
        }

        $html = MessagerieDocuments::ecran();
        foreach (array_keys(MessagerieDocuments::CATEGORIES) as $categorie) {
            self::assertStringContainsString('value="' . $categorie . '"', $html, $categorie);
        }
    }

    public function test_aucune_icone_inconnue_et_aucun_emoji(): void
    {
        $html = MessagerieDocuments::ecran();

        self::assertStringNotContainsString('href="#msg-i-"', $html);
        self::assertDoesNotMatchRegularExpression('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $html);
        self::assertStringContainsString('href="#msg-i-vignettes"', $html);
    }

    public function test_la_vue_ne_contient_aucune_balise_et_la_page_est_routee(): void
    {
        $vue = (string) file_get_contents(BASE_PATH . '/views/messagerie/documents.php');
        self::assertStringContainsString('MessagerieDocuments::ecran()', $vue);
        self::assertDoesNotMatchRegularExpression('/<(?!\?php)[a-z]/i', $vue);

        $routes = (string) file_get_contents(BASE_PATH . '/routes/messagerie.php');
        self::assertStringContainsString("\$router->get('/documents', [MessagerieController::class, 'classeur']);", $routes);
        self::assertTrue(method_exists(\App\Controllers\Messagerie\MessagerieController::class, 'classeur'));
    }

    public function test_le_classeur_est_dans_le_menu_de_la_messagerie(): void
    {
        $cles = array_column(\App\View\Navigation\MessagerieNavigation::items(), 'url', 'key');

        self::assertSame('messagerie/documents', $cles['documents'] ?? null);
        self::assertSame('messagerie', $cles['discussions'] ?? null);
    }
}
