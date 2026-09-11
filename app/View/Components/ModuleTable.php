<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

/**
 * Tableau de données commun aux modules métier.
 *
 * Les six modules construits sur des vues de lecture affichent tous des listes
 * de la même forme. Les écrire six fois à la main garantissait six alignements
 * légèrement différents ; ils passent donc tous par ici.
 *
 * Convention d'échappement : le contenu des cellules est du HTML produit par le
 * composant appelant (pastilles, mise en forme des montants). C'est donc à
 * l'appelant d'échapper toute donnée venant de la base, avec View::e(), comme
 * partout ailleurs dans les composants.
 */
final class ModuleTable
{
    /**
     * @param array<int, array{label:string, align?:string}> $colonnes
     * @param array<int, array<int, string>> $lignes cellules déjà rendues en HTML
     */
    public static function render(array $colonnes, array $lignes, string $videTitre = 'Aucune donnée', string $videTexte = ''): string
    {
        if ($lignes === []) {
            return Ui::emptyState($videTitre, $videTexte);
        }

        $entetes = '';
        foreach ($colonnes as $colonne) {
            $entetes .= '<th' . self::alignement($colonne['align'] ?? 'left') . '>'
                . View::e($colonne['label']) . '</th>';
        }

        $corps = '';
        foreach ($lignes as $ligne) {
            $corps .= '<tr>';
            foreach (array_values($ligne) as $index => $cellule) {
                $align = $colonnes[$index]['align'] ?? 'left';
                $corps .= '<td' . self::alignement($align) . '>' . $cellule . '</td>';
            }
            $corps .= '</tr>';
        }

        return '<div class="finea-table-wrapper"><table class="finea-table">'
            . '<thead><tr>' . $entetes . '</tr></thead>'
            . '<tbody>' . $corps . '</tbody>'
            . '</table></div>';
    }

    /**
     * Barre de progression, pour les taux de remplissage.
     */
    public static function jauge(int $pourcentage, string $tone = 'neutral'): string
    {
        $borne = max(0, min(100, $pourcentage));
        $couleur = match ($tone) {
            'danger' => '#dc2626',
            'warning' => '#d97706',
            'success' => '#16a34a',
            default => '#475569',
        };

        return '<span class="module-gauge" role="img" aria-label="' . View::e($pourcentage . ' pour cent') . '"'
            . ' style="display:inline-flex; align-items:center; gap:8px; min-width:120px;">'
            . '<span style="flex:1; height:6px; border-radius:999px; background:rgba(100,116,139,.25); overflow:hidden;">'
            . '<span style="display:block; height:100%; width:' . $borne . '%; background:' . $couleur . ';"></span>'
            . '</span>'
            . '<strong style="font-variant-numeric:tabular-nums;">' . View::e((string) $pourcentage) . ' %</strong>'
            . '</span>';
    }

    /**
     * Montant aligné à droite, chiffres en chasse fixe pour que les colonnes
     * se lisent verticalement.
     */
    public static function montant(float $valeur, string $devise = 'XOF', int $decimales = 0): string
    {
        return '<span style="font-variant-numeric:tabular-nums;">'
            . View::e(number_format($valeur, $decimales, ',', ' ') . ' ' . $devise)
            . '</span>';
    }

    private static function alignement(string $align): string
    {
        return match ($align) {
            'right' => ' style="text-align:right;"',
            'center' => ' style="text-align:center;"',
            default => '',
        };
    }
}
