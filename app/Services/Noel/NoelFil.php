<?php

declare(strict_types=1);

namespace App\Services\Noel;

/**
 * Ce que NOEL retient du fil de la conversation.
 *
 * Sans cela il pose des questions qu'il n'entend pas : « voulez-vous que je
 * transmette ? » — « oui » — « je n'ai pas compris votre question ». C'est
 * pire que de ne rien proposer : quelqu'un qui dit oui et n'obtient rien cesse
 * de faire confiance.
 *
 * CETTE MEMOIRE VIT DANS LA SESSION, PAS EN BASE. Trois raisons. Savoir qui a
 * seche sur l'enigme de mercredi ne regarde personne. Une question en attente
 * de confirmation n'a pas a survivre a la journee — « oui » une heure plus
 * tard ne devrait rien transmettre. Et la session expire au bout de vingt-
 * quatre minutes sur ce serveur, ce qui fait exactement la duree de vie qu'on
 * veut pour un contexte de conversation.
 */
final class NoelFil
{
    private const CLE = 'noel_fils';

    /** Au-dela, on ne garde pas le contexte de conversations oubliees. */
    private const FILS_RETENUS = 10;

    /**
     * Ce dont on parlait, et ce que NOEL attend.
     *
     * @return array{concept: ?string, module: ?string, attente: ?string, question: ?string, essais: int}
     */
    public static function dernier(int $conversationId): array
    {
        $vide = ['concept' => null, 'module' => null, 'attente' => null, 'question' => null, 'essais' => 0];

        if ($conversationId <= 0) {
            return $vide;
        }

        $fils = $_SESSION[self::CLE] ?? [];

        if (!is_array($fils) || !isset($fils[$conversationId]) || !is_array($fils[$conversationId])) {
            return $vide;
        }

        return array_merge($vide, $fils[$conversationId]);
    }

    /**
     * Retenir l'etat du fil apres une reponse.
     *
     * @param array<string, mixed> $etat
     */
    public static function retenir(int $conversationId, array $etat): void
    {
        if ($conversationId <= 0) {
            return;
        }

        $fils = $_SESSION[self::CLE] ?? [];

        if (!is_array($fils)) {
            $fils = [];
        }

        $fils[$conversationId] = [
            'concept' => $etat['concept'] ?? null,
            'module' => $etat['module'] ?? null,
            'attente' => $etat['attente'] ?? null,
            'question' => $etat['question'] ?? null,
            'essais' => (int) ($etat['essais'] ?? 0),
        ];

        /*
         * On ne garde que les derniers fils. Un agent qui ecrit a vingt
         * collegues n'a pas besoin qu'on se souvienne de tout, et une session
         * qui enfle finit par couter plus cher qu'elle ne rend service.
         */
        if (count($fils) > self::FILS_RETENUS) {
            $fils = array_slice($fils, -self::FILS_RETENUS, null, true);
        }

        $_SESSION[self::CLE] = $fils;
    }

    /** NOEL n'attend plus rien sur ce fil. */
    public static function oublierAttente(int $conversationId): void
    {
        $fil = self::dernier($conversationId);
        $fil['attente'] = null;
        $fil['question'] = null;
        $fil['essais'] = 0;

        self::retenir($conversationId, $fil);
    }

    /** Pour les tests, et pour repartir propre. */
    public static function toutOublier(): void
    {
        unset($_SESSION[self::CLE]);
    }
}
