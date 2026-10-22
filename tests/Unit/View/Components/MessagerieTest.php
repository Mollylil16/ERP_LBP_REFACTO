<?php

declare(strict_types=1);

namespace Tests\Unit\View\Components;

use App\View\Components\Messagerie;
use Tests\TestCase;

/**
 * Charpente de la messagerie (lot 2) : ce que le PHP rend, et les règles
 * que le JavaScript doit tenir (aucun innerHTML, un seul fichier qui relève).
 */
final class MessagerieTest extends TestCase
{
    /** @return array{api:string,csrf:string,moi:int,moi_nom:string,connexion:string,plein:string,direction:bool} */
    private function contexte(array $surcharge = []): array
    {
        return array_replace([
            'api' => '/messagerie/api',
            'csrf' => 'jeton123',
            'moi' => 13,
            'moi_nom' => 'Awa Koné',
            'connexion' => '/login',
            'plein' => '/messagerie',
            'direction' => false,
        ], $surcharge);
    }

    public function test_le_socle_echappe_le_contexte(): void
    {
        $html = Messagerie::socle($this->contexte([
            'moi_nom' => '<script>alert(1)</script>"',
            'plein' => '/messagerie"><img src=x>',
        ]));

        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('data-moi-nom="&lt;script&gt;alert(1)&lt;/script&gt;&quot;"', $html);
        self::assertStringNotContainsString('"><img src=x>', $html);
        self::assertStringContainsString('data-csrf="jeton123"', $html);
        self::assertStringContainsString('data-direction="0"', $html);
    }

    public function test_le_socle_rend_chaque_icone_une_fois(): void
    {
        $html = Messagerie::socle($this->contexte());

        foreach (Messagerie::nomsIcones() as $nom) {
            self::assertSame(1, substr_count($html, 'id="msg-i-' . $nom . '"'), $nom);
        }
    }

    public function test_le_panneau_n_existe_pas_sur_l_ecran_complet(): void
    {
        $avec = Messagerie::socle($this->contexte());
        $sans = Messagerie::socle($this->contexte(), false);

        self::assertStringContainsString('data-msg-tiroir', $avec);
        self::assertStringContainsString('<template data-msg-gabarit>', $avec);
        self::assertStringContainsString('data-mode="panneau"', $avec);
        self::assertStringNotContainsString('data-msg-tiroir', $sans);
        self::assertStringContainsString('data-msg-toasts', $sans);
    }

    public function test_le_bouton_d_en_tete(): void
    {
        $ailleurs = Messagerie::boutonEntete('/messagerie');
        $ici = Messagerie::boutonEntete('/messagerie', true);

        self::assertStringContainsString('data-msg-ouvrir', $ailleurs);
        self::assertStringContainsString('href="/messagerie"', $ailleurs);
        self::assertStringContainsString('data-msg-total hidden', $ailleurs);
        self::assertStringNotContainsString('data-msg-ouvrir', $ici);
        self::assertStringContainsString('aria-current="page"', $ici);
        self::assertStringContainsString('&quot;', Messagerie::boutonEntete('/x"y'));
    }

    public function test_l_ecran_porte_les_filtres_les_dialogues_et_les_etats_vides(): void
    {
        $html = Messagerie::ecran();

        foreach (['toutes', 'non_lues', 'groupes', 'archivees'] as $filtre) {
            self::assertStringContainsString('data-msg-filtre="' . $filtre . '"', $html);
        }
        foreach (['annuaire', 'groupe', 'transfert', 'lu-par', 'ajout-membres', 'renommer', 'reglages', 'confirmer'] as $dialogue) {
            self::assertStringContainsString('data-msg-dialogue="' . $dialogue . '"', $html);
        }
        self::assertStringContainsString('Chargement des discussions', $html);
        self::assertStringContainsString('Choisissez une discussion', $html);
        self::assertStringContainsString('Seuls les administrateurs peuvent écrire', $html);
        self::assertStringContainsString('Lecture seule', $html);
        // Le trombone existe, désactivé : l'envoi de fichiers vient plus tard.
        self::assertStringContainsString('aria-disabled="true" title="Bientôt', $html);
    }

    public function test_la_charpente_n_a_aucun_identifiant_html(): void
    {
        // Elle peut exister deux fois dans une page (écran et panneau).
        self::assertDoesNotMatchRegularExpression('/\sid="/', Messagerie::application('plein'));
        self::assertDoesNotMatchRegularExpression('/\sid="/', Messagerie::application('panneau'));
    }

    public function test_une_icone_inconnue_ne_rend_rien(): void
    {
        self::assertSame('', Messagerie::icone('inexistante'));
        self::assertStringContainsString('href="#msg-i-envoyer"', Messagerie::icone('envoyer'));
        self::assertStringContainsString('aria-hidden="true"', Messagerie::icone('envoyer'));
    }

    public function test_la_vue_ne_contient_aucune_balise(): void
    {
        $source = (string) file_get_contents(BASE_PATH . '/views/messagerie/index.php');

        self::assertStringContainsString('Messagerie::ecran()', $source);
        self::assertDoesNotMatchRegularExpression('/<(?!\?php)[a-z]/i', $source);
    }

    public function test_les_gabarits_portent_le_bouton_et_le_panneau(): void
    {
        foreach (['module.php', 'app.php'] as $gabarit) {
            $source = (string) file_get_contents(BASE_PATH . '/views/layouts/' . $gabarit);

            self::assertStringContainsString('Messagerie::contexteCourant()', $source, $gabarit);
            self::assertStringContainsString('Messagerie::boutonEntete(', $source, $gabarit);
            self::assertStringContainsString('Messagerie::socle(', $source, $gabarit);
            self::assertStringContainsString("js/messagerie-releve.js", $source, $gabarit);
            self::assertLessThan(
                strpos($source, "js/messagerie.js"),
                strpos($source, "js/messagerie-releve.js"),
                $gabarit . ' : le relevé doit être chargé avant l’interface'
            );
        }
    }

    public function test_le_javascript_n_injecte_jamais_de_html(): void
    {
        foreach (['messagerie.js', 'messagerie-releve.js'] as $fichier) {
            $source = (string) file_get_contents(BASE_PATH . '/public/assets/js/' . $fichier);

            self::assertStringNotContainsString('innerHTML', $source, $fichier);
            self::assertStringNotContainsString('insertAdjacentHTML', $source, $fichier);
            self::assertStringNotContainsString('outerHTML', $source, $fichier);
        }
    }

    public function test_seul_le_releve_interroge_les_nouveautes(): void
    {
        $interface = (string) file_get_contents(BASE_PATH . '/public/assets/js/messagerie.js');
        $releve = (string) file_get_contents(BASE_PATH . '/public/assets/js/messagerie-releve.js');

        self::assertStringNotContainsString('/nouveautes', $interface);
        self::assertStringNotContainsString('setInterval', $interface);
        self::assertStringContainsString('/nouveautes', $releve);
        self::assertStringContainsString('visibilitychange', $releve);
        self::assertStringContainsString('3000', $releve);
        self::assertStringContainsString('30000', $releve);
    }
}
