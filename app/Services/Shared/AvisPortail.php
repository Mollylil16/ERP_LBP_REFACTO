<?php

declare(strict_types=1);

namespace App\Services\Shared;

/**
 * Les avis de la direction, affichés sur le portail pendant quelques jours.
 *
 * Un avis se lit au moment où l'on entre dans le logiciel, avant de choisir
 * son module : c'est le seul endroit par lequel tout le monde passe. Il
 * s'efface tout seul au bout de sa durée — un message qui reste des semaines
 * n'est plus lu, et l'on finit par ne plus voir non plus celui qui compte.
 *
 * Aucune base, aucune table : la date d'aujourd'hui suffit à décider. Un avis
 * périmé se retire du tableau ci-dessous quand on repasse par ici.
 */
final class AvisPortail
{
    /**
     * @var array<int, array{id:string, debut:string, jours:int, ton:string, titre:string, paragraphes:array<int, string>}>
     */
    private const AVIS = [
        [
            'id' => 'point-caisse-1er-octobre',
            'debut' => '2026-09-24',
            'jours' => 5,
            'ton' => 'attention',
            'titre' => 'À toutes les agences — nouvelle règle à partir du 1er octobre',
            'paragraphes' => [
                'Chaque soir, avant de partir, vous devez fermer votre caisse dans le logiciel : comptez votre argent, entrez le montant, validez.',
                'À partir du 1er octobre, si la caisse d\'un jour n\'est pas fermée, vous ne pourrez ni facturer ni encaisser le lendemain matin. Le logiciel vous montrera le jour qui manque et le bouton pour le fermer. Dès que c\'est fait, la caisse rouvre.',
                'Fermez à la fin de la journée, pas avant. Si vous fermez à 15 h et qu\'un client paie à 17 h, le logiciel rouvre votre caisse tout seul et vous demande de recompter. C\'est normal, ce n\'est pas une erreur de votre part. Si vous descendez tôt, ne fermez pas la caisse : laissez-la ouverte et fermez-la le lendemain matin à votre arrivée — c\'est cette fermeture qui ouvrira la caisse du jour.',
                'Pour l\'agence de l\'aéroport : cet écran existe et vous y avez accès. Il s\'appelle « Points de Caisse », dans le menu Finance. Vous devez le remplir chaque jour comme les autres agences.',
                'Ce n\'est pas une sanction. C\'est pour que l\'argent que vous comptez corresponde à ce que le logiciel affiche — et que personne ne puisse vous reprocher un écart que vous n\'avez pas fait.',
            ],
        ],
    ];

    /**
     * Les avis à afficher aujourd'hui.
     *
     * @return array<int, array{id:string, debut:string, jours:int, ton:string, titre:string, paragraphes:array<int, string>, fin:string, reste:int}>
     */
    public static function actifs(?string $aujourdhui = null): array
    {
        $aujourdhui ??= date('Y-m-d');
        $actifs = [];

        foreach (self::AVIS as $avis) {
            // Le premier jour compte : un avis de cinq jours posé le 24 se lit
            // du 24 au 28, et a disparu le 29.
            $fin = date('Y-m-d', (int) strtotime($avis['debut'] . ' +' . ($avis['jours'] - 1) . ' days'));

            if ($aujourdhui < $avis['debut'] || $aujourdhui > $fin) {
                continue;
            }

            $actifs[] = $avis + [
                'fin' => $fin,
                'reste' => (int) ceil((strtotime($fin) - strtotime($aujourdhui)) / 86400) + 1,
            ];
        }

        return $actifs;
    }
}
