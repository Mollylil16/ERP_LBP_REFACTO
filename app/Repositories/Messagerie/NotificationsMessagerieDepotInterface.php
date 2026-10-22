<?php

declare(strict_types=1);

namespace App\Repositories\Messagerie;

/**
 * Ce que les notifications de la messagerie (lot 4) lisent et écrivent.
 *
 * Séparé de MessagerieDepotInterface : les notifications partent après la
 * réponse HTTP, hors des règles du service, et n'ont besoin que de trois
 * requêtes. Les doublures des tests de la messagerie n'ont rien à apprendre.
 */
interface NotificationsMessagerieDepotInterface
{
    /**
     * Le message à annoncer, avec sa discussion et sa première pièce jointe.
     *
     * Ligne : id, conversation_id, auteur_id, auteur_nom, texte,
     * transfere_de_id, supprime_at, conversation_type, conversation_nom,
     * piece (null, ou nom_origine, categorie, extension).
     *
     * @return array<string, mixed>|null
     */
    public function messageANotifier(int $messageId): ?array;

    /**
     * Les membres actifs de la discussion, auteur exclu, avec ce qu'il faut
     * pour décider : user_id, nom, actif (bool), sourdine (bool),
     * apercu_masque (bool), dernier_push_at (?string), non_lus (int :
     * messages des autres, non supprimés, au-delà de son dernier lu, jusqu'à
     * $messageId compris).
     *
     * @return array<int, array<string, mixed>>
     */
    public function destinataires(int $conversationId, int $messageId, int $auteurId): array;

    /**
     * Retient l'heure de la dernière notification de ces membres pour cette
     * discussion (au plus une par minute dans un groupe).
     *
     * @param array<int, int> $userIds
     */
    public function marquerNotifies(int $conversationId, array $userIds, string $maintenant): void;
}
