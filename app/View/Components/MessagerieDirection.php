<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

/**
 * Écran « Toutes les discussions », réservé au DG et à l'administrateur.
 *
 * Une liste filtrable de toutes les discussions de la messagerie ; un clic
 * ouvre la discussion dans l'écran habituel, en lecture seule, et c'est cette
 * ouverture qui est inscrite dans le journal des accès, affiché en dessous.
 */
final class MessagerieDirection
{
    private const TYPES = [
        'direct' => ['Discussion à deux', 'info'],
        'groupe' => ['Groupe', 'success'],
        'annonces' => ['Annonces', 'warning'],
    ];

    /**
     * @param array{conversations: array<int, array<string, mixed>>, acces: array<int, array<string, mixed>>, plafond: int} $vue
     * @param array{q: string, type: string} $filtres
     */
    public static function ecran(array $vue, array $filtres): string
    {
        $conversations = $vue['conversations'];
        $nombre = count($conversations);
        $sousTitre = $nombre >= $vue['plafond']
            ? 'Les ' . $vue['plafond'] . ' plus récemment actives. Affinez la recherche pour en voir d’autres.'
            : $nombre . ' discussion' . ($nombre > 1 ? 's' : '') . '.';

        return '<div class="finea-shell"><div class="finea-container">'
            . Ui::pageHeader(
                'Toutes les discussions',
                'Ouvrez n’importe quelle discussion en lecture seule. Chaque ouverture est inscrite dans le journal ci-dessous.',
                ['eyebrow' => 'Messagerie · Direction']
            )
            . self::filtres($filtres)
            . Ui::section('Discussions', self::tableau($conversations, $filtres), $sousTitre)
            . Ui::section('Dernières ouvertures par la Direction', self::journal($vue['acces']), '30 dernières.')
            . '</div></div>';
    }

    /** @param array{q: string, type: string} $f */
    private static function filtres(array $f): string
    {
        $types = [['value' => '', 'label' => 'Tous les types']];
        foreach (self::TYPES as $code => [$libelle]) {
            $types[] = ['value' => $code, 'label' => $libelle];
        }

        return '<form method="get" action="' . View::url('messagerie/direction') . '" class="rh-personnel-filters">'
            . '<div class="lbp-jrn-filtres">'
            . Form::input('q', ['label' => 'Recherche', 'value' => $f['q'], 'id' => 'msgdir-q', 'placeholder' => 'Nom d’un participant ou d’un groupe'])
            . Form::select('type', $types, $f['type'], ['label' => 'Type', 'id' => 'msgdir-type'])
            . '</div>'
            . '<div class="rh-personnel-filter-actions">'
            . '<button type="submit" class="rh-filter-btn rh-filter-btn--primary">Filtrer</button>'
            . '<a href="' . View::url('messagerie/direction') . '" class="rh-filter-btn rh-filter-btn--reset">Réinitialiser</a>'
            . '</div></form>';
    }

    /**
     * @param array<int, array<string, mixed>> $conversations
     * @param array{q: string, type: string} $f
     */
    private static function tableau(array $conversations, array $f): string
    {
        $lignes = [];
        foreach ($conversations as $c) {
            [$libelle, $ton] = self::TYPES[(string) $c['type']] ?? [(string) $c['type'], 'neutral'];
            $participants = (string) $c['participants'];
            $titre = $c['nom'] ?? ($participants !== '' ? $participants : 'Discussion sans participant');

            $lignes[] = [
                '<a href="' . View::url('messagerie') . '#c=' . (int) $c['id'] . '">' . View::e((string) $titre) . '</a>',
                Ui::badge($libelle, $ton),
                View::e($c['nom'] !== null ? $participants : ''),
                View::e((string) $c['nb_messages']),
                View::e(self::date($c['dernier_message_at'] ?? null)),
            ];
        }

        $filtre = $f['q'] !== '' || $f['type'] !== '';

        return ModuleTable::render(
            [
                ['label' => 'Discussion'],
                ['label' => 'Type'],
                ['label' => 'Membres'],
                ['label' => 'Messages', 'align' => 'right'],
                ['label' => 'Dernier message'],
            ],
            $lignes,
            $filtre ? 'Aucune discussion ne correspond' : 'Aucune discussion pour l’instant',
            $filtre ? 'Changez la recherche ou le type.' : 'Les discussions apparaîtront ici dès que le personnel commencera à écrire.'
        );
    }

    /** @param array<int, array<string, mixed>> $acces */
    private static function journal(array $acces): string
    {
        $lignes = [];
        foreach ($acces as $a) {
            $nom = $a['nom'] ?? null;
            $lignes[] = [
                View::e(self::date((string) $a['created_at'])),
                View::e((string) ($a['lecteur'] ?? 'Compte supprimé')),
                '<a href="' . View::url('messagerie') . '#c=' . (int) $a['conversation_id'] . '">'
                    . View::e($nom !== null && $nom !== '' ? (string) $nom : 'Discussion n° ' . (int) $a['conversation_id']) . '</a>',
            ];
        }

        return ModuleTable::render(
            [['label' => 'Quand'], ['label' => 'Qui'], ['label' => 'Discussion ouverte']],
            $lignes,
            'Aucune ouverture',
            'Rien n’a encore été consulté depuis cet écran.'
        );
    }

    private static function date(?string $date): string
    {
        if ($date === null || $date === '') {
            return '—';
        }
        $t = strtotime($date);

        return $t === false ? $date : date('d/m/Y H:i', $t);
    }
}
