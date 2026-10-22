<?php

declare(strict_types=1);

namespace Tests\Support\Messagerie;

use App\Repositories\Messagerie\NotificationsMessagerieDepotInterface;

/**
 * Doublure des lectures des notifications (lot 4), posée sur la doublure
 * en mémoire des tables msg_* : les tests écrivent avec MessagerieService,
 * comme l'écran, et les notifications relisent les mêmes données — avec les
 * mêmes filtres que NotificationsMessagerieRepository en SQL.
 */
final class NotificationsDepotMemoire implements NotificationsMessagerieDepotInterface
{
    /** @var array<string, string> clé « conv-user » => dernier_push_at */
    public array $derniersPush = [];

    public function __construct(private DepotMessagerieMemoire $messagerie)
    {
    }

    public function messageANotifier(int $messageId): ?array
    {
        $message = $this->messagerie->message($messageId);
        if ($message === null) {
            return null;
        }

        $conversation = $this->messagerie->conversation((int) $message['conversation_id']);
        $piece = null;
        foreach ($this->messagerie->pieces as $p) {
            if ((int) $p['message_id'] === $messageId) {
                $fichier = $this->messagerie->fichiers[(int) $p['fichier_id']];
                $piece = ['nom_origine' => $p['nom_origine'], 'categorie' => $fichier['categorie'], 'extension' => $fichier['extension']];
                break;
            }
        }

        return $message + [
            'conversation_type' => $conversation['type'] ?? 'groupe',
            'conversation_nom' => $conversation['nom'] ?? null,
            'piece' => $piece,
        ];
    }

    public function destinataires(int $conversationId, int $messageId, int $auteurId): array
    {
        $resultat = [];
        foreach ($this->messagerie->membres as $cle => $m) {
            $userId = (int) $m['user_id'];
            if ((int) $m['conversation_id'] !== $conversationId || $m['quitte_at'] !== null || $userId === $auteurId) {
                continue;
            }

            $nonLus = 0;
            foreach ($this->messagerie->messages as $message) {
                if ((int) $message['conversation_id'] === $conversationId
                    && (int) $message['id'] > (int) $m['dernier_message_lu_id']
                    && (int) $message['id'] <= $messageId
                    && (int) $message['auteur_id'] !== $userId
                    && $message['supprime_at'] === null) {
                    $nonLus++;
                }
            }

            $utilisateur = $this->messagerie->utilisateurs[$userId];
            $resultat[] = [
                'user_id' => $userId,
                'nom' => $utilisateur['nom'],
                'actif' => (bool) $utilisateur['actif'],
                'sourdine' => (bool) $m['sourdine'],
                'apercu_masque' => (bool) ($this->messagerie->preferences($userId)['apercu_masque'] ?? false),
                'dernier_push_at' => $this->derniersPush[$cle] ?? null,
                'non_lus' => $nonLus,
            ];
        }

        return $resultat;
    }

    public function marquerNotifies(int $conversationId, array $userIds, string $maintenant): void
    {
        foreach ($userIds as $userId) {
            $this->derniersPush["$conversationId-$userId"] = $maintenant;
        }
    }
}
