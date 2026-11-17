<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\Csrf;
use App\Helpers\View;

/**
 * Page « Supprimer une fiche » du personnel (décidée le 17/11/2026).
 *
 * Deux cas : la fiche a un vrai historique, et la page explique pourquoi
 * elle ne peut pas être supprimée et renvoie vers « Sortie » ; sinon elle
 * montre ce qui va se passer (compte de connexion désactivé) et demande un
 * motif et une confirmation.
 */
final class RhSuppression
{
    /**
     * @param array{employee: array<string,mixed>, bloquants: array<int,string>, comptes: array<int,array<string,mixed>>} $avant
     */
    public static function page(array $avant): string
    {
        return '<div class="finea-shell"><div class="finea-container">' . self::contenu($avant) . '</div></div>';
    }

    /**
     * @param array{employee: array<string,mixed>, bloquants: array<int,string>, comptes: array<int,array<string,mixed>>} $avant
     */
    private static function contenu(array $avant): string
    {
        $e = $avant['employee'];
        $id = (int) ($e['id'] ?? 0);
        $nom = (string) ($e['full_name'] ?? '');
        $matricule = (string) (($e['employee_number'] ?? '') ?: 'sans matricule');

        $html = Ui::pageHeader(
            'Supprimer la fiche de ' . $nom,
            'Pour une fiche créée par erreur : doublon, test ou faute de saisie. Un vrai départ se fait par « Sortie ».',
            [
                'eyebrow' => 'Dossier personnel',
                'class' => 'rh-hero',
                'actions' => [Ui::button('Retour au dossier', ['href' => 'rh/personnel/' . $id, 'variant' => 'secondary'])],
            ]
        );

        if ($avant['bloquants'] !== []) {
            $liste = '';
            foreach ($avant['bloquants'] as $b) {
                $liste .= '<li>' . View::e($b) . '</li>';
            }

            return $html . Rh::card(
                '<p>La fiche <strong>' . View::e($nom) . '</strong> (' . View::e($matricule) . ') a déjà un historique :</p>'
                    . '<ul>' . $liste . '</ul>'
                    . '<p>Ces informations ne doivent pas être perdues. Pour une personne qui a quitté LBP, '
                    . 'utilisez « Sortie / réintégration » : le dossier est conservé.</p>'
                    . Rh::formActions([
                        Ui::button('Faire une sortie', ['href' => 'rh/personnel/' . $id . '/sortie', 'variant' => 'accent']),
                        Ui::button('Retour au dossier', ['href' => 'rh/personnel/' . $id, 'variant' => 'secondary']),
                    ]),
                ['title' => 'Suppression impossible']
            );
        }

        $comptes = '';
        foreach ($avant['comptes'] as $c) {
            $comptes .= '<li>' . View::e((string) $c['full_name']) . ' — ' . View::e((string) $c['email']) . '</li>';
        }
        $avertissement = '<p>Vous allez supprimer définitivement la fiche <strong>' . View::e($nom) . '</strong> ('
            . View::e($matricule) . '). Elle n’a ni contrat, ni paie, ni pointage, ni document.</p>'
            . ($comptes !== ''
                ? '<p>Le compte de connexion suivant sera <strong>désactivé</strong> : la personne ne pourra plus se connecter.</p><ul>' . $comptes . '</ul>'
                : '<p>Aucun compte de connexion n’est rattaché à cette fiche.</p>')
            . '<p>Le contenu de la fiche reste consultable dans le journal d’audit.</p>';

        $formulaire = '<form method="post" action="' . View::url('rh/personnel/' . $id . '/supprimer') . '" class="rh-compact-form">'
            . Csrf::input()
            . Form::textarea('motif', [
                'label' => 'Motif de la suppression',
                'rows' => 3,
                'required' => true,
                'placeholder' => 'Par exemple : doublon de la fiche LBP-016',
            ])
            . Form::checkbox('confirmation', [
                'label' => 'Je confirme : cette suppression est définitive.',
                'required' => true,
                'value' => '1',
            ])
            . Rh::formActions([
                Ui::button('Supprimer définitivement', ['type' => 'submit', 'variant' => 'danger']),
                Ui::button('Annuler', ['href' => 'rh/personnel/' . $id, 'variant' => 'secondary']),
            ])
            . '</form>';

        return $html . Rh::card($avertissement . $formulaire, ['title' => 'Confirmer la suppression']);
    }
}
