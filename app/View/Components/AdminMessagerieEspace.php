<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

/**
 * L'espace qu'occupent les fichiers de la messagerie, pour l'administrateur.
 *
 * Ce que montre l'écran est ce qui pèse sur le disque de l'hébergement :
 * après dédoublonnage et après réduction des photos. Au-delà du seuil, la
 * jauge passe au rouge — c'est le moment de regarder par mois et par
 * catégorie ce qui grossit.
 */
final class AdminMessagerieEspace
{
    private const LIBELLES = [
        'pdf' => 'PDF',
        'image' => 'Photos et images',
        'word' => 'Documents Word',
        'tableur' => 'Tableurs (Excel, CSV)',
        'presentation' => 'Présentations',
        'texte' => 'Textes',
    ];

    /** @param array<string, mixed> $etat MessagerieEspace::etat() */
    public static function page(array $etat): string
    {
        $alerte = (bool) $etat['alerte'];

        return '<div class="finea-shell"><div class="finea-container">'
            . Ui::pageHeader(
                'Espace de la messagerie',
                'Ce que les fichiers échangés occupent réellement sur le disque du serveur.',
                ['eyebrow' => 'Administration et qualité', 'class' => 'admin-hero']
            )
            . Dashboard::kpis([
                [
                    'label' => 'Espace occupé',
                    'value' => (string) $etat['lisible'],
                    'meta' => 'Seuil d’alerte : ' . $etat['seuil_lisible'],
                    'tone' => $alerte ? 'danger' : 'primary',
                ],
                [
                    'label' => 'Fichiers sur le disque',
                    'value' => number_format((int) $etat['fichiers'], 0, ',', ' '),
                    'meta' => 'Un par contenu distinct',
                    'tone' => 'info',
                ],
                [
                    'label' => 'Pièces jointes',
                    'value' => number_format((int) $etat['pieces'], 0, ',', ' '),
                    'meta' => 'Envois et transferts compris',
                    'tone' => 'info',
                ],
                [
                    'label' => 'Gagné par dédoublonnage',
                    'value' => (string) $etat['economie_lisible'],
                    'meta' => 'Fichiers envoyés plusieurs fois, gardés une seule',
                    'tone' => 'success',
                ],
            ], ['aria-label' => 'Espace de la messagerie'])
            . Ui::section('Jauge', self::jauge($etat), $alerte ? 'Seuil dépassé' : 'Sous le seuil')
            . Ui::section('Par mois', self::parMois($etat['par_mois']), 'Mois où les fichiers ont été reçus')
            . Ui::section('Par catégorie', self::parCategorie($etat['par_categorie']))
            . '</div></div>';
    }

    /** @param array<string, mixed> $etat */
    private static function jauge(array $etat): string
    {
        $pourcentage = (int) $etat['pourcentage'];
        $alerte = (bool) $etat['alerte'];
        $tone = $alerte ? 'danger' : ($pourcentage >= 80 ? 'warning' : 'success');

        $message = $alerte
            ? 'Les fichiers de la messagerie dépassent ' . $etat['seuil_lisible']
                . '. Vérifiez l’espace restant chez l’hébergeur avant qu’il ne refuse les envois.'
            : 'Les fichiers occupent ' . $etat['lisible'] . ' sur ' . $etat['seuil_lisible'] . ' autorisés avant alerte.';

        return '<p style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin:0;">'
            . Ui::badge($alerte ? 'Alerte' : 'Normal', $alerte ? 'danger' : 'success')
            . ModuleTable::jauge($pourcentage, $tone)
            . '</p>'
            . '<p style="margin:12px 0 0;">' . View::e($message) . '</p>';
    }

    /** @param array<int, array<string, mixed>> $lignes */
    private static function parMois(array $lignes): string
    {
        $rendu = [];
        foreach ($lignes as $l) {
            $rendu[] = [
                View::e(self::mois((string) $l['mois'])),
                View::e(number_format((int) $l['fichiers'], 0, ',', ' ')),
                View::e((string) $l['lisible']),
            ];
        }

        return ModuleTable::render(
            [['label' => 'Mois'], ['label' => 'Fichiers', 'align' => 'right'], ['label' => 'Espace', 'align' => 'right']],
            $rendu,
            'Aucun fichier',
            'Aucun fichier n’a encore été envoyé dans la messagerie.'
        );
    }

    /** @param array<int, array<string, mixed>> $lignes */
    private static function parCategorie(array $lignes): string
    {
        $rendu = [];
        foreach ($lignes as $l) {
            $rendu[] = [
                View::e(self::LIBELLES[(string) $l['categorie']] ?? (string) $l['categorie']),
                View::e(number_format((int) $l['fichiers'], 0, ',', ' ')),
                View::e((string) $l['lisible']),
            ];
        }

        return ModuleTable::render(
            [['label' => 'Catégorie'], ['label' => 'Fichiers', 'align' => 'right'], ['label' => 'Espace', 'align' => 'right']],
            $rendu,
            'Aucun fichier'
        );
    }

    /** « 2026-10 » => « octobre 2026 ». */
    private static function mois(string $mois): string
    {
        $noms = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        $n = (int) substr($mois, 5, 2);

        return $n >= 1 && $n <= 12 ? $noms[$n - 1] . ' ' . substr($mois, 0, 4) : $mois;
    }
}
