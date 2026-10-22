<?php

declare(strict_types=1);

namespace App\Repositories\Messagerie;

use PDO;

/**
 * Lectures et écriture des notifications de la messagerie, sous MySQL /
 * MariaDB. Requêtes préparées natives : chaque répétition d'un paramètre
 * porte son propre nom.
 *
 * msg_membres.dernier_push_at est ajoutée par MigrationRunner
 * (createMessagerieNotificationsColonne).
 */
final class NotificationsMessagerieRepository implements NotificationsMessagerieDepotInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function messageANotifier(int $messageId): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT m.id, m.conversation_id, m.auteur_id, u.full_name AS auteur_nom, m.texte,
                   m.transfere_de_id, m.supprime_at, c.type AS conversation_type, c.nom AS conversation_nom
            FROM msg_messages m
            JOIN msg_conversations c ON c.id = m.conversation_id
            LEFT JOIN users u ON u.id = m.auteur_id
            WHERE m.id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $messageId]);
        $message = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$message) {
            return null;
        }

        $piece = $this->pdo->prepare('
            SELECT pj.nom_origine, f.categorie, f.extension
            FROM msg_pieces_jointes pj
            JOIN msg_fichiers f ON f.id = pj.fichier_id
            WHERE pj.message_id = :m
            ORDER BY pj.id
            LIMIT 1
        ');
        $piece->execute(['m' => $messageId]);
        $message['piece'] = $piece->fetch(PDO::FETCH_ASSOC) ?: null;

        return $message;
    }

    public function destinataires(int $conversationId, int $messageId, int $auteurId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT mb.user_id, u.full_name AS nom, (u.status = 'active') AS actif, mb.sourdine,
                   mb.dernier_push_at, COALESCE(p.apercu_masque, 0) AS apercu_masque,
                   (
                       SELECT COUNT(*)
                       FROM msg_messages m
                       WHERE m.conversation_id = mb.conversation_id
                         AND m.id > mb.dernier_message_lu_id
                         AND m.id <= :m
                         AND m.auteur_id <> mb.user_id
                         AND m.supprime_at IS NULL
                   ) AS non_lus
            FROM msg_membres mb
            JOIN users u ON u.id = mb.user_id
            LEFT JOIN msg_presence p ON p.user_id = mb.user_id
            WHERE mb.conversation_id = :c
              AND mb.quitte_at IS NULL
              AND mb.user_id <> :a
        ");
        $stmt->execute(['m' => $messageId, 'c' => $conversationId, 'a' => $auteurId]);

        return array_map(static fn(array $l): array => [
            'user_id' => (int) $l['user_id'],
            'nom' => (string) $l['nom'],
            'actif' => (bool) $l['actif'],
            'sourdine' => (bool) $l['sourdine'],
            'apercu_masque' => (bool) $l['apercu_masque'],
            'dernier_push_at' => $l['dernier_push_at'] !== null ? (string) $l['dernier_push_at'] : null,
            'non_lus' => (int) $l['non_lus'],
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function marquerNotifies(int $conversationId, array $userIds, string $maintenant): void
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if ($userIds === []) {
            return;
        }

        $marques = [];
        $parametres = ['d' => $maintenant, 'c' => $conversationId];
        foreach ($userIds as $i => $id) {
            $marques[] = ':u' . $i;
            $parametres['u' . $i] = $id;
        }

        $this->pdo->prepare('
            UPDATE msg_membres SET dernier_push_at = :d
            WHERE conversation_id = :c AND user_id IN (' . implode(', ', $marques) . ')
        ')->execute($parametres);
    }
}
