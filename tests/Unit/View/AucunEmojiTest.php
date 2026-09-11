<?php

declare(strict_types=1);

namespace Tests\Unit\View;

use Tests\TestCase;

/**
 * L interface n utilise que des icones SVG, jamais d emoji.
 *
 * Un emoji se rend differemment sur chaque appareil, ne suit pas la couleur du
 * theme, et casse l homogeneite du trait. Ce test evite qu ils reviennent au fil
 * des contributions.
 *
 * Les scripts de app/Console sont exclus : leur sortie va dans un terminal, pas
 * dans l interface.
 */
final class AucunEmojiTest extends TestCase
{
    /** Plages Unicode couvrant les emoji et pictogrammes decoratifs. */
    private const PLAGES = '[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}]';

    public function test_aucun_emoji_dans_les_composants_et_les_vues(): void
    {
        $fautifs = [];

        foreach ($this->fichiers() as $chemin) {
            $contenu = (string) file_get_contents($chemin);

            if (preg_match('/' . self::PLAGES . '/u', $contenu, $trouve)) {
                $relatif = str_replace(BASE_PATH . DIRECTORY_SEPARATOR, '', $chemin);
                $fautifs[] = $relatif . ' (' . $trouve[0] . ')';
            }
        }

        self::assertSame(
            [],
            $fautifs,
            "Emoji trouvé(s). Utilisez une icône SVG :\n  " . implode("\n  ", $fautifs)
        );
    }

    public function test_les_pastilles_ne_recoivent_pas_de_svg_dans_leur_libelle(): void
    {
        // Ui::badge echappe son libelle : y glisser du SVG affiche le code source
        // a l ecran. L option « icon » existe pour cela.
        $fautifs = [];

        foreach ($this->fichiers() as $chemin) {
            $contenu = (string) file_get_contents($chemin);

            if (preg_match("/Ui::badge\('<svg/", $contenu)) {
                $fautifs[] = str_replace(BASE_PATH . DIRECTORY_SEPARATOR, '', $chemin);
            }
        }

        self::assertSame(
            [],
            $fautifs,
            "Pastille(s) recevant du SVG en libellé. Passez-le dans l'option 'icon' :\n  " . implode("\n  ", $fautifs)
        );
    }

    /**
     * @return array<int, string>
     */
    private function fichiers(): array
    {
        $fichiers = [];

        foreach (['app', 'views'] as $racine) {
            $iterateur = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(BASE_PATH . '/' . $racine, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterateur as $fichier) {
                if (!$fichier->isFile() || $fichier->getExtension() !== 'php') {
                    continue;
                }

                if (str_contains($fichier->getPathname(), DIRECTORY_SEPARATOR . 'Console' . DIRECTORY_SEPARATOR)) {
                    continue;
                }

                $fichiers[] = $fichier->getPathname();
            }
        }

        return $fichiers;
    }
}
