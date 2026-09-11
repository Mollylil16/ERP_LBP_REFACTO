<?php

declare(strict_types=1);

namespace Tests\Unit\View;

use Tests\TestCase;

/**
 * Aucun bouton ni lien ne doit rester sans libellé visible.
 *
 * Ce test naît d'une régression réelle : la suppression des emoji de l'interface
 * a vidé quatre commandes dont le libellé était l'emoji lui-même. Un bouton
 * « Supprimer » réduit à un rectangle rouge muet et un lien « Réinitialiser »
 * invisible sont partis en production sans que rien ne les signale.
 *
 * Un contrôle est accepté s'il porte un texte, une icône, ou à défaut un nom
 * accessible (aria-label, title, aria-labelledby) : un point de carrousel n'a
 * pas à écrire son nom, mais il doit pouvoir se faire annoncer.
 */
final class ControlesEtiquetesTest extends TestCase
{
    /** Blocs PHP, neutralisés avant l'analyse du balisage. */
    private const BLOC_PHP = '/<\?(?:php|=)?.*?\?>/s';

    public function test_aucun_bouton_ni_lien_sans_libelle_ni_nom_accessible(): void
    {
        $fautifs = [];

        foreach ($this->fichiers() as $chemin) {
            $brut = (string) file_get_contents($chemin);

            /*
             * Chaque bloc PHP devient un bloc de « x » de même longueur : les
             * positions et les numéros de ligne restent exacts, et les balises
             * fermantes présentes dans les attributs ne cassent plus la
             * reconnaissance du balisage.
             */
            $neutre = (string) preg_replace_callback(
                self::BLOC_PHP,
                static fn(array $t): string => (string) preg_replace('/[^\n]/', 'x', $t[0]),
                $brut
            );

            foreach (['button', 'a'] as $balise) {
                $motif = '/<' . $balise . '\b([^>]*)>\s*<\/' . $balise . '>/s';

                if (!preg_match_all($motif, $neutre, $trouves, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
                    continue;
                }

                foreach ($trouves as $trouve) {
                    $attributs = $trouve[1][0];

                    if ($this->porteUnNomAccessible($attributs)) {
                        continue;
                    }

                    $ligne = substr_count(substr($neutre, 0, (int) $trouve[0][1]), "\n") + 1;
                    $fautifs[] = str_replace(BASE_PATH . DIRECTORY_SEPARATOR, '', $chemin)
                        . ':' . $ligne . ' (<' . $balise . '>)';
                }
            }
        }

        self::assertSame(
            [],
            $fautifs,
            "Commande(s) sans libellé visible ni nom accessible.\n"
                . "Ajoutez un texte, une icône SVG, ou à défaut un aria-label :\n  "
                . implode("\n  ", $fautifs)
        );
    }

    private function porteUnNomAccessible(string $attributs): bool
    {
        foreach (['aria-label', 'aria-labelledby', 'title'] as $attribut) {
            if (preg_match('/\b' . preg_quote($attribut, '/') . '\s*=\s*["\'][^"\']+/i', $attributs)) {
                return true;
            }
        }

        // Une commande désactivée ne reçoit ni clic ni focus : elle ne s'annonce
        // pas et n'a donc pas de nom à porter.
        return (bool) preg_match('/\bdisabled\b/i', $attributs);
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
                if ($fichier->isFile() && $fichier->getExtension() === 'php') {
                    $fichiers[] = $fichier->getPathname();
                }
            }
        }

        return $fichiers;
    }
}
