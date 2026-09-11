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
    /**
     * Plages Unicode couvrant les emoji et pictogrammes decoratifs.
     *
     * Le bloc U+2300-U+23FF n'est pas pris en entier : il contient a la fois des
     * emoji (montre, sablier, boutons de lecture) et des symboles techniques
     * legitimes. Seules les sous-plages qui se rendent en emoji sur les systemes
     * courants sont refusees. Un sablier U+23F3 avait ainsi traverse le guide de
     * saisie sans etre vu.
     *
     * Les fleches typographiques (U+2190-U+21FF) et les puces geometriques
     * (U+25A0-U+25FF) restent autorisees : elles suivent la police et la couleur
     * du texte, ce qui est exactement ce qu'on reproche aux emoji de ne pas faire.
     */
    private const PLAGES = '[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}'
        . '\x{231A}-\x{231B}\x{2328}\x{23CF}\x{23E9}-\x{23FA}'
        . '\x{21EA}\x{2304}\x{2049}\x{203C}\x{2122}\x{2139}\x{3030}\x{303D}\x{3297}\x{3299}]';

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
